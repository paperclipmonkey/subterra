import express, { Request, Response } from 'express';
import { Storage } from '@google-cloud/storage';
import { PubSub } from '@google-cloud/pubsub';
import { TranscoderServiceClient } from '@google-cloud/video-transcoder';
import sharp from 'sharp';
import { getSecret } from './secrets';
import { setupSlackLogger } from './slack-logger';

// Setup Slack logging for console.warn and console.error
setupSlackLogger();

const app = express();
app.use(express.json());

const storage = new Storage();
const pubsub = new PubSub();
const transcoderClient = new TranscoderServiceClient();
const pubsubTopicName = process.env.IMAGE_PROCESSOR_PUBSUB_TOPIC || 'media-notifications';

/**
 * Image size presets for responsive srcset generation.
 * Each variant is stored as a WebP file with the size suffix.
 */
export const IMAGE_SIZES = [
    { name: 'desktop', width: 1920, quality: 70 },
    { name: 'tablet', width: 768, quality: 65 },
    { name: 'mobile', width: 480, quality: 60 },
] as const;

/**
 * Middleware to check API Key
 */
const checkApiKey = (req: Request, res: Response, next: () => void) => {
    const apiKey = getSecret('IMAGE_PROCESSOR_API_KEY');

    if (!apiKey) {
        // Fail closed: if no key is configured, deny all access to prevent
        // accidental exposure of this endpoint in misconfigured environments.
        console.error('IMAGE_PROCESSOR_API_KEY not configured. Refusing access.');
        return res.status(401).json({ error: 'Unauthorized: Service not configured' });
    }

    const providedKey = req.header('Authorization')?.replace('Bearer ', '');

    if (!providedKey || providedKey !== apiKey) {
        console.warn(`Unauthorized access attempt from ${req.ip}`);
        return res.status(401).json({ error: 'Unauthorized: Invalid or missing API Key' });
    }

    next();
};

// Health check endpoint (public)
app.get('/health', (_req: Request, res: Response) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
    });
});

/**
 * POST /
 * Eventarc GCS Trigger handler.
 * Authentication is enforced at the Cloud Run IAM level — only the Eventarc
 * trigger service account is granted the Cloud Run Invoker role.
 */
app.post('/', async (req: Request, res: Response) => {
    // Eventarc deliveries CloudEvent containing target Object notifications
    const data = req.body;
    const bucketName = data.bucket;
    const sourcePath = data.name;

    console.log(`Received GCS Trigger event for gs://${bucketName}/${sourcePath}`);

    if (!bucketName || !sourcePath) {
        return res.status(400).json({ error: 'Missing bucket or name' });
    }

    if (!sourcePath.startsWith('input/')) {
        console.log(`Ignoring trigger for non-input path: ${sourcePath}`);
        return res.json({ status: 'ignored' });
    }

    let customMetadata: any = {};

    try {
        const bucket = storage.bucket(bucketName);
        const [gcsMetadata] = await bucket.file(sourcePath).getMetadata();
        customMetadata = gcsMetadata.metadata || {};

        const mediaModel = customMetadata['media_model'] as string | undefined;
        const mediaId = customMetadata['media_id'] as string | undefined;
        const outputPrefix = customMetadata['output_prefix'] as string | undefined;
        const originalPath = customMetadata['file_path'] as string | undefined;

        if (!mediaModel || !mediaId || !outputPrefix) {
             console.log(`Skipping: missing custom metadata on ${sourcePath}`, customMetadata);
             return res.json({ status: 'ignored', message: 'Missing custom metadata' });
        }

        const isVideo = sourcePath.match(/\.(mp4|mov|avi|mkv|m4v)$/i);

        if (isVideo) {
             console.log(`Processing Video: gs://${bucketName}/${sourcePath}`);
             await submitTranscodeJob({ bucketName, sourcePath, outputPrefix, mediaModel, mediaId });
        } else {
             console.log(`Processing Image: gs://${bucketName}/${sourcePath}`);
             const result = await processImage({ bucketName, sourcePath, outputPrefix });

             const callbackPayload = {
                  status: 'succeeded',
                  mediaModel,
                  mediaId: Number(mediaId),
                  variants: result.variants,
                  sourcePath: result.sourcePath,
                  originalPath
             };

             const dataBuffer = Buffer.from(JSON.stringify(callbackPayload));
             await pubsub.topic(pubsubTopicName).publishMessage({ data: dataBuffer });

             console.log(`Published success notification to Pub/Sub topic ${pubsubTopicName} for ${sourcePath}`);
        }

        return res.json({ status: 'success' });
    } catch (error) {
        console.error('Media processing failed for %s:', sourcePath, error);

        try {
             if (customMetadata['media_id'] && customMetadata['media_model']) {
                  const callbackPayload = {
                       status: 'failed',
                       mediaModel: customMetadata['media_model'] as string,
                       mediaId: Number(customMetadata['media_id']),
                       error: (error as Error).message,
                       sourcePath
                  };
                  const dataBuffer = Buffer.from(JSON.stringify(callbackPayload));
                  await pubsub.topic(pubsubTopicName).publishMessage({ data: dataBuffer });
                  console.log(`Published failure notification to Pub/Sub for ${sourcePath}`);
             }
        } catch (pubSubError) {
             console.error('Failed to publish error to PubSub:', pubSubError);
        }

        return res.json({ status: 'ignored', message: (error as Error).message });
    }
});

interface ProcessImageParams {
    bucketName: string;
    sourcePath: string;
    outputPrefix: string;
}

/**
 * Core image processing logic.
 * Downloads source → generates WebP variants → uploads → notifies callback.
 */
export async function processImage(params: ProcessImageParams): Promise<{ variants: any[], sourcePath: string }> {
    const { bucketName, sourcePath, outputPrefix } = params;
    const bucket = storage.bucket(bucketName);

    console.log(`Processing image: gs://${bucketName}/${sourcePath}`);

    // Download source image into memory (images are typically <20MB)
    const [sourceBuffer] = await bucket.file(sourcePath).download();
    console.log(`Downloaded source image: ${sourceBuffer.length} bytes`);

    const variants: Array<{ name: string; path: string; width: number; height: number; size: number }> =
        [];

    // Generate each size variant
    for (const preset of IMAGE_SIZES) {
        const outputPath = `${outputPrefix.replace(/\/$/, '')}/${preset.name}.webp`;

        const processed = await sharp(sourceBuffer)
            .resize(preset.width, undefined, { withoutEnlargement: true, fit: 'inside' })
            .webp({ quality: preset.quality })
            .toBuffer({ resolveWithObject: true });

        await bucket.file(outputPath).save(processed.data, {
            contentType: 'image/webp',
            metadata: {
                cacheControl: 'public, max-age=31536000',
            },
        });

        variants.push({
            name: preset.name,
            path: outputPath,
            width: processed.info.width,
            height: processed.info.height,
            size: processed.info.size,
        });

        console.log(
            `Generated ${preset.name}: ${processed.info.width}x${processed.info.height} (${processed.info.size} bytes)`
        );
    }

    console.log(`Image processing complete for ${sourcePath}: ${variants.length} variants`);

    return {
        variants,
        sourcePath,
    };
}

/**
 * Submit a job to the GCP Transcoder API using the web-hd-mp4 template.
 */
async function submitTranscodeJob(params: {
    bucketName: string;
    sourcePath: string;
    outputPrefix: string;
    mediaModel: string;
    mediaId: string;
}) {
    const { bucketName, sourcePath, outputPrefix, mediaModel, mediaId } = params;
    const projectId = process.env.GCP_PROJECT_ID;
    const location = process.env.GCP_LOCATION || 'europe-west2';

    if (!projectId) {
        throw new Error('GCP_PROJECT_ID environment variable is not configured.');
    }

    const parent = transcoderClient.locationPath(projectId, location);

    const job = {
        inputUri: `gs://${bucketName}/${sourcePath}`,
        outputUri: `gs://${bucketName}/${outputPrefix}`,
        templateId: 'web-hd-mp4',
        labels: {
            'media_model': mediaModel,
            'media_id': mediaId,
            'output_dir': Buffer.from(outputPrefix).toString('base64'),
            'input_prefix': Buffer.from(sourcePath).toString('base64'),
        },
        config: {
            pubsubDestination: {
                topic: `projects/${projectId}/topics/${pubsubTopicName}`
            }
        }
    };

    const [response] = await transcoderClient.createJob({
        parent,
        job,
    });

    console.log(`Submitted GCP Transcoder Job: ${response.name} for ${sourcePath}`);
}

// Start server only if not in test environment
if (process.env.NODE_ENV !== 'test') {
    const port = parseInt(process.env.PORT || '8080');
    app.listen(port, () => {
        console.log(`Image processor service listening on port ${port}`);
    });
}

export default app;

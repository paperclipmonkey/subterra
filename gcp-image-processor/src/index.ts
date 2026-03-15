/**
 * Subterra Image Processor - GCP Cloud Run Application
 *
 * Receives image processing requests, downloads images from GCS,
 * generates multiple WebP variants for srcset (desktop, tablet, mobile),
 * uploads results back to GCS, and notifies the Laravel app via webhook.
 */
import express, { Request, Response } from 'express';
import { Storage } from '@google-cloud/storage';
import sharp from 'sharp';
import axios from 'axios';
import { getSecret } from './secrets';
import { setupSlackLogger } from './slack-logger';

// Setup Slack logging for console.warn and console.error
setupSlackLogger();

const app = express();
app.use(express.json());

const storage = new Storage();

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
        console.warn('IMAGE_PROCESSOR_API_KEY not configured. Endpoints are unprotected.');
        return next();
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

// Protected routes
app.use(['/process'], checkApiKey);

/**
 * POST /process
 *
 * Accepts a JSON body with:
 *   - bucket: GCS bucket name containing the source image
 *   - path: Object path within the bucket
 *   - callbackUrl: URL to POST results to when processing is complete
 *   - mediaModel: snake_case model label (e.g. "cave_media", "trip_media")
 *   - mediaId: Primary key of the media record
 *   - outputPrefix: GCS prefix for output files
 *
 * Downloads the source image, generates WebP variants at multiple sizes,
 * uploads them to the same bucket under outputPrefix, and POSTs results
 * to the callbackUrl.
 */
app.post('/process', async (req: Request, res: Response) => {
    const { bucket: bucketName, path: sourcePath, callbackUrl, mediaModel, mediaId, outputPrefix } =
        req.body;

    if (!bucketName || !sourcePath || !callbackUrl || !mediaModel || !mediaId || !outputPrefix) {
        return res.status(400).json({
            error: 'Missing required fields: bucket, path, callbackUrl, mediaModel, mediaId, outputPrefix',
        });
    }

    try {
        await processImage({ bucketName, sourcePath, callbackUrl, mediaModel, mediaId, outputPrefix });
        
        // Respond once processing is complete so Cloud Run doesn't throttle CPU
        return res.json({ status: 'success', mediaId });
    } catch (error) {
        console.error(`Image processing failed for ${mediaModel}#${mediaId}:`, error);

        // Notify callback of failure
        try {
            await axios.post(callbackUrl, {
                status: 'failed',
                mediaModel,
                mediaId,
                error: (error as Error).message,
            });
        } catch (cbError) {
            console.error('Failed to send error callback:', cbError);
        }

        return res.status(500).json({ error: (error as Error).message });
    }
});

interface ProcessImageParams {
    bucketName: string;
    sourcePath: string;
    callbackUrl: string;
    mediaModel: string;
    mediaId: string | number;
    outputPrefix: string;
}

/**
 * Core image processing logic.
 * Downloads source → generates WebP variants → uploads → notifies callback.
 */
export async function processImage(params: ProcessImageParams): Promise<void> {
    const { bucketName, sourcePath, callbackUrl, mediaModel, mediaId, outputPrefix } = params;
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

    // Notify the Laravel callback
    const callbackPayload = {
        status: 'succeeded',
        mediaModel,
        mediaId,
        variants,
        sourcePath,
    };

    console.log(`Sending callback to ${callbackUrl}`);
    await axios.post(callbackUrl, callbackPayload, {
        headers: {
            Authorization: `Bearer ${getSecret('CALLBACK_SECRET')}`,
            'Content-Type': 'application/json',
        },
        timeout: 10000,
    });

    console.log(`Image processing complete for ${mediaModel}#${mediaId}: ${variants.length} variants`);
}

// Start server only if not in test environment
if (process.env.NODE_ENV !== 'test') {
    const port = parseInt(process.env.PORT || '8080');
    app.listen(port, () => {
        console.log(`Image processor service listening on port ${port}`);
    });
}

export default app;

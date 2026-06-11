import request from 'supertest';
import app, { isHeic } from '../src/index';
import { Storage } from '@google-cloud/storage';
import sharp from 'sharp';
import convert from 'heic-convert';
import * as secrets from '../src/secrets';
var mockDownload: any = jest.fn();
var mockSave: any = jest.fn();
var mockGetMetadata: any = jest.fn();
var mockPublishMessage: any = jest.fn().mockResolvedValue('msg-id');
var mockTopic: any = jest.fn(() => ({
    publishMessage: (payload: any) => mockPublishMessage(payload)
}));

jest.mock('../src/secrets');

jest.mock('@google-cloud/storage', () => ({
    Storage: jest.fn(() => ({
        bucket: jest.fn(() => ({
            file: jest.fn(() => ({
                download: (options?: any) => mockDownload(options),
                save: (data: any, options?: any) => mockSave(data, options),
                getMetadata: () => mockGetMetadata()
            }))
        }))
    }))
}));

jest.mock('@google-cloud/pubsub', () => ({
    PubSub: jest.fn(() => ({
        topic: (name: string) => mockTopic(name)
    }))
}));
// Mock heic-convert (default-exported function) so tests don't decode real HEVC.
jest.mock('heic-convert', () =>
    jest.fn().mockResolvedValue(new TextEncoder().encode('decoded-jpeg-data').buffer)
);

// Mock sharp correctly since it's a default export of a function
jest.mock('sharp', () => {
    const mSharp = jest.fn(() => ({
        resize: jest.fn().mockReturnThis(),
        webp: jest.fn().mockReturnThis(),
        toBuffer: jest.fn().mockResolvedValue({
            data: Buffer.from('processed-image-data'),
            info: { width: 480, height: 320, size: 1024 }
        })
    }));
    return mSharp;
});

describe('isHeic', () => {
    const ftypBox = (major: string, compatible: string[] = []): Buffer => {
        const size = 16 + compatible.length * 4;
        const buf = Buffer.alloc(size);
        buf.writeUInt32BE(size, 0);
        buf.write('ftyp', 4, 'latin1');
        buf.write(major.padEnd(4), 8, 'latin1');
        compatible.forEach((brand, i) => buf.write(brand.padEnd(4), 16 + i * 4, 'latin1'));
        return buf;
    };

    it('detects the HEVC `heic` major brand', () => {
        expect(isHeic(ftypBox('heic'))).toBe(true);
    });

    it('detects an HEVC brand listed only as a compatible brand', () => {
        expect(isHeic(ftypBox('mif1', ['miaf', 'heic']))).toBe(true);
    });

    it('does not treat AVIF as HEIC (sharp decodes AVIF natively)', () => {
        expect(isHeic(ftypBox('avif', ['mif1', 'miaf']))).toBe(false);
    });

    it('returns false for non-ISO-BMFF data (e.g. a PNG header)', () => {
        expect(isHeic(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))).toBe(false);
    });

    it('returns false for a truncated buffer', () => {
        expect(isHeic(Buffer.from('ftyp'))).toBe(false);
    });
});

describe('Health Check Endpoint', () => {
    it('returns 200 status with health details', async () => {
        const response = await request(app).get('/health');
        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'healthy');
    });
});

describe('POST / GCS Trigger Execution', () => {
    beforeEach(() => {
        jest.clearAllMocks();

        jest.spyOn(secrets, 'getSecret').mockReturnValue('test-api-key');

        mockDownload.mockResolvedValue([Buffer.from('fake-original-image')]);
        mockSave.mockResolvedValue({});

        mockGetMetadata.mockResolvedValue([{
            metadata: {
                media_model: 'trip_media',
                media_id: '104',
                output_prefix: 'output/trip_media/104_uuid/',
                file_path: 'trip/original-image.png'
            }
        }]);
    });

    it('successfully downloads image, resizes, and publishes to Pub/Sub', async () => {
        const payload = {
            bucket: 'subterra-test-bucket',
            name: 'input/some-uuid/original-image.png',
        };

        const response = await request(app)
            .post('/')
            .set('Authorization', 'Bearer test-api-key')
            .send(payload);

        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'success');

        expect(mockGetMetadata).toHaveBeenCalled();
        expect(mockDownload).toHaveBeenCalled();
        expect(mockSave).toHaveBeenCalledTimes(3); // desktop, tablet, mobile presets

        expect(mockTopic).toHaveBeenCalledWith('media-notifications');
        expect(mockPublishMessage).toHaveBeenCalledWith(
            expect.objectContaining({
                data: expect.any(Buffer)
            })
        );

        // Verify published data shape
        const publishCall = mockPublishMessage.mock.calls[0][0];
        const publishedData = JSON.parse(publishCall.data.toString());
        expect(publishedData).toMatchObject({
            status: 'succeeded',
            mediaModel: 'trip_media',
            mediaId: 104,
            sourcePath: 'input/some-uuid/original-image.png'
        });
    });

    it('handles image processing failures and publishes failure status to Pub/Sub', async () => {
        mockDownload.mockRejectedValueOnce(new Error('GCS Download Failure'));

        const payload = {
            bucket: 'subterra-test-bucket',
            name: 'input/some-uuid/original-image.png',
        };

        const response = await request(app)
            .post('/')
            .set('Authorization', 'Bearer test-api-key')
            .send(payload);

        expect(response.status).toBe(200); // Catches and acknowledges to ignored failure delivery loop
        expect(response.body).toHaveProperty('status', 'ignored');

        expect(mockTopic).toHaveBeenCalledWith('media-notifications');
        expect(mockPublishMessage).toHaveBeenCalled();


        const publishCall = mockPublishMessage.mock.calls[0][0];
        const publishedData = JSON.parse(publishCall.data.toString());
        expect(publishedData).toMatchObject({
            status: 'failed',
            mediaModel: 'trip_media',
            mediaId: 104,
            error: 'GCS Download Failure'
        });
    });

    it('decodes HEVC-coded HEIC sources via heic-convert before resizing', async () => {
        // A minimal ISO-BMFF ftyp box with the HEVC `heic` major brand.
        const ftyp = Buffer.alloc(16);
        ftyp.writeUInt32BE(16, 0);
        ftyp.write('ftyp', 4, 'latin1');
        ftyp.write('heic', 8, 'latin1');
        mockDownload.mockResolvedValueOnce([ftyp]);

        const payload = {
            bucket: 'subterra-test-bucket',
            name: 'input/some-uuid/IMG_1234.heic',
        };

        const response = await request(app)
            .post('/')
            .set('Authorization', 'Bearer test-api-key')
            .send(payload);

        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'success');

        // The HEIC must be decoded once, then the 3 webp variants still produced.
        expect(convert).toHaveBeenCalledTimes(1);
        expect(convert).toHaveBeenCalledWith(
            expect.objectContaining({ buffer: ftyp, format: 'JPEG' })
        );
        expect(mockSave).toHaveBeenCalledTimes(3);
    });

    it('does not invoke heic-convert for non-HEIC sources', async () => {
        // beforeEach default download buffer is plain bytes, not an ftyp box.
        const payload = {
            bucket: 'subterra-test-bucket',
            name: 'input/some-uuid/original-image.png',
        };

        await request(app)
            .post('/')
            .set('Authorization', 'Bearer test-api-key')
            .send(payload);

        expect(convert).not.toHaveBeenCalled();
        expect(mockSave).toHaveBeenCalledTimes(3);
    });

    it('ignores non-input paths immediately', async () => {
        const payload = {
            bucket: 'subterra-test-bucket',
            name: 'some-other-folder/original-image.png',
        };

        const response = await request(app)
            .post('/')
            .set('Authorization', 'Bearer test-api-key')
            .send(payload);

        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'ignored');
        
        expect(mockGetMetadata).not.toHaveBeenCalled();
    });

    /**
     * Regression test: processing multiple images concurrently must not trigger
     * the MaxListenersExceededWarning on a shared PassThrough stream.
     *
     * Root cause: the `teeny-request` override to ^10.x caused @google-cloud/storage
     * to use an incompatible stream implementation. teeny-request 10.x wraps the
     * node-fetch response body in a PassThrough, and the storage library then set up
     * a second pipeline from that same stream object, adding duplicate error/close
     * listeners. Under concurrent load the 10-listener limit was exceeded.
     *
     * Fix: removed the `overrides: { teeny-request: ^10.1.2 }` from package.json so
     * that @google-cloud/storage resolves to its intended teeny-request@^9.x.
     */
    it('processes multiple concurrent image requests without MaxListeners warnings', async () => {
        const warnings: string[] = [];
        const warningListener = (warning: Error) => {
            if (warning.name === 'MaxListenersExceededWarning') {
                warnings.push(warning.message);
            }
        };
        process.on('warning', warningListener);

        const payload = {
            bucket: 'subterra-test-bucket',
            name: 'input/some-uuid/original-image.png',
        };

        // Fire 6 concurrent requests to push well past the default 10-listener limit
        // if the stream-sharing bug were present (6 × 3 upload streams = 18 potential listeners).
        const responses = await Promise.all(
            Array.from({ length: 6 }, () =>
                request(app).post('/').set('Authorization', 'Bearer test-api-key').send(payload)
            )
        );

        process.off('warning', warningListener);

        for (const response of responses) {
            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('status', 'success');
        }

        expect(warnings).toHaveLength(0);
    });
});

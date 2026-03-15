import request from 'supertest';
import app from '../src/index';
import { Storage } from '@google-cloud/storage';
import axios from 'axios';
import sharp from 'sharp';

const mockDownload = jest.fn();
const mockSave = jest.fn();

jest.mock('@google-cloud/storage', () => {
    return {
        Storage: jest.fn(() => ({
            bucket: jest.fn(() => ({
                file: jest.fn(() => ({
                    download: mockDownload,
                    save: mockSave
                }))
            }))
        }))
    };
});

jest.mock('axios');

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

jest.mock('../src/secrets', () => ({
    getSecret: jest.fn((key: string) => {
        if (key === 'IMAGE_PROCESSOR_API_KEY') return 'test-api-key';
        if (key === 'CALLBACK_SECRET') return 'test-callback-secret';
        return undefined;
    }),
}));

describe('Health Check Endpoint', () => {
    it('returns 200 status with health details', async () => {
        const response = await request(app).get('/health');
        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'healthy');
    });
});

describe('POST /process Authentication', () => {
    it('returns 401 without API Key header', async () => {
        const response = await request(app).post('/process').send({});
        expect(response.status).toBe(401);
    });

    it('returns 401 with invalid API Key', async () => {
        const response = await request(app)
            .post('/process')
            .set('Authorization', 'Bearer wrong-key')
            .send({});
        expect(response.status).toBe(401);
    });

    it('returns 400 when authenticated but missing required fields', async () => {
        const response = await request(app)
            .post('/process')
            .set('Authorization', 'Bearer test-api-key')
            .send({});
        expect(response.status).toBe(400);
    });
});

describe('POST /process Pipeline Execution', () => {
    beforeEach(() => {
        jest.clearAllMocks();

        mockDownload.mockResolvedValue([Buffer.from('fake-original-image')]);
        mockSave.mockResolvedValue({});

        (axios.post as jest.Mock).mockResolvedValue({ status: 200 });
    });

    it('successfully downloads image, resizes, and calls webhook', async () => {
        const payload = {
            bucket: 'subterra-test-bucket',
            path: 'input/original-image.png',
            callbackUrl: 'https://subterra.test/api/webhooks/gcp/image-processor',
            mediaModel: 'trip_media',
            mediaId: 104,
            outputPrefix: 'output/trip_media/104_uuid/',
        };

        const response = await request(app)
            .post('/process')
            .set('Authorization', 'Bearer test-api-key')
            .send(payload);

        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'success');

        expect(mockDownload).toHaveBeenCalled();
        expect(mockSave).toHaveBeenCalledTimes(3); // desktop, tablet, mobile presets

        expect(axios.post).toHaveBeenCalledWith(
            'https://subterra.test/api/webhooks/gcp/image-processor',
            expect.objectContaining({
                status: 'succeeded',
                mediaModel: 'trip_media',
                mediaId: 104,
                variants: expect.arrayContaining([
                    expect.objectContaining({ name: 'desktop' }),
                    expect.objectContaining({ name: 'tablet' }),
                    expect.objectContaining({ name: 'mobile' }),
                ]),
            }),
            expect.any(Object)
        );
    });

    it('handles image processing failures and fires failure callback', async () => {
        mockDownload.mockRejectedValueOnce(new Error('GCS Download Failure'));

        const payload = {
            bucket: 'subterra-test-bucket',
            path: 'input/original-image.png',
            callbackUrl: 'https://subterra.test/api/webhooks/gcp/image-processor',
            mediaModel: 'trip_media',
            mediaId: 104,
            outputPrefix: 'output/trip_media/104_uuid/',
        };

        const response = await request(app)
            .post('/process')
            .set('Authorization', 'Bearer test-api-key')
            .send(payload);

        expect(response.status).toBe(500);

        expect(axios.post).toHaveBeenCalledWith(
            'https://subterra.test/api/webhooks/gcp/image-processor',
            expect.objectContaining({
                status: 'failed',
                mediaModel: 'trip_media',
                mediaId: 104,
                error: 'GCS Download Failure',
            })
        );
    });
});

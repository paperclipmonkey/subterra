import request from 'supertest';
import app from '../src/index';
import { Storage } from '@google-cloud/storage';
import sharp from 'sharp';

const mockDownload = jest.fn();
const mockSave = jest.fn();
const mockGetMetadata = jest.fn();
const mockPublishMessage = jest.fn().mockResolvedValue('msg-id');

jest.mock('@google-cloud/storage', () => {
    return {
        Storage: jest.fn(() => ({
            bucket: jest.fn(() => ({
                file: jest.fn(() => ({
                    download: mockDownload,
                    save: mockSave,
                    getMetadata: mockGetMetadata
                }))
            }))
        }))
    };
});

jest.mock('@google-cloud/pubsub', () => {
    return {
        PubSub: jest.fn(() => ({
            topic: jest.fn(() => ({
                publishMessage: mockPublishMessage
            }))
        }))
    };
});

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
            .send(payload);

        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'success');

        expect(mockGetMetadata).toHaveBeenCalled();
        expect(mockDownload).toHaveBeenCalled();
        expect(mockSave).toHaveBeenCalledTimes(3); // desktop, tablet, mobile presets

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
            .send(payload);

        expect(response.status).toBe(200); // Catches and acknowledges to ignored failure delivery loop
        expect(response.body).toHaveProperty('status', 'ignored');

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

    it('ignores non-input paths immediately', async () => {
        const payload = {
            bucket: 'subterra-test-bucket',
            name: 'some-other-folder/original-image.png',
        };

        const response = await request(app)
            .post('/')
            .send(payload);

        expect(response.status).toBe(200);
        expect(response.body).toHaveProperty('status', 'ignored');
        
        expect(mockGetMetadata).not.toHaveBeenCalled();
    });
});

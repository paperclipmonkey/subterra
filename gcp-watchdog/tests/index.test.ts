/**
 * Unit tests for the main Express application.
 */
import request from 'supertest';
import app from '../src/index';

// Mock the clients
jest.mock('../src/firestore-client');
jest.mock('../src/textmagic-client');
jest.mock('../src/smtp-client');

import { FirestoreClient } from '../src/firestore-client';
import { TextMagicClient } from '../src/textmagic-client';
import { SMTPClient } from '../src/smtp-client';

describe('Watchdog API', () => {
    let mockFirestore: jest.Mocked<FirestoreClient>;
    let mockSms: jest.Mocked<TextMagicClient>;
    let mockEmail: jest.Mocked<SMTPClient>;

    beforeEach(() => {
        mockFirestore = new FirestoreClient() as jest.Mocked<FirestoreClient>;
        mockSms = new TextMagicClient() as jest.Mocked<TextMagicClient>;
        mockEmail = new SMTPClient() as jest.Mocked<SMTPClient>;
    });

    describe('GET /health', () => {
        it('should return healthy status', async () => {
            const response = await request(app).get('/health');

            expect(response.status).toBe(200);
            expect(response.body.status).toBe('healthy');
            expect(response.body.timestamp).toBeDefined();
        });
    });

    describe('POST /watchdog', () => {
        it('should register a watchdog successfully', async () => {
            const payload = {
                callout_id: 'test123',
                callout_time: '2026-01-30T10:00:00Z',
                user: { name: 'John Doe', phone: '+1234567890' },
            };

            mockFirestore.createWatchdog = jest.fn().mockResolvedValue(undefined);

            const response = await request(app)
                .post('/watchdog')
                .send(payload);

            expect(response.status).toBe(200);
            expect(response.body.message).toBe('Watchdog registered successfully');
            expect(response.body.callout_id).toBe('test123');
        });

        it('should return 400 if missing required fields', async () => {
            const response = await request(app)
                .post('/watchdog')
                .send({});

            expect(response.status).toBe(400);
            expect(response.body.error).toContain('Missing required fields');
        });
    });

    describe('DELETE /watchdog', () => {
        it('should cancel a watchdog successfully', async () => {
            mockFirestore.deleteWatchdog = jest.fn().mockResolvedValue(undefined);

            const response = await request(app)
                .delete('/watchdog?callout_id=test123');

            expect(response.status).toBe(200);
            expect(response.body.message).toBe('Watchdog cancelled successfully');
            expect(response.body.callout_id).toBe('test123');
        });

        it('should return 400 if missing callout_id', async () => {
            const response = await request(app).delete('/watchdog');

            expect(response.status).toBe(400);
            expect(response.body.error).toContain('Missing callout_id');
        });
    });

    describe('POST /check', () => {
        it('should return no overdue callouts when none exist', async () => {
            mockFirestore.getOverdueCallouts = jest.fn().mockResolvedValue([]);

            const response = await request(app).post('/check');

            expect(response.status).toBe(200);
            expect(response.body.message).toBe('No overdue callouts');
        });

        it('should process and alert overdue callouts', async () => {
            const mockCallout = {
                callout_id: 'test123',
                callout_time: { toDate: () => new Date('2026-01-30T08:00:00Z') },
                user: { name: 'John Doe', phone: '+1234567890', email: 'john@example.com' },
                participants: [],
                trip_plan: 'Test trip',
                cave_name: 'Test Cave',
            };

            mockFirestore.getOverdueCallouts = jest.fn().mockResolvedValue([mockCallout]);
            mockSms.sendSms = jest.fn().mockResolvedValue(true);
            mockEmail.sendAlertEmail = jest.fn().mockResolvedValue(true);
            mockFirestore.markAsAlerted = jest.fn().mockResolvedValue(undefined);

            const response = await request(app).post('/check');

            expect(response.status).toBe(200);
            expect(response.body.message).toContain('Processed 1 overdue callout');
            expect(response.body.alerts_sent).toHaveLength(1);
        });
    });
});

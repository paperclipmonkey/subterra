/**
 * Unit tests for the main Express application.
 */
import request from 'supertest';
import app, { setClients } from '../src/index';

// Mock the clients
jest.mock('../src/firestore-client');
jest.mock('../src/textmagic-client');
jest.mock('../src/smtp-client');

import { FirestoreClient } from '../src/firestore-client';
import { TextMagicClient } from '../src/textmagic-client';
import { SMTPClient } from '../src/smtp-client';
import * as secrets from '../src/secrets';

describe('Watchdog API', () => {
    let mockFirestore: jest.Mocked<FirestoreClient>;
    let mockSms: jest.Mocked<TextMagicClient>;
    let mockEmail: jest.Mocked<SMTPClient>;

    beforeEach(() => {
        mockFirestore = new FirestoreClient() as jest.Mocked<FirestoreClient>;
        mockSms = new TextMagicClient() as jest.Mocked<TextMagicClient>;
        mockEmail = new SMTPClient() as jest.Mocked<SMTPClient>;

        // Inject mocks
        setClients(mockFirestore, mockSms, mockEmail);

        secrets.resetSecrets();
        jest.spyOn(secrets, 'getSecret').mockReturnValue('test-api-key');
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('GET /health', () => {
        it('should return healthy status', async () => {
            const response = await request(app).get('/health');

            expect(response.status).toBe(200);
            expect(response.body.status).toBe('healthy');
            expect(response.body.timestamp).toBeDefined();
        });
    });

    describe('Authentication', () => {
        it('should return 401 if API key is missing', async () => {
            const response = await request(app).post('/watchdog');
            expect(response.status).toBe(401);
            expect(response.body.error).toContain('Unauthorized');
        });

        it('should return 401 if API key is incorrect', async () => {
            const response = await request(app)
                .post('/watchdog')
                .set('X-Watchdog-Key', 'wrong-key');
            expect(response.status).toBe(401);
            expect(response.body.error).toContain('Unauthorized');
        });

        it('should bypass auth if WATCHDOG_API_KEY is not configured', async () => {
            jest.spyOn(secrets, 'getSecret').mockReturnValue('');
            const response = await request(app).post('/watchdog').send({
                callout_id: 'test123',
                callout_time: '2026-01-30T10:00:00Z',
            });
            // Should get to 200 or 400 (if body bad), but not 401
            expect(response.status).not.toBe(401);
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
                .set('X-Watchdog-Key', 'test-api-key')
                .send(payload);

            expect(response.status).toBe(200);
            expect(response.body.message).toBe('Watchdog registered successfully');
            expect(response.body.callout_id).toBe('test123');
        });

        it('should return 400 if missing required fields', async () => {
            const response = await request(app)
                .post('/watchdog')
                .set('X-Watchdog-Key', 'test-api-key')
                .send({});

            expect(response.status).toBe(400);
            expect(response.body.error).toContain('Missing required fields');
        });
    });

    describe('DELETE /watchdog', () => {
        it('should cancel a watchdog successfully', async () => {
            mockFirestore.deleteWatchdog = jest.fn().mockResolvedValue(undefined);

            const response = await request(app)
                .delete('/watchdog?callout_id=test123')
                .set('X-Watchdog-Key', 'test-api-key');

            expect(response.status).toBe(200);
            expect(response.body.message).toBe('Watchdog cancelled successfully');
            expect(response.body.callout_id).toBe('test123');
        });

        it('should return 400 if missing callout_id', async () => {
            const response = await request(app)
                .delete('/watchdog')
                .set('X-Watchdog-Key', 'test-api-key');

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

    describe('GET /watchdog', () => {
        it('should list active watchdogs', async () => {
            const mockWatchdogs = [
                { callout_id: 'test1', status: 'active' },
                { callout_id: 'test2', status: 'active' }
            ];
            mockFirestore.listActiveWatchdogs = jest.fn().mockResolvedValue(mockWatchdogs);

            const response = await request(app)
                .get('/watchdog')
                .set('X-Watchdog-Key', 'test-api-key');

            expect(response.status).toBe(200);
            expect(response.body.count).toBe(2);
            expect(response.body.data).toHaveLength(2);
        });
    });

    describe('POST /watchdog/test', () => {
        it('should trigger test alerts', async () => {
            const payload = {
                user: { name: 'Test User', phone: '+1234567890', email: 'test@example.com' },
                cave_name: 'Test Cave'
            };

            mockSms.sendSms = jest.fn().mockResolvedValue(true);
            mockEmail.sendAlertEmail = jest.fn().mockResolvedValue(true);

            const response = await request(app)
                .post('/watchdog/test')
                .set('X-Watchdog-Key', 'test-api-key')
                .send(payload);

            expect(response.status).toBe(200);
            expect(response.body.message).toBe('Test alerts triggered successfully');
            expect(response.body.sms_sent).toBeDefined();
            expect(response.body.emails_sent).toBeDefined();
        });

        it('should return 400 if missing contact info', async () => {
            const response = await request(app)
                .post('/watchdog/test')
                .set('X-Watchdog-Key', 'test-api-key')
                .send({ user: { name: 'No Contact info' } });

            expect(response.status).toBe(400);
            expect(response.body.error).toContain('Missing required fields for test');
        });
    });
});

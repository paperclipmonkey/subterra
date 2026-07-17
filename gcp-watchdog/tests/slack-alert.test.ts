/**
 * Unit tests for the dedicated backup-watchdog Slack alert.
 */
import axios from 'axios';
import { buildOverdueSlackText, sendOverdueSlackAlert } from '../src/slack-alert';
import * as secrets from '../src/secrets';

jest.mock('axios');
const mockedAxios = axios as jest.Mocked<typeof axios>;

describe('sendOverdueSlackAlert', () => {
    const originalEnv = process.env.NODE_ENV;

    afterEach(() => {
        process.env.NODE_ENV = originalEnv;
        jest.restoreAllMocks();
        mockedAxios.post.mockClear();
    });

    it('posts a structured @channel alert with callout details', async () => {
        process.env.NODE_ENV = 'production';
        jest.spyOn(secrets, 'getSecret').mockImplementation((key) =>
            key === 'SLACK_CALLOUTS_WEBHOOK_URL' ? 'https://hooks.slack.com/services/callouts' : ''
        );
        mockedAxios.post.mockResolvedValue({ status: 200, data: 'ok' } as any);

        await sendOverdueSlackAlert([
            { callout_id: 'abc123', cave_name: 'Swildons Hole', user: { name: 'Jane' } },
        ]);

        expect(mockedAxios.post).toHaveBeenCalledTimes(1);
        const [url, body] = mockedAxios.post.mock.calls[0] as [string, { text: string }];
        expect(url).toBe('https://hooks.slack.com/services/callouts');
        expect(body.text).toContain('<!channel>');
        expect(body.text).toContain('1 OVERDUE');
        expect(body.text).toContain('Swildons Hole');
        expect(body.text).toContain('Jane');
        expect(body.text).toContain('abc123');
    });

    it('falls back to the general webhook when no dedicated callouts webhook is set', async () => {
        process.env.NODE_ENV = 'production';
        jest.spyOn(secrets, 'getSecret').mockImplementation((key) =>
            key === 'SLACK_WEBHOOK_URL' ? 'https://hooks.slack.com/services/fallback' : ''
        );
        mockedAxios.post.mockResolvedValue({ status: 200 } as any);

        await sendOverdueSlackAlert([{ callout_id: 'x', cave_name: 'Cave', user: {} }]);

        expect(mockedAxios.post).toHaveBeenCalledTimes(1);
        expect((mockedAxios.post.mock.calls[0] as any[])[0]).toBe('https://hooks.slack.com/services/fallback');
    });

    it('does nothing in the test environment', async () => {
        process.env.NODE_ENV = 'test';
        jest.spyOn(secrets, 'getSecret').mockReturnValue('https://hooks.slack.com/services/x');

        await sendOverdueSlackAlert([{ callout_id: 'x', cave_name: 'Cave', user: {} }]);

        expect(mockedAxios.post).not.toHaveBeenCalled();
    });

    it('does nothing when no webhook is configured', async () => {
        process.env.NODE_ENV = 'production';
        jest.spyOn(secrets, 'getSecret').mockReturnValue('');

        await sendOverdueSlackAlert([{ callout_id: 'x', cave_name: 'Cave', user: {} }]);

        expect(mockedAxios.post).not.toHaveBeenCalled();
    });

    it('never throws when the Slack post fails', async () => {
        process.env.NODE_ENV = 'production';
        jest.spyOn(secrets, 'getSecret').mockReturnValue('https://hooks.slack.com/services/x');
        mockedAxios.post.mockRejectedValue(new Error('slack down'));
        jest.spyOn(process.stdout, 'write').mockImplementation(() => true);

        await expect(
            sendOverdueSlackAlert([{ callout_id: 'x', cave_name: 'Cave', user: {} }])
        ).resolves.toBeUndefined();
    });
});

describe('buildOverdueSlackText', () => {
    const realCallout = { callout_id: 'abc-123', cave_name: 'Deep Cave', user: { name: 'John Doe' } };
    const testCallout = {
        callout_id: 'TEST-2026-07-01-120032',
        cave_name: '🧪 Test System Check',
        user: { name: '🧪 Monthly Test Alert' },
    };

    it('reports TEST- callouts as a scheduled test without @channel', () => {
        const text = buildOverdueSlackText([testCallout]);

        expect(text).not.toContain('<!channel>');
        expect(text).not.toContain('OVERDUE CALLOUT(S)');
        expect(text).toContain('BACKUP WATCHDOG MONTHLY TEST');
        expect(text).toContain('No action is required');
        expect(text).toContain('(ID: TEST-2026-07-01-120032)');
    });

    it('keeps the @channel emergency section when real and test callouts overlap', () => {
        const text = buildOverdueSlackText([testCallout, realCallout]);

        expect(text).toContain('<!channel>');
        // The test callout must not inflate the emergency count.
        expect(text).toContain('BACKUP WATCHDOG: 1 OVERDUE CALLOUT(S)');
        expect(text).toContain('*Deep Cave* — John Doe (ID: abc-123)');
        expect(text).toContain('BACKUP WATCHDOG MONTHLY TEST');
    });
});

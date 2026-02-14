/**
 * Unit tests for secrets loading logic.
 */
import { loadSecrets, getSecret, resetSecrets } from '../src/secrets';

describe('Secrets loading', () => {
    const originalEnv = process.env;

    beforeEach(() => {
        jest.resetModules();
        process.env = { ...originalEnv };
        resetSecrets();
    });

    afterAll(() => {
        process.env = originalEnv;
    });

    it('should load secrets from environment variables', () => {
        process.env.WATCHDOG_API_KEY = 'env-api-key';
        process.env.TEXTMAGIC_USERNAME = 'env-user';
        process.env.TEXTMAGIC_API_KEY = '';
        process.env.SMTP_USERNAME = '';
        process.env.SMTP_PASSWORD = '';

        const secrets = loadSecrets();

        expect(secrets.WATCHDOG_API_KEY).toBe('env-api-key');
        expect(secrets.TEXTMAGIC_USERNAME).toBe('env-user');
    });

    it('should get a specific secret', () => {
        process.env.WATCHDOG_API_KEY = 'specific-key';

        const secret = getSecret('WATCHDOG_API_KEY');

        expect(secret).toBe('specific-key');
    });

    it('should return empty string if secret is missing', () => {
        // Clear all relevant env vars
        delete process.env.WATCHDOG_API_KEY;
        delete process.env.TEXTMAGIC_USERNAME;
        delete process.env.TEXTMAGIC_API_KEY;
        delete process.env.SMTP_USERNAME;
        delete process.env.SMTP_PASSWORD;

        const secret = getSecret('WATCHDOG_API_KEY');
        expect(secret).toBe('');
    });
});

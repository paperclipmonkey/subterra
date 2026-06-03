import * as fs from 'fs';
import * as path from 'path';

interface SecretsConfig {
    TEXTMAGIC_USERNAME: string;
    TEXTMAGIC_API_KEY: string;
    SMTP_USERNAME: string;
    SMTP_PASSWORD: string;
    WATCHDOG_API_KEY: string;
    SLACK_WEBHOOK_URL: string;
    // Optional: dedicated webhook for structured callout alerts (e.g. #callouts-overdue).
    // Falls back to SLACK_WEBHOOK_URL when unset.
    SLACK_CALLOUTS_WEBHOOK_URL: string;
}

let cachedSecrets: SecretsConfig | null = null;

/**
 * Load secrets from either the mounted JSON file (in Cloud Run) or environment variables (locally)
 */
export function loadSecrets(): SecretsConfig {
    if (cachedSecrets) {
        return cachedSecrets;
    }

    const secretsPath = '/secrets/config.json';

    // Try to load from mounted secret file first (Cloud Run)
    if (fs.existsSync(secretsPath)) {
        try {
            const secretsData = fs.readFileSync(secretsPath, 'utf8');
            cachedSecrets = JSON.parse(secretsData) as SecretsConfig;
            console.log('Loaded secrets from mounted file');
            return cachedSecrets;
        } catch (error) {
            console.error('Failed to load secrets from file:', error);
            throw new Error('Failed to load secrets configuration');
        }
    }

    // Fallback to environment variables (for local development)
    // Ensure all properties are explicitly assigned, even if empty, to guarantee a non-null SecretsConfig object.
    cachedSecrets = {
        TEXTMAGIC_USERNAME: process.env.TEXTMAGIC_USERNAME || '',
        TEXTMAGIC_API_KEY: process.env.TEXTMAGIC_API_KEY || '',
        SMTP_USERNAME: process.env.SMTP_USERNAME || '',
        SMTP_PASSWORD: process.env.SMTP_PASSWORD || '',
        WATCHDOG_API_KEY: process.env.WATCHDOG_API_KEY || '',
        SLACK_WEBHOOK_URL: process.env.SLACK_WEBHOOK_URL || '',
        SLACK_CALLOUTS_WEBHOOK_URL: process.env.SLACK_CALLOUTS_WEBHOOK_URL || '',
    };

    console.log('Loaded secrets from environment variables');
    return cachedSecrets;
}

export function getSecret(key: keyof SecretsConfig): string {
    const secrets = loadSecrets();
    return secrets[key] || '';
}

export function resetSecrets(): void {
    cachedSecrets = null;
}

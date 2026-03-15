import * as fs from 'fs';

interface SecretsConfig {
    IMAGE_PROCESSOR_API_KEY: string;
    CALLBACK_SECRET: string;
    SLACK_WEBHOOK_URL: string;
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
    cachedSecrets = {
        IMAGE_PROCESSOR_API_KEY: process.env.IMAGE_PROCESSOR_API_KEY || '',
        CALLBACK_SECRET: process.env.CALLBACK_SECRET || '',
        SLACK_WEBHOOK_URL: process.env.SLACK_WEBHOOK_URL || '',
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

import axios from 'axios';
import { getSecret } from './secrets';

export function setupSlackLogger() {
    const originalConsoleWarn = console.warn;
    const originalConsoleError = console.error;

    console.warn = (...args: any[]) => {
        originalConsoleWarn.apply(console, args as any);
        sendLogToSlack('warn', args);
    };

    console.error = (...args: any[]) => {
        originalConsoleError.apply(console, args as any);
        sendLogToSlack('error', args);
    };
}

async function sendLogToSlack(level: 'warn' | 'error', args: any[]) {
    // Only send if URL is configured and we're not running tests
    const webhookUrl = getSecret('SLACK_WEBHOOK_URL');
    if (!webhookUrl || process.env.NODE_ENV === 'test') return;

    try {
        const emoji = level === 'error' ? '🚨' : '⚠️';
        let mainMessage = 'Log event';
        let extraArgs = [...args];

        if (args.length > 0) {
            if (typeof args[0] === 'string') {
                mainMessage = args[0];
                extraArgs = args.slice(1);
            } else if (args[0] instanceof Error) {
                mainMessage = args[0].message;
            }
        }

        let attachments = '';
        if (extraArgs.length > 0) {
            attachments = extraArgs.map(arg => {
                if (arg instanceof Error) {
                    return arg.stack || arg.message;
                } else if (typeof arg === 'object') {
                    // Prevent circular references in JSON serialization
                    try {
                        return JSON.stringify(arg, null, 2);
                    } catch (e) {
                        return String(arg);
                    }
                }
                return String(arg);
            }).join('\n\n');
        }

        let text = `${emoji} *GCP Image Processor [${level.toUpperCase()}]*\n${mainMessage}`;
        if (attachments) {
            text += `\n\`\`\`\n${attachments}\n\`\`\``;
        }

        await axios.post(webhookUrl, { text });
    } catch (e) {
        // Fallback to stdout to avoid recursive console.error calls
        process.stdout.write(`Failed to send log to Slack: ${(e as Error).message}\n`);
    }
}

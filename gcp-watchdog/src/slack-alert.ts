/**
 * Dedicated, structured Slack alerts for the backup watchdog.
 *
 * This is separate from slack-logger.ts: the logger forwards generic console.warn/error
 * lines, whereas this sends a deliberate, high-visibility callout alert (with @channel) so
 * the backup is as prominent in Slack as the primary Subterra alert.
 *
 * Prefers a dedicated callouts webhook (SLACK_CALLOUTS_WEBHOOK_URL, e.g. the same
 * #callouts-overdue channel the primary uses) and falls back to the general watchdog
 * webhook (SLACK_WEBHOOK_URL) so it still works if the dedicated one isn't configured.
 */
import axios from 'axios';
import { getSecret } from './secrets';

const isTestCallout = (c: any): boolean => String(c.callout_id || '').startsWith('TEST-');

const calloutLine = (c: any): string => {
    const cave = c.cave_name || 'Unknown location';
    const name = (c.user && c.user.name) || 'Unknown';
    return `• *${cave}* — ${name} (ID: ${c.callout_id})`;
};

/**
 * Build the Slack message text. Exported for unit testing.
 *
 * Real overdue callouts get the full-volume @channel alert. Monthly TEST- callouts
 * (from watchdog:test-alert) go overdue by design, so they are reported as a test —
 * without @channel — instead of looking like a live emergency.
 */
export function buildOverdueSlackText(callouts: any[]): string {
    const real = callouts.filter((c) => !isTestCallout(c));
    const tests = callouts.filter(isTestCallout);

    const sections: string[] = [];

    if (real.length > 0) {
        sections.push(
            `<!channel>\n🚨 *BACKUP WATCHDOG: ${real.length} OVERDUE CALLOUT(S)*\n` +
                `The independent GCP watchdog detected overdue callout(s) and is alerting all duty officers by SMS + email:\n` +
                real.map(calloutLine).join('\n')
        );
    }

    if (tests.length > 0) {
        sections.push(
            `🧪 *BACKUP WATCHDOG MONTHLY TEST: ${tests.length} test callout(s) went overdue as scheduled*\n` +
                `The watchdog is alerting the test contact by SMS + email to confirm the delivery path works. No action is required:\n` +
                tests.map(calloutLine).join('\n')
        );
    }

    return sections.join('\n\n');
}

export async function sendOverdueSlackAlert(callouts: any[]): Promise<void> {
    if (process.env.NODE_ENV === 'test') return;
    if (!callouts || callouts.length === 0) return;

    const webhookUrl = getSecret('SLACK_CALLOUTS_WEBHOOK_URL') || getSecret('SLACK_WEBHOOK_URL');
    if (!webhookUrl) return;

    try {
        await axios.post(webhookUrl, { text: buildOverdueSlackText(callouts) });
    } catch (e) {
        // A Slack failure must never affect the SMS/email alerting flow.
        process.stdout.write(`Failed to send overdue Slack alert: ${(e as Error).message}\n`);
    }
}

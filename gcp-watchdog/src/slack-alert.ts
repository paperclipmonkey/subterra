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

export async function sendOverdueSlackAlert(callouts: any[]): Promise<void> {
    if (process.env.NODE_ENV === 'test') return;
    if (!callouts || callouts.length === 0) return;

    const webhookUrl = getSecret('SLACK_CALLOUTS_WEBHOOK_URL') || getSecret('SLACK_WEBHOOK_URL');
    if (!webhookUrl) return;

    try {
        const lines = callouts.map((c) => {
            const cave = c.cave_name || 'Unknown location';
            const name = (c.user && c.user.name) || 'Unknown';
            return `• *${cave}* — ${name} (ID: ${c.callout_id})`;
        });

        const text =
            `<!channel>\n🚨 *BACKUP WATCHDOG: ${callouts.length} OVERDUE CALLOUT(S)*\n` +
            `The independent GCP watchdog detected overdue callout(s) and is alerting all duty officers by SMS + email:\n` +
            lines.join('\n');

        await axios.post(webhookUrl, { text });
    } catch (e) {
        // A Slack failure must never affect the SMS/email alerting flow.
        process.stdout.write(`Failed to send overdue Slack alert: ${(e as Error).message}\n`);
    }
}

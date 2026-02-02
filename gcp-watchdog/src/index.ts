/**
 * Subterra Watchdog Service - GCP Cloud Run Application
 * Monitors callouts and sends emergency alerts when users don't return on time.
 */
import express, { Request, Response } from 'express';
import { FirestoreClient, CalloutData } from './firestore-client';
import { TextMagicClient } from './textmagic-client';
import { SMTPClient } from './smtp-client';

const app = express();
app.use(express.json());

// Initialize clients
const firestoreClient = new FirestoreClient();
const smsClient = new TextMagicClient();
const emailClient = new SMTPClient();

// Health check endpoint
app.get('/health', (req: Request, res: Response) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
    });
});

// Register a new watchdog
app.post('/watchdog', async (req: Request, res: Response) => {
    try {
        const data: CalloutData = req.body;

        if (!data.callout_id || !data.callout_time) {
            return res.status(400).json({
                error: 'Missing required fields: callout_id, callout_time',
            });
        }

        // Store in Firestore
        await firestoreClient.createWatchdog(data.callout_id, data);

        console.log(`Watchdog registered: ${data.callout_id} for ${data.callout_time}`);

        res.json({
            message: 'Watchdog registered successfully',
            callout_id: data.callout_id,
            callout_time: data.callout_time,
        });
    } catch (error) {
        console.error('Error registering watchdog:', error);
        res.status(500).json({ error: (error as Error).message });
    }
});

// Cancel a watchdog
app.delete('/watchdog', async (req: Request, res: Response) => {
    try {
        const calloutId = req.query.callout_id as string;

        if (!calloutId) {
            return res.status(400).json({ error: 'Missing callout_id parameter' });
        }

        // Delete from Firestore
        await firestoreClient.deleteWatchdog(calloutId);

        console.log(`Watchdog cancelled: ${calloutId}`);

        res.json({
            message: 'Watchdog cancelled successfully',
            callout_id: calloutId,
        });
    } catch (error) {
        console.error('Error cancelling watchdog:', error);
        res.status(500).json({ error: (error as Error).message });
    }
});

// Check for overdue callouts (triggered by Cloud Scheduler)
app.post('/check', async (req: Request, res: Response) => {
    try {
        // Query for overdue callouts
        const overdueCallouts = await firestoreClient.getOverdueCallouts();

        if (overdueCallouts.length === 0) {
            console.log('No overdue callouts found');
            return res.json({
                message: 'No overdue callouts',
                checked_at: new Date().toISOString(),
            });
        }

        console.warn(`Found ${overdueCallouts.length} overdue callout(s)`);

        const alertsSent = [];

        for (const callout of overdueCallouts) {
            const calloutId = callout.callout_id;
            const user = callout.user || {};
            const participants = callout.participants || [];

            console.warn(`Processing overdue callout: ${calloutId}`);

            // Collect all phone numbers and emails to alert
            const phoneNumbers: string[] = [];
            const emails: string[] = [];

            // Add all participants
            for (const participant of participants) {
                if (participant.phone) phoneNumbers.push(participant.phone);
                if (participant.email) emails.push(participant.email);
            }

            // Create alert message
            const alertMessage = `🚨 SUBTERRA EMERGENCY: Callout Overdue

User: ${user.name || 'Unknown'}
Phone: ${user.phone || 'Unknown'}
Expected return: ${callout.callout_time.toDate().toISOString()}
Cave: ${callout.cave_name || 'Unknown'}

Please contact immediately. If unreachable, initiate emergency protocols.

Callout ID: ${calloutId}`;

            // Send SMS alerts
            const smsResults: Record<string, boolean> = {};
            for (const phone of phoneNumbers) {
                smsResults[phone] = await smsClient.sendSms(phone, alertMessage);
            }

            // Send email alerts
            const emailResults: Record<string, boolean> = {};
            for (const email of emails) {
                emailResults[email] = await emailClient.sendAlertEmail(email, callout);
            }

            // Mark as alerted in Firestore
            await firestoreClient.markAsAlerted(calloutId);

            alertsSent.push({
                callout_id: calloutId,
                sms_sent: smsResults,
                emails_sent: emailResults,
            });

            console.log(
                `Alerts sent for callout ${calloutId}: SMS=${JSON.stringify(smsResults)}, Email=${JSON.stringify(emailResults)}`
            );
        }

        res.json({
            message: `Processed ${overdueCallouts.length} overdue callout(s)`,
            alerts_sent: alertsSent,
            checked_at: new Date().toISOString(),
        });
    } catch (error) {
        console.error('Error checking overdue callouts:', error);
        res.status(500).json({ error: (error as Error).message });
    }
});

// Start server
const port = parseInt(process.env.PORT || '8080');
app.listen(port, () => {
    console.log(`Watchdog service listening on port ${port}`);
});

export default app;

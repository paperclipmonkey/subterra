/**
 * Subterra Watchdog Service - GCP Cloud Run Application
 * Monitors callouts and sends emergency alerts when users don't return on time.
 */
import express, { Request, Response } from 'express';
import { FirestoreClient, CalloutData } from './firestore-client';
import { TextMagicClient } from './textmagic-client';
import { SMTPClient } from './smtp-client';
import { getSecret } from './secrets';
import { setupSlackLogger } from './slack-logger';

// Setup Slack logging for console.warn and console.error
setupSlackLogger();

const app = express();
app.use(express.json());

// Initialize clients lazily to allow for better test mocking
let firestoreClient: FirestoreClient;
let smsClient: TextMagicClient;
let emailClient: SMTPClient;

const getFirestoreClient = () => {
    if (!firestoreClient) firestoreClient = new FirestoreClient();
    return firestoreClient;
};

const getSmsClient = () => {
    if (!smsClient) smsClient = new TextMagicClient();
    return smsClient;
};

const getEmailClient = () => {
    if (!emailClient) emailClient = new SMTPClient();
    return emailClient;
};

/**
 * For testing purposes: inject mock clients
 */
export const setClients = (
    firestore?: any,
    sms?: any,
    email?: any
) => {
    if (firestore) firestoreClient = firestore;
    if (sms) smsClient = sms;
    if (email) emailClient = email;
};

/**
 * Middleware to check API Key
 */
const checkApiKey = (req: Request, res: Response, next: () => void) => {
    const apiKey = getSecret('WATCHDOG_API_KEY');

    // Fail closed: if no key is configured, deny all access to prevent
    // accidental exposure of emergency alert endpoints in misconfigured environments.
    if (!apiKey) {
        console.error('WATCHDOG_API_KEY not configured. Refusing access.');
        return res.status(401).json({ error: 'Unauthorized: Service not configured' });
    }

    const providedKey = req.header('X-Watchdog-Key');

    if (providedKey !== apiKey) {
        console.warn(`Unauthorized access attempt from ${req.ip}`);
        return res.status(401).json({ error: 'Unauthorized: Invalid or missing API Key' });
    }

    next();
};

// Health check endpoint (public)
app.get('/health', (req: Request, res: Response) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
    });
});

// Protected routes (Creation, Deletion, Listing, Testing, and Scheduler check)
app.use(['/watchdog', '/check'], checkApiKey);

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
        await getFirestoreClient().createWatchdog(data.callout_id, data);

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
        await getFirestoreClient().deleteWatchdog(calloutId);

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

// List all active watchdogs
app.get('/watchdog', async (req: Request, res: Response) => {
    try {
        const activeWatchdogs = await getFirestoreClient().listActiveWatchdogs();
        res.json({
            count: activeWatchdogs.length,
            data: activeWatchdogs,
        });
    } catch (error) {
        console.error('Error listing watchdogs:', error);
        res.status(500).json({ error: (error as Error).message });
    }
});

// Trigger a test watchdog alert
app.post('/watchdog/test', async (req: Request, res: Response) => {
    try {
        const data: CalloutData = req.body;

        if (!data.user || (!data.user.phone && !data.user.email)) {
            return res.status(400).json({
                error: 'Missing required fields for test: user.phone or user.email',
            });
        }

        console.log(`Triggering TEST watchdog: ${data.callout_id || 'test-id'}`);

        const alertMessage = `🚨 SUBTERRA TEST ALERT: This is a test of the Subterra Watchdog system.
        
User: ${data.user.name || 'Test User'}
Cave: ${data.cave_name || 'Test Cave'}
        
Reply SAFE to acknowledge this test.`;

        const phoneNumbers = [];
        if (data.user.phone) phoneNumbers.push(data.user.phone);
        if (data.participants) {
            for (const p of data.participants) {
                if (p.phone) phoneNumbers.push(p.phone);
            }
        }

        const emails = [];
        if (data.user.email) emails.push(data.user.email);
        if (data.participants) {
            for (const p of data.participants) {
                if (p.email) emails.push(p.email);
            }
        }

        const smsResults: Record<string, boolean> = {};
        for (const phone of phoneNumbers) {
            smsResults[phone] = await getSmsClient().sendSms(phone, alertMessage);
        }

        const emailResults: Record<string, boolean> = {};
        const mockCalloutDoc = {
            callout_id: data.callout_id || 'test-id',
            callout_time: { toDate: () => new Date() },
            user: data.user,
            participants: data.participants || [],
            trip_plan: data.trip_plan || 'Test trip plan',
            cave_name: data.cave_name || 'Test Cave',
        } as any;

        for (const email of emails) {
            emailResults[email] = await getEmailClient().sendAlertEmail(email, mockCalloutDoc);
        }

        res.json({
            message: 'Test alerts triggered successfully',
            sms_sent: smsResults,
            emails_sent: emailResults,
        });
    } catch (error) {
        console.error('Error triggering test watchdog:', error);
        res.status(500).json({ error: (error as Error).message });
    }
});

// Check for overdue callouts (triggered by Cloud Scheduler)
app.post('/check', async (req: Request, res: Response) => {
    try {
        // Query for overdue callouts
        const overdueCallouts = await getFirestoreClient().getOverdueCallouts();

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
            const dutyOfficers = callout.duty_officers || [];

            console.warn(`Processing overdue callout: ${calloutId}`);

            // Collect all phone numbers and emails to alert
            const phoneNumbers: string[] = [];
            const emails: string[] = [];

            // Add all duty officers
            for (const dofficer of dutyOfficers) {
                if (dofficer.phone) phoneNumbers.push(dofficer.phone);
                if (dofficer.email) emails.push(dofficer.email);
            }

            // Create alert message
            const alertMessage = `🚨 SUBTERRA EMERGENCY: Callout Overdue

Initiator: ${user.name || 'Unknown'} (Ph: ${user.phone || 'Unknown'})
Expected return: ${callout.callout_time.toDate().toISOString()}
Cave: ${callout.cave_name || 'Unknown'}

This is a 15m overdue unacknowledged callout. Please contact the team immediately.

Callout ID: ${calloutId}`;

            // Send SMS alerts
            const smsResults: Record<string, boolean> = {};
            for (const phone of phoneNumbers) {
                smsResults[phone] = await getSmsClient().sendSms(phone, alertMessage);
            }

            // Send email alerts
            const emailResults: Record<string, boolean> = {};
            for (const email of emails) {
                emailResults[email] = await getEmailClient().sendAlertEmail(email, callout);
            }

            // Mark as alerted in Firestore
            await getFirestoreClient().markAsAlerted(calloutId);

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

// Start server only if not in test environment
if (process.env.NODE_ENV !== 'test') {
    const port = parseInt(process.env.PORT || '8080');
    app.listen(port, () => {
        console.log(`Watchdog service listening on port ${port}`);
    });
}

export default app;

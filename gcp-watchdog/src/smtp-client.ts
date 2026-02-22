/**
 * SMTP email client for sending emergency alerts.
 */
import nodemailer from 'nodemailer';
import type { Transporter } from 'nodemailer';
import type { CalloutDocument } from './firestore-client';
import { getSecret } from './secrets';

export class SMTPClient {
    private transporter: Transporter;
    private fromEmail: string;
    private fromName: string;

    constructor() {
        const server = process.env.SMTP_SERVER || '';
        const port = parseInt(process.env.SMTP_PORT || '587');
        const username = getSecret('SMTP_USERNAME');
        const password = getSecret('SMTP_PASSWORD');
        this.fromEmail = process.env.SMTP_FROM_EMAIL || '';
        this.fromName = process.env.SMTP_FROM_NAME || 'Subterra Watchdog';

        if (!server || !username || !password || !this.fromEmail) {
            console.warn('SMTP credentials not fully configured');
        }

        this.transporter = nodemailer.createTransport({
            host: server,
            port: port,
            secure: port === 465,
            auth: {
                user: username,
                pass: password,
            },
        });
    }

    async sendEmail(
        to: string,
        subject: string,
        text: string,
        html?: string
    ): Promise<boolean> {
        try {
            await this.transporter.sendMail({
                from: `"${this.fromName}" <${this.fromEmail}>`,
                to,
                subject,
                text,
                html: html || text,
            });

            console.log(`Email sent successfully to ${to}`);
            return true;
        } catch (error) {
            console.error(`Failed to send email to ${to}:`, error);
            return false;
        }
    }

    async sendAlertEmail(to: string, callout: CalloutDocument): Promise<boolean> {
        const user = callout.user || {};
        const calloutTime = callout.callout_time.toDate().toISOString();

        const subject = '🚨 SUBTERRA EMERGENCY: Callout Overdue';

        // Build readable participant list and their phones if provided
        let participantText = '';
        let participantHtml = '';
        if (callout.participants && callout.participants.length > 0) {
            callout.participants.forEach((p, idx) => {
                const phone = p.phone ? ` (${p.phone})` : '';
                participantText += `\nParticipant ${idx + 1}: ${p.name || 'Unknown'}${phone}`;
                participantHtml += `<li>${p.name || 'Unknown'} ${phone}</li>`;
            });
        } else {
            participantText = '\nNo additional participants listed.';
            participantHtml = '<p>No additional participants listed.</p>';
        }

        let locationText = '';
        let locationHtml = '';
        if (callout.location_data && callout.location_data.lat && callout.location_data.lng) {
            locationText = `\nGPS Coordinates logged at start: ${callout.location_data.lat}, ${callout.location_data.lng}`;
            locationHtml = `<p><strong>GPS Coordinates logged at start:</strong> <a href="https://maps.google.com/?q=${callout.location_data.lat},${callout.location_data.lng}">${callout.location_data.lat}, ${callout.location_data.lng}</a></p>`;
        }

        // Plain text version
        const text = `URGENT: Subterra Callout Overdue

This message was sent because a Subterra Callout has become 15 minutes overdue and the on-call Duty Officer has not acknowledged the event.

Callout ID: ${callout.callout_id || 'Unknown'}
Initiator: ${user.name || 'Unknown'} (${user.phone || 'No phone'})
Expected Return: ${calloutTime}
Cave: ${callout.cave_name || 'Unknown'}

Party Members: ${participantText}

Parking: ${callout.car_parking || 'Unknown'}
Vehicle Registration: ${callout.car_registration || 'Unknown'}
${locationText}

Trip Plan:
${callout.trip_plan || 'No trip plan provided'}

Team Details/Notes:
${callout.team_details || 'No additional team details.'}

This is an automated message from the Subterra Redundant Safety System.
Please contact the team immediately. If unreachable, initiate emergency protocols.`;

        // HTML version
        const html = `
<html>
<body style="font-family: Arial, sans-serif; color: #333;">
    <div style="background-color: #d32f2f; color: white; padding: 20px; border-radius: 5px;">
        <h1 style="margin: 0;">🚨 SUBTERRA EMERGENCY: Callout Overdue</h1>
    </div>
    <div style="padding: 20px; background-color: #f5f5f5; margin-top: 10px; border-radius: 5px;">
        <p style="color: #d32f2f; font-weight: bold;">This message was sent because a Subterra Callout has become 15 minutes overdue and the on-call Duty Officer has not acknowledged the event.</p>
        <p><strong>Callout ID:</strong> ${callout.callout_id || 'Unknown'}</p>
        <p><strong>Initiator:</strong> ${user.name || 'Unknown'} (${user.phone || 'No phone'})</p>
        <p><strong>Expected Return:</strong> ${calloutTime}</p>
        <p><strong>Cave:</strong> ${callout.cave_name || 'Unknown'}</p>
        
        <hr/>
        <h3>Party Members:</h3>
        ${participantHtml ? `<ul>${participantHtml}</ul>` : '<p>No additional participants listed.</p>'}
        
        <hr/>
        <h3>Vehicle & Location:</h3>
        <p><strong>Parking:</strong> ${callout.car_parking || 'Unknown'}</p>
        <p><strong>Registration:</strong> ${callout.car_registration || 'Unknown'}</p>
        ${locationHtml}

        <hr/>
        <h3>Trip Plan:</h3>
        <p>${callout.trip_plan || 'No trip plan provided'}</p>

        <hr/>
        <h3>Team Details/Notes:</h3>
        <p>${callout.team_details || 'No additional team details'}</p>

    </div>
    <div style="padding: 20px; margin-top: 10px; font-size: 12px; color: #666;">
        <p>This is an automated message from the Subterra Redundant Safety System.</p>
        <p><strong>Please contact the team immediately. If unreachable, initiate emergency protocols.</strong></p>
    </div>
</body>
</html>`;

        return this.sendEmail(to, subject, text, html);
    }
}

/**
 * Alert formatting: user text must be HTML-escaped and times shown in UK time.
 */
import nodemailer from 'nodemailer';
import { escapeHtml, formatUkTime } from '../src/format';
import { SMTPClient } from '../src/smtp-client';

jest.mock('nodemailer');

describe('escapeHtml', () => {
    it('escapes markup characters', () => {
        expect(escapeHtml(`<a href="x">'&'</a>`)).toBe('&lt;a href=&quot;x&quot;&gt;&#39;&amp;&#39;&lt;/a&gt;');
    });

    it('handles null and undefined', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
    });
});

describe('formatUkTime', () => {
    it('shows British Summer Time rather than UTC', () => {
        // 17:30 UTC on a July day is 18:30 in the UK.
        const formatted = formatUkTime(new Date('2026-07-04T17:30:00Z'));
        expect(formatted).toContain('18:30');
        expect(formatted).toContain('BST');
    });

    it('shows GMT in winter', () => {
        expect(formatUkTime(new Date('2026-01-10T17:30:00Z'))).toContain('17:30');
    });
});

describe('alert email', () => {
    it('escapes callout text and shows the UK return time', async () => {
        const sendMail = jest.fn().mockResolvedValue({});
        (nodemailer.createTransport as jest.Mock).mockReturnValue({ sendMail });

        const client = new SMTPClient();
        await client.sendAlertEmail('do@example.com', {
            callout_id: 'abc123',
            callout_time: { toDate: () => new Date('2026-07-04T17:30:00Z') },
            user: { name: 'Eve <!--', phone: '0700' },
            cave_name: 'Swildons',
            trip_plan: '<a href="https://evil.example">Click here</a>',
            participants: [{ name: '<b>Bob</b>', phone: '0711' }],
            location_data: { lat: 51.2, lng: -2.6 },
        } as never);

        const { html } = sendMail.mock.calls[0][0];
        expect(html).not.toContain('<a href="https://evil.example">');
        expect(html).toContain('&lt;a href=&quot;https://evil.example&quot;&gt;');
        expect(html).not.toContain('Eve <!--');
        expect(html).not.toContain('<b>Bob</b>');
        expect(html).toContain('18:30');
        // The GPS link survives intact.
        expect(html).toContain('https://maps.google.com/?q=51.2,-2.6');
    });
});

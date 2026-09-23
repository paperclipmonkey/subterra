/**
 * Escape user-supplied text for interpolation into the alert email's HTML.
 * Trip plans, names, parking notes etc. come straight from the callout creator;
 * unescaped, a stray `<!--` could hide the rest of the alert (GPS included) and
 * links/markup could be injected into an email sent to duty officers.
 */
export function escapeHtml(value: unknown): string {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Format a time for duty officers in UK local time. callout_time is stored in UTC;
 * printing toISOString() read an hour early during BST — on an emergency alert.
 */
export function formatUkTime(date: Date): string {
    const formatted = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Europe/London',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(date);

    return formatted;
}

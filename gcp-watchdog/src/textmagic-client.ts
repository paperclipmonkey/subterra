/**
 * TextMagic SMS client for sending emergency alerts.
 */
import axios, { AxiosError } from 'axios';

export class TextMagicClient {
    private username: string;
    private apiKey: string;
    private baseUrl = 'https://rest.textmagic.com/api/v2';

    constructor() {
        this.username = process.env.TEXTMAGIC_USERNAME || '';
        this.apiKey = process.env.TEXTMAGIC_API_KEY || '';

        if (!this.username || !this.apiKey) {
            console.warn('TextMagic credentials not configured');
        }
    }

    async sendSms(phone: string, message: string): Promise<boolean> {
        if (!this.username || !this.apiKey) {
            console.error('TextMagic credentials not configured');
            return false;
        }

        // Remove spaces and dashes
        const cleanPhone = phone.replace(/[\s-]/g, '');

        try {
            const response = await axios.post(
                `${this.baseUrl}/messages`,
                {
                    text: message,
                    phones: cleanPhone,
                },
                {
                    headers: {
                        'X-TM-Username': this.username,
                        'X-TM-Key': this.apiKey,
                        'Content-Type': 'application/json',
                    },
                    timeout: 10000,
                }
            );

            console.log(`SMS sent successfully to ${phone}:`, response.data);
            return true;
        } catch (error) {
            const axiosError = error as AxiosError;
            console.error(`Failed to send SMS to ${phone}:`, axiosError.message);
            return false;
        }
    }

    async sendBulkSms(phones: string[], message: string): Promise<Record<string, boolean>> {
        const results: Record<string, boolean> = {};

        for (const phone of phones) {
            results[phone] = await this.sendSms(phone, message);
        }

        return results;
    }
}

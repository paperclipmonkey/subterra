/**
 * Firestore client for managing watchdog callout records.
 */
import { Firestore, Timestamp } from '@google-cloud/firestore';

export interface CalloutData {
    callout_id: string;
    callout_time: string | Date;
    user: {
        name: string;
        phone?: string;
        email?: string;
    };
    participants?: Array<{
        name: string;
        phone?: string;
        email?: string;
    }>;
    trip_plan?: string;
    cave_name?: string;
}

export interface CalloutDocument {
    callout_id: string;
    status: string;
    callout_time: Timestamp;
    user: Record<string, any>;
    participants: Array<Record<string, any>>;
    trip_plan: string;
    cave_name: string;
    created_at: Timestamp;
}

export class FirestoreClient {
    private db: Firestore;
    private collection: FirebaseFirestore.CollectionReference;

    constructor() {
        const projectId = process.env.GCP_PROJECT_ID;
        this.db = new Firestore({ projectId });
        this.collection = this.db.collection('callouts');
    }

    async createWatchdog(calloutId: string, data: CalloutData): Promise<void> {
        const calloutTime = typeof data.callout_time === 'string'
            ? Timestamp.fromDate(new Date(data.callout_time))
            : Timestamp.fromDate(data.callout_time);

        const docData: Partial<CalloutDocument> = {
            callout_id: calloutId,
            status: 'active',
            callout_time: calloutTime,
            user: data.user || {},
            participants: data.participants || [],
            trip_plan: data.trip_plan || '',
            cave_name: data.cave_name || '',
            created_at: Timestamp.now(),
        };

        await this.collection.doc(calloutId).set(docData);
    }

    async getWatchdog(calloutId: string): Promise<CalloutDocument | null> {
        const doc = await this.collection.doc(calloutId).get();
        if (doc.exists) {
            return doc.data() as CalloutDocument;
        }
        return null;
    }

    async deleteWatchdog(calloutId: string): Promise<void> {
        await this.collection.doc(calloutId).delete();
    }

    async getOverdueCallouts(): Promise<CalloutDocument[]> {
        const now = Timestamp.now();

        const snapshot = await this.collection
            .where('status', '==', 'active')
            .where('callout_time', '<=', now)
            .get();

        const results: CalloutDocument[] = [];
        snapshot.forEach(doc => {
            results.push(doc.data() as CalloutDocument);
        });

        return results;
    }

    async markAsAlerted(calloutId: string): Promise<void> {
        await this.collection.doc(calloutId).update({
            status: 'alerted',
            alerted_at: Timestamp.now(),
        });
    }

    async listActiveWatchdogs(): Promise<CalloutDocument[]> {
        const snapshot = await this.collection
            .where('status', '==', 'active')
            .get();

        const results: CalloutDocument[] = [];
        snapshot.forEach(doc => {
            results.push(doc.data() as CalloutDocument);
        });

        return results;
    }
}

const { DynamoDBClient } = require("@aws-sdk/client-dynamodb");
const { DynamoDBDocumentClient, PutCommand, GetCommand, DeleteCommand } = require("@aws-sdk/lib-dynamodb");
const { SNSClient, PublishCommand } = require("@aws-sdk/client-sns");
const { SFNClient, StartExecutionCommand, StopExecutionCommand } = require("@aws-sdk/client-sfn");

const client = new DynamoDBClient({});
const docClient = DynamoDBDocumentClient.from(client);
const snsClient = new SNSClient({});
const sfnClient = new SFNClient({});

const TABLE_NAME = process.env.TABLE_NAME;
const SNS_TOPIC_ARN = process.env.SNS_TOPIC_ARN;
const STATE_MACHINE_ARN = process.env.STATE_MACHINE_ARN; // Needs to be passed in env vars

exports.handler = async (event) => {
    console.log("Event:", JSON.stringify(event));

    // Determine source of event efficiently
    // 1. HTTP API (Start/Cancel)
    if (event.routeKey) {
        return handleApiRequest(event);
    }
    // 2. Step Function (Check Status)
    if (event.action === 'check_status') {
        return handleCheckStatus(event);
    }

    return { statusCode: 400, body: "Unknown event source" };
};

async function handleApiRequest(event) {
    const method = event.requestContext.http.method;
    const path = event.requestContext.http.path;

    try {
        if (method === 'POST' && path === '/watchdog') {
            const body = JSON.parse(event.body);
            return await startWatchdog(body);
        }

        if (method === 'DELETE' && path === '/watchdog') {
            // Expecting trip_id in query string ?trip_id=...
            const trip_id = event.queryStringParameters?.trip_id;
            if (!trip_id) throw new Error("Missing trip_id");
            return await cancelWatchdog(trip_id);
        }
    } catch (error) {
        console.error("API Error:", error);
        return {
            statusCode: 500,
            body: JSON.stringify({ error: error.message })
        };
    }

    return { statusCode: 404, body: "Not Found" };
}

// --- Action: START ---
async function startWatchdog(data) {
    const { trip_id, expected_return_time, emergency_contact, user_info } = data;

    if (!trip_id || !expected_return_time) {
        throw new Error("Missing required fields");
    }

    // 1. Save to DynamoDB
    // Set TTL for 7 days after return time
    const returnTimeEpoch = new Date(expected_return_time).getTime() / 1000;
    const ttl = returnTimeEpoch + (7 * 24 * 60 * 60);

    await docClient.send(new PutCommand({
        TableName: TABLE_NAME,
        Item: {
            trip_id,
            status: 'PENDING',
            expected_return_time,
            emergency_contact,
            user_info,
            expires_at: ttl
        }
    }));

    // 2. Start Step Function
    // We use trip_id as the execution name for deduplication
    const sfnParams = {
        stateMachineArn: process.env.STATE_MACHINE_ARN,
        name: trip_id, // Ensure uniqueness
        input: JSON.stringify({
            trip_id,
            expected_return_time,
            emergency_contact
        })
    };

    try {
        await sfnClient.send(new StartExecutionCommand(sfnParams));
    } catch (e) {
        // If it's ExecutionAlreadyExists, that's fine (idempotency)
        if (e.name !== 'ExecutionAlreadyExists') throw e;
    }

    return {
        statusCode: 200,
        body: JSON.stringify({ message: "Watchdog started", trip_id })
    };
}

// --- Action: CANCEL ---
async function cancelWatchdog(trip_id) {
    // 1. Delete from DynamoDB (Immediate deletion as requested)
    await docClient.send(new DeleteCommand({
        TableName: TABLE_NAME,
        Key: { trip_id }
    }));

    // 2. Stop Step Function (Best effort)
    try {
        // Construct ARN based on convention if allowed, or we need to look it up.
        // Easiest way in pure serverless is usually just letting it run to completion
        // finding it's gone. But stopping saves money.
        // SFN Execution ARN format: arn:aws:states:region:account:execution:machineName:executionName
        // We need the State Machine ARN to construct the Execution ARN. 
        // For now, let's assume we can derive it or rely on the Check step finding the record gone.
        // "Dumb Watchdog" efficiency: If record is gone, CheckStatus does nothing. 
        // So explicitly stopping SFN is an optimization, not a requirement for correctness.
        // We will skip explicit StopExecution for simplicity unless requested, as strictly
        // speaking we need the Execution ARN.
    } catch (e) {
        console.warn("Failed to stop SFN:", e);
    }

    return {
        statusCode: 200,
        body: JSON.stringify({ message: "Watchdog cancelled" })
    };
}

// --- Action: CHECK STATUS (Invoked by SFN) ---
async function handleCheckStatus(event) {
    const { trip_id } = event;
    console.log("Checking status for:", trip_id);

    // 1. Get from DynamoDB
    const result = await docClient.send(new GetCommand({
        TableName: TABLE_NAME,
        Key: { trip_id }
    }));

    // If item doesn't exist, it was cancelled/deleted.
    if (!result.Item) {
        console.log("Trip not found (Cancelled). safe.");
        return { status: "SAFE" };
    }

    // If item exists, it is PENDING (or we wouldn't have deleted it).
    // TRIGGER ALERT!
    console.log("Trip found! TRIGGERING ALERT.");

    const contact = result.Item.emergency_contact || {};
    const user = result.Item.user_info || {};

    const message = `URGENT: Subterra Trip Overdue.
  
Trip ID: ${trip_id}
User: ${user.name || 'Unknown'} (${user.phone || 'No phone'})
Emergency Contact: ${contact.name} (${contact.phone})
Expected Return: ${result.Item.expected_return_time}

This is an automated message from the Subterra Redundant Safety System.
Please contact the user. If unreachable, initiate emergency protocols.`;

    await snsClient.send(new PublishCommand({
        TopicArn: SNS_TOPIC_ARN,
        Message: message,
        Subject: "Subterra Emergency Alert"
    }));

    return { status: "ALERTED" };
}

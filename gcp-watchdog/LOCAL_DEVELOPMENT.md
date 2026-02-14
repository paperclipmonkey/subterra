# Local Development Setup for GCP Watchdog

This guide will help you run the watchdog service locally for debugging.

## Prerequisites

- Node.js 18+ installed
- GCP account (optional for basic testing)
- TextMagic account (optional - can use test mode)
- SMTP credentials (optional - can use test mode)

## Setup Steps

### 1. Install Dependencies

```bash
cd gcp-watchdog
npm install
```

### 2. Configure Environment Variables

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` with your actual credentials or use test mode (see below).

> [!NOTE]
> For local development, the service will attempt to load secrets from `/secrets/config.json`. If this file does not exist, it will fall back to the environment variables defined in your `.env` file (see `src/secrets.ts`).

### 3. Run with Different Modes

#### Option A: Full Integration Mode (with real GCP)

1. **Authenticate with GCP**:
   ```bash
   gcloud auth application-default login
   gcloud config set project YOUR_PROJECT_ID
   ```

2. **Set up Firestore** (if not already done):
   ```bash
   # Enable Firestore API
   gcloud services enable firestore.googleapis.com
   
   # Create Firestore database (if needed)
   gcloud firestore databases create --region=us-central1
   ```

3. **Update `.env`** with your credentials:
   - Set `GCP_PROJECT_ID` to your actual GCP project
   - Set TextMagic credentials (or leave blank for testing)
   - Set SMTP credentials (or leave blank for testing)

4. **Run the service**:
   ```bash
   npm run dev
   ```

#### Option B: Local Firestore Emulator Mode (no GCP billing)

1. **Install Firebase emulators**:
   ```bash
   npm install -g firebase-tools
   ```

2. **Start Firestore emulator**:
   ```bash
   firebase emulators:start --only firestore
   ```
   
   This will start Firestore on `localhost:8080` by default.

3. **Update `.env`** to use emulator:
   ```bash
   FIRESTORE_EMULATOR_HOST=localhost:8080
   GCP_PROJECT_ID=demo-project
   ```

4. **Run the service** (in a new terminal):
   ```bash
   npm run dev
   ```

#### Option C: Quick Test Mode (mock everything)

For quick endpoint testing without external dependencies:

```bash
# Set minimal env vars
export PORT=8080
export GCP_PROJECT_ID=test-project

npm run dev
```

The service will start but may error on actual operations. Good for testing HTTP endpoints.

## Testing the Service

Once running, test the endpoints:

### 1. Health Check
```bash
curl http://localhost:8080/health
```

### 2. Register a Watchdog
```bash
curl -X POST http://localhost:8080/watchdog \
  -H "Content-Type: application/json" \
  -d '{
    "callout_id": "test-123",
    "callout_time": "2026-02-02T12:00:00Z",
    "user": {
      "name": "Test User",
      "phone": "+1234567890",
      "email": "test@example.com"
    },
    "participants": [
      {
        "name": "Participant 1",
        "phone": "+0987654321"
      }
    ],
    "trip_plan": "Testing the cave system",
    "cave_name": "Test Cave"
  }'
```

### 3. Check for Overdue Callouts
```bash
curl -X POST http://localhost:8080/check
```

### 4. Cancel a Watchdog
```bash
curl -X DELETE "http://localhost:8080/watchdog?callout_id=test-123"
```

## Debugging Tips

### View Logs
The service outputs logs to console. Look for:
- `Watchdog registered: ...` - successful registration
- `Found X overdue callout(s)` - overdue callouts detected
- Error messages for failed operations

### Common Issues

1. **Firestore connection errors**: Make sure you're authenticated with GCP or using the emulator
2. **SMS/Email errors**: These are non-blocking; the service will log errors but continue
3. **Port already in use**: Change `PORT` in `.env`

### Using Firestore Emulator UI

If using the emulator, view data at: `http://localhost:4000/firestore` (when running `firebase emulators:start --only firestore`)

## Next Steps

Once local testing is working:
1. Set up proper credentials in `.env`
2. Test with real SMS/email
3. Deploy to GCP using Terraform (see main README.md)

## Running Tests

```bash
# Run all tests
npm test

# Watch mode
npm run test:watch

# With coverage
npm run test:coverage
```

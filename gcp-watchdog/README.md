# GCP Watchdog Service

Serverless emergency monitoring system for Subterra callouts using GCP Cloud Run, Firestore, Cloud Scheduler, TextMagic SMS, and SMTP email.

## Architecture

- **Cloud Run**: Serverless Node.js/TypeScript Express application
- **Firestore**: NoSQL database for callout records
- **Cloud Scheduler**: 5-minute cron job to check for overdue callouts
- **TextMagic API**: SMS alerts
- **SMTP**: Email alerts
- **Secret Manager**: Secure credential storage

## Deployment

### Prerequisites

1. GCP project with billing enabled
2. `gcloud` CLI installed and authenticated
3. TextMagic account with API credentials
4. SMTP server credentials

### Steps

1. **Configure Terraform variables**:
   ```bash
   cd terraform
   cp terraform.tfvars.example terraform.tfvars
   # Edit terraform.tfvars with your values
   ```

2. **Build and push Docker image**:
   ```bash
   cd ..
   gcloud builds submit --tag gcr.io/YOUR_PROJECT_ID/subterra-watchdog:latest
   ```

3. **Deploy infrastructure**:
   ```bash
   cd terraform
   terraform init
   terraform plan
   terraform apply
   ```

4. **Note the Cloud Run URL** from Terraform outputs:
   ```bash
   terraform output cloud_run_url
   ```

## API Endpoints

### POST /watchdog
Register a new watchdog for a callout.

**Request**:
```json
{
  "callout_id": "unique-id",
  "callout_time": "2026-01-30T10:00:00Z",
  "user": {"name": "John Doe", "phone": "+1234567890", "email": "john@example.com"},
  "participants": [{"name": "Jane", "phone": "+0987654321"}],
  "trip_plan": "Exploring cave X",
  "cave_name": "Cave X"
}
```

### DELETE /watchdog?callout_id=xxx
Cancel a watchdog when callout is resolved.

### POST /check
Check for overdue callouts (triggered by Cloud Scheduler).

## Local Development

1. **Install dependencies**:
   ```bash
   npm install
   ```

2. **Set environment variables**:
   ```bash
   export GCP_PROJECT_ID=your-project
   export TEXTMAGIC_USERNAME=your-username
   export TEXTMAGIC_API_KEY=your-key
   export SMTP_SERVER=smtp.gmail.com
   export SMTP_PORT=587
   export SMTP_USERNAME=your-email
   export SMTP_PASSWORD=your-password
   export SMTP_FROM_EMAIL=watchdog@subterra.world
   export PORT=8080
   ```

3. **Run locally**:
   ```bash
   npm run dev
   ```

## Testing

Run unit tests:
```bash
npm test
```

Run with coverage:
```bash
npm run test:coverage
```

## Security & Secrets

The service uses GCP Secret Manager for sensitive configuration. In production (Cloud Run), it expects a JSON file mounted at `/secrets/config.json`.

### Secret Format

The secret should be a JSON object with the following fields:

```json
{
  "TEXTMAGIC_USERNAME": "your-username",
  "TEXTMAGIC_API_KEY": "your-api-key",
  "SMTP_USERNAME": "your-smtp-username",
  "SMTP_PASSWORD": "your-smtp-password",
  "WATCHDOG_API_KEY": "your-internal-api-key"
}
```

- **TEXTMAGIC_USERNAME**: Your TextMagic account username.
- **TEXTMAGIC_API_KEY**: Your TextMagic API key.
- **SMTP_USERNAME**: Username for the SMTP server (e.g., Gmail address).
- **SMTP_PASSWORD**: Password or App Password for the SMTP server.
- **WATCHDOG_API_KEY**: A shared secret used to authenticate requests from the main Subterra application to the watchdog service.

## Local Development

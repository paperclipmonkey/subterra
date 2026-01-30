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
  "emergency_contact": {"name": "Emergency", "phone": "+1111111111", "email": "emergency@example.com"},
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

## Monitoring

- View Cloud Run logs: `gcloud run services logs read subterra-watchdog`
- View Firestore data: GCP Console > Firestore
- View scheduler execution: GCP Console > Cloud Scheduler

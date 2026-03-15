# Service Account for the Laravel Application
resource "google_service_account" "laravel_app" {
  account_id   = "subterra-laravel-app"
  display_name = "Subterra Laravel Application Service Account"
  description  = "Service account used by the Laravel app to interact with GCP services (e.g., GCS staging)"
}

# Grant the Laravel Service Account read/write/delete (objectAdmin) on the media staging bucket
resource "google_storage_bucket_iam_member" "laravel_storage_admin" {
  bucket = google_storage_bucket.media_staging.name
  role   = "roles/storage.objectAdmin"
  member = "serviceAccount:${google_service_account.laravel_app.email}"
}

# Create a Service Account Key for Laravel (to download and input into Laravel .env)
resource "google_service_account_key" "laravel_key" {
  service_account_id = google_service_account.laravel_app.name
}

# Output the Service Account Key (sensitive)
# You can view this with: terraform output -raw laravel_service_account_key | base64 -d
output "laravel_service_account_key" {
  description = "The Service Account Key for the Laravel App (Base64 encoded JSON)"
  value       = google_service_account_key.laravel_key.private_key
  sensitive   = true
}

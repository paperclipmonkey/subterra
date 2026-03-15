# Secret Manager for sensitive credentials
# All credentials are stored in a single JSON secret that can be populated out-of-band

# Single consolidated secret for all watchdog credentials
resource "google_secret_manager_secret" "watchdog_config" {
  secret_id = "${var.app_name}-watchdog-config"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

# Create initial empty version (to be populated out-of-band)
resource "google_secret_manager_secret_version" "watchdog_config" {
  secret = google_secret_manager_secret.watchdog_config.id

  # Empty JSON placeholder - populate this manually with actual credentials
  secret_data = jsonencode({
    TEXTMAGIC_USERNAME = ""
    TEXTMAGIC_API_KEY  = ""
    SMTP_USERNAME      = ""
    SMTP_PASSWORD      = ""
    WATCHDOG_API_KEY   = ""
  })

  lifecycle {
    ignore_changes = [secret_data]
  }
}

# IAM: Allow Cloud Run service account to access the secret
resource "google_secret_manager_secret_iam_member" "watchdog_config_access" {
  secret_id = google_secret_manager_secret.watchdog_config.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.watchdog_service.email}"
}

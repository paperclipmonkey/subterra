# Secret Manager for sensitive credentials

# TextMagic API Username
resource "google_secret_manager_secret" "textmagic_username" {
  secret_id = "${var.app_name}-textmagic-username"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "textmagic_username" {
  secret      = google_secret_manager_secret.textmagic_username.id
  secret_data = var.textmagic_username
}

# TextMagic API Key
resource "google_secret_manager_secret" "textmagic_api_key" {
  secret_id = "${var.app_name}-textmagic-api-key"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "textmagic_api_key" {
  secret      = google_secret_manager_secret.textmagic_api_key.id
  secret_data = var.textmagic_api_key
}

# SMTP Username
resource "google_secret_manager_secret" "smtp_username" {
  secret_id = "${var.app_name}-smtp-username"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "smtp_username" {
  secret      = google_secret_manager_secret.smtp_username.id
  secret_data = var.smtp_username
}

# SMTP Password
resource "google_secret_manager_secret" "smtp_password" {
  secret_id = "${var.app_name}-smtp-password"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "smtp_password" {
  secret      = google_secret_manager_secret.smtp_password.id
  secret_data = var.smtp_password
}

# Slack Webhook URL (optional)
resource "google_secret_manager_secret" "slack_webhook_url" {
  count     = var.slack_webhook_url != "" ? 1 : 0
  secret_id = "${var.app_name}-slack-webhook-url"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "slack_webhook_url" {
  count       = var.slack_webhook_url != "" ? 1 : 0
  secret      = google_secret_manager_secret.slack_webhook_url[0].id
  secret_data = var.slack_webhook_url
}

# IAM: Allow Cloud Run service account to access secrets
resource "google_secret_manager_secret_iam_member" "textmagic_username_access" {
  secret_id = google_secret_manager_secret.textmagic_username.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.watchdog_service.email}"
}

resource "google_secret_manager_secret_iam_member" "textmagic_api_key_access" {
  secret_id = google_secret_manager_secret.textmagic_api_key.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.watchdog_service.email}"
}

resource "google_secret_manager_secret_iam_member" "smtp_username_access" {
  secret_id = google_secret_manager_secret.smtp_username.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.watchdog_service.email}"
}

resource "google_secret_manager_secret_iam_member" "smtp_password_access" {
  secret_id = google_secret_manager_secret.smtp_password.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.watchdog_service.email}"
}

resource "google_secret_manager_secret_iam_member" "slack_webhook_url_access" {
  count     = var.slack_webhook_url != "" ? 1 : 0
  secret_id = google_secret_manager_secret.slack_webhook_url[0].id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.watchdog_service.email}"
}

# Service Account for Cloud Scheduler
resource "google_service_account" "scheduler" {
  account_id   = "${var.app_name}-watchdog-scheduler"
  display_name = "Subterra Watchdog Scheduler"
  description  = "Service account for Cloud Scheduler to invoke Cloud Run"
}

# Grant Cloud Run Invoker role to scheduler service account
resource "google_cloud_run_v2_service_iam_member" "scheduler_invoker" {
  name     = google_cloud_run_v2_service.watchdog.name
  location = google_cloud_run_v2_service.watchdog.location
  role     = "roles/run.invoker"
  member   = "serviceAccount:${google_service_account.scheduler.email}"
}

# Cloud Scheduler Job - runs every 5 minutes
resource "google_cloud_scheduler_job" "watchdog_checker" {
  name             = "${var.app_name}-watchdog-checker"
  description      = "Check for overdue callouts every 5 minutes"
  schedule         = "*/5 * * * *"
  time_zone        = "UTC"
  attempt_deadline = "320s"
  region           = var.region

  http_target {
    http_method = "POST"
    uri         = "${google_cloud_run_v2_service.watchdog.uri}/check"

    oidc_token {
      service_account_email = google_service_account.scheduler.email
      audience              = google_cloud_run_v2_service.watchdog.uri
    }
  }

  retry_config {
    retry_count = 3
  }

  depends_on = [
    google_project_service.scheduler,
    google_cloud_run_v2_service.watchdog,
  ]
}

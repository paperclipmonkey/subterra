# Service Account for Cloud Run
resource "google_service_account" "watchdog_service" {
  account_id   = "${var.app_name}-service"
  display_name = "Subterra Watchdog Service Account"
  description  = "Service account for Subterra Watchdog Cloud Run service"
}

# Grant Firestore access to service account
resource "google_project_iam_member" "firestore_user" {
  project = var.project_id
  role    = "roles/datastore.user"
  member  = "serviceAccount:${google_service_account.watchdog_service.email}"
}

# Cloud Run Service
resource "google_cloud_run_v2_service" "watchdog" {
  name     = var.app_name
  location = var.region
  ingress  = "INGRESS_TRAFFIC_ALL"

  template {
    service_account = google_service_account.watchdog_service.email

    scaling {
      min_instance_count = 0
      max_instance_count = 10
    }

    containers {
      image = "europe-west2-docker.pkg.dev/${var.project_id}/subterra-watchdog/subterra-watchdog:${var.image_tag}"

      env {
        name  = "GCP_PROJECT_ID"
        value = var.project_id
      }

      env {
        name  = "ENVIRONMENT"
        value = var.environment
      }

      env {
        name  = "SMTP_SERVER"
        value = var.smtp_server
      }

      env {
        name  = "SMTP_PORT"
        value = tostring(var.smtp_port)
      }

      env {
        name  = "SMTP_FROM_EMAIL"
        value = var.smtp_from_email
      }

      env {
        name  = "SMTP_FROM_NAME"
        value = var.smtp_from_name
      }

      # Secrets from Secret Manager
      env {
        name = "TEXTMAGIC_USERNAME"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.textmagic_username.secret_id
            version = "latest"
          }
        }
      }

      env {
        name = "TEXTMAGIC_API_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.textmagic_api_key.secret_id
            version = "latest"
          }
        }
      }

      env {
        name = "SMTP_USERNAME"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.smtp_username.secret_id
            version = "latest"
          }
        }
      }

      env {
        name = "SMTP_PASSWORD"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.smtp_password.secret_id
            version = "latest"
          }
        }
      }

      resources {
        limits = {
          cpu    = "1"
          memory = "512Mi"
        }
      }

      ports {
        container_port = 8080
      }
    }
  }

  depends_on = [
    google_project_service.run,
    google_firestore_database.watchdog_db,
    google_secret_manager_secret_version.textmagic_username,
    google_secret_manager_secret_version.textmagic_api_key,
    google_secret_manager_secret_version.smtp_username,
    google_secret_manager_secret_version.smtp_password,
  ]
}

# Allow unauthenticated invocations for /watchdog endpoints (protected by API key in app)
# The /check endpoint will be protected by Cloud Scheduler's OIDC token
resource "google_cloud_run_v2_service_iam_member" "public_access" {
  name     = google_cloud_run_v2_service.watchdog.name
  location = google_cloud_run_v2_service.watchdog.location
  role     = "roles/run.invoker"
  member   = "allUsers"
}

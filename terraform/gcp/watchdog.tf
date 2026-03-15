# Service Account for Cloud Run
resource "google_service_account" "watchdog_service" {
  account_id   = "${var.app_name}-watchdog-service"
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
  name     = "${var.app_name}-watchdog"
  location = var.region
  ingress  = "INGRESS_TRAFFIC_ALL"

  template {
    service_account = google_service_account.watchdog_service.email

    scaling {
      min_instance_count = 0
      max_instance_count = 10
    }

    # Mount the consolidated secret as a volume at template level
    volumes {
      name = "secrets"
      secret {
        secret = google_secret_manager_secret.watchdog_config.secret_id
        items {
          version = "latest"
          path    = "config.json"
        }
      }
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

      # Mount the volume in the container
      volume_mounts {
        name       = "secrets"
        mount_path = "/secrets"
      }

      resources {
        limits = {
          cpu    = "1"
          memory = "512Mi"
        }
        cpu_idle = true
      }

      ports {
        container_port = 8080
      }
    }
  }

  depends_on = [
    google_project_service.run,
    google_firestore_database.watchdog_db,
    google_secret_manager_secret_version.watchdog_config,
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

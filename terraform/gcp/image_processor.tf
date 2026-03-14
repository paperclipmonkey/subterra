variable "image_processor_image_tag" {
  description = "Docker image tag for the image processor service"
  type        = string
  default     = "latest"
}

# Service Account for the Image Processor Cloud Run service
resource "google_service_account" "image_processor_service" {
  account_id   = "subterra-image-proc"
  display_name = "Subterra Image Processor Service Account"
  description  = "Service account for the image processor Cloud Run service"
}

# Grant the image processor service account read/write on the GCS staging bucket
resource "google_storage_bucket_iam_member" "image_processor_storage_admin" {
  bucket = google_storage_bucket.transcoder_staging.name
  role   = "roles/storage.admin"
  member = "serviceAccount:${google_service_account.image_processor_service.email}"
}

# Cloud Run Service for Image Processing
resource "google_cloud_run_v2_service" "image_processor" {
  name     = "subterra-image-processor"
  location = var.region
  ingress  = "INGRESS_TRAFFIC_ALL"

  template {
    service_account = google_service_account.image_processor_service.email

    scaling {
      min_instance_count = 0
      max_instance_count = 5
    }

    containers {
      image = "europe-west2-docker.pkg.dev/${var.project_id}/subterra-image-processor/subterra-image-processor:${var.image_processor_image_tag}"

      env {
        name  = "GCP_PROJECT_ID"
        value = var.project_id
      }

      env {
        name  = "ENVIRONMENT"
        value = var.environment
      }

      env {
        name  = "IMAGE_PROCESSOR_API_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.image_processor_api_key.secret_id
            version = "latest"
          }
        }
      }

      env {
        name  = "CALLBACK_SECRET"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.image_processor_callback_secret.secret_id
            version = "latest"
          }
        }
      }

      resources {
        limits = {
          cpu    = "2"
          memory = "1Gi"
        }
        cpu_idle = true
      }

      ports {
        container_port = 8080
      }
    }

    # Allow up to 5 min for large image processing
    timeout = "300s"
  }

  depends_on = [
    google_project_service.run,
    google_secret_manager_secret_version.image_processor_api_key,
    google_secret_manager_secret_version.image_processor_callback_secret,
  ]
}

# Allow unauthenticated invocations (protected by API key in app)
resource "google_cloud_run_v2_service_iam_member" "image_processor_public_access" {
  name     = google_cloud_run_v2_service.image_processor.name
  location = google_cloud_run_v2_service.image_processor.location
  role     = "roles/run.invoker"
  member   = "allUsers"
}

# Secrets for the image processor
resource "google_secret_manager_secret" "image_processor_api_key" {
  secret_id = "image-processor-api-key"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "image_processor_api_key" {
  secret      = google_secret_manager_secret.image_processor_api_key.id
  secret_data = "CHANGE_ME_ON_FIRST_DEPLOY"

  lifecycle {
    ignore_changes = [secret_data]
  }
}

resource "google_secret_manager_secret" "image_processor_callback_secret" {
  secret_id = "image-processor-callback-secret"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "image_processor_callback_secret" {
  secret      = google_secret_manager_secret.image_processor_callback_secret.id
  secret_data = "CHANGE_ME_ON_FIRST_DEPLOY"

  lifecycle {
    ignore_changes = [secret_data]
  }
}

# Grant the service account access to read secrets
resource "google_secret_manager_secret_iam_member" "image_processor_api_key_access" {
  secret_id = google_secret_manager_secret.image_processor_api_key.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.image_processor_service.email}"
}

resource "google_secret_manager_secret_iam_member" "image_processor_callback_secret_access" {
  secret_id = google_secret_manager_secret.image_processor_callback_secret.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.image_processor_service.email}"
}

# Artifact Registry for the image processor Docker images
resource "google_artifact_registry_repository" "image_processor" {
  location      = var.region
  repository_id = "subterra-image-processor"
  format        = "DOCKER"
  description   = "Docker repository for Subterra image processor Cloud Run service"

  depends_on = [google_project_service.artifactregistry]
}

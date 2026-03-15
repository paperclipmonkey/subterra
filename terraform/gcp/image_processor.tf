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
  bucket = google_storage_bucket.media_staging.name
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

    volumes {
      name = "secrets"
      secret {
        secret = google_secret_manager_secret.image_processor_config.secret_id
        items {
          version = "latest"
          path    = "config.json"
        }
      }
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

      volume_mounts {
        name       = "secrets"
        mount_path = "/secrets"
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
    google_secret_manager_secret_version.image_processor_config,
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
resource "google_secret_manager_secret" "image_processor_config" {
  secret_id = "subterra-image-proc-config"

  replication {
    auto {}
  }

  depends_on = [google_project_service.secretmanager]
}

resource "google_secret_manager_secret_version" "image_processor_config" {
  secret = google_secret_manager_secret.image_processor_config.id

  # Empty JSON placeholder - populate this manually with actual credentials
  secret_data = jsonencode({
    IMAGE_PROCESSOR_API_KEY = "CHANGE_ME"
    CALLBACK_SECRET         = "CHANGE_ME"
  })

  lifecycle {
    ignore_changes = [secret_data]
  }
}

# Grant the service account access to read secrets
resource "google_secret_manager_secret_iam_member" "image_processor_config_access" {
  secret_id = google_secret_manager_secret.image_processor_config.id
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

# --- Eventarc Trigger for GCS Uploads ---

data "google_storage_project_service_account" "gcs_agent" {}

# Grant GCS service agent publishing rights to Pub/Sub (required for Eventarc delivery)
resource "google_project_iam_member" "gcs_pubsub_publishing" {
  project = var.project_id
  role    = "roles/pubsub.publisher"
  member  = "serviceAccount:${data.google_storage_project_service_account.gcs_agent.email_address}"
}

# Eventarc Trigger from GCS to Cloud Run
resource "google_eventarc_trigger" "gcs_upload_trigger" {
  name     = "${var.app_name}-media-trigger"
  location = var.region

  matching_criteria {
    attribute = "type"
    value     = "google.cloud.storage.object.v1.finalized"
  }

  matching_criteria {
    attribute = "bucket"
    value     = google_storage_bucket.media_staging.name
  }

  destination {
    cloud_run_service {
      service = google_cloud_run_v2_service.image_processor.name
      region  = var.region
    }
  }

  service_account = google_service_account.image_processor_service.email

  depends_on = [
    google_project_iam_member.gcs_pubsub_publishing
  ]
}

# Grant the image processor SA permissions to receive Eventarc events
resource "google_project_iam_member" "image_processor_event_receiver" {
  project = var.project_id
  role    = "roles/eventarc.eventReceiver"
  member  = "serviceAccount:${google_service_account.image_processor_service.email}"
}

# Grant the image processor SA permissions to publish to Pub/Sub
resource "google_project_iam_member" "image_processor_pubsub_publisher" {
  project = var.project_id
  role    = "roles/pubsub.publisher"
  member  = "serviceAccount:${google_service_account.image_processor_service.email}"
}

# Grant the image processor SA permissions to submit Transcoder jobs
resource "google_project_iam_member" "image_processor_transcoder_user" {
  project = var.project_id
  role    = "roles/transcoder.admin"
  member  = "serviceAccount:${google_service_account.image_processor_service.email}"
}

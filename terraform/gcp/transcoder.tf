variable "media_staging_bucket" {
  description = "Name of the GCS bucket used as a staging area for media processing"
  type        = string
  default     = "subterra-media-staging"
}

# Enable the Transcoder API
resource "google_project_service" "transcoder" {
  service            = "transcoder.googleapis.com"
  disable_on_destroy = false
}

# GCS staging bucket for media processing
resource "google_storage_bucket" "media_staging" {
  name          = var.media_staging_bucket
  location      = var.region
  force_destroy = true

  lifecycle_rule {
    condition {
      age = 1
    }
    action {
      type = "Delete"
    }
  }
}

# Retrieve the current project metadata (needed for the Transcoder Service Agent email)
data "google_project" "transcoder_project" {}

# Grant the Transcoder Service Agent storage.admin on the staging bucket
resource "google_storage_bucket_iam_member" "transcoder_sa_storage_admin" {
  bucket = google_storage_bucket.media_staging.name
  role   = "roles/storage.admin"
  member = "serviceAccount:service-${data.google_project.transcoder_project.number}@gcp-sa-transcoder.iam.gserviceaccount.com"

  depends_on = [google_project_service.transcoder]
}

# Transcoder Job Template — H.264 720p MP4 with AAC audio
resource "google_transcoder_job_template" "web_hd_mp4" {
  job_template_id = "web-hd-mp4"
  location        = var.region

  config {
    elementary_streams {
      key = "video-stream"
      video_stream {
        h264 {
          height_pixels = 720
          width_pixels  = 1280
          bitrate_bps   = 2500000
          frame_rate    = 30
        }
      }
    }

    elementary_streams {
      key = "audio-stream"
      audio_stream {
        codec         = "aac"
        bitrate_bps   = 64000
        channel_count = 2
      }
    }

    mux_streams {
      key                = "sd"
      container          = "mp4"
      elementary_streams = ["video-stream", "audio-stream"]
    }
  }

  depends_on = [google_project_service.transcoder]
}

# --- Pub/sub Topic for finished transcoder jobs ---
resource "google_pubsub_topic" "transcoder_notifications" {
  name = "${var.app_name}-transcoder-notifications"
}

resource "google_pubsub_subscription" "transcoder_webhook" {
  name  = "${var.app_name}-transcoder-webhook"
  topic = google_pubsub_topic.transcoder_notifications.id

  # Push message to Laravel URL containing query param auth
  push_config {
    push_endpoint = sensitive("${var.app_url}/api/webhooks/gcp/transcoder?token=${var.webhook_secret}")
    
    attributes = {
      "x-goog-version" = "v1"
    }
  }

  ack_deadline_seconds = 60
}

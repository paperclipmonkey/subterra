output "watchdog_url" {
  description = "URL of the Watchdog Cloud Run service"
  value       = google_cloud_run_v2_service.watchdog.uri
}

output "firestore_database" {
  description = "Firestore database name"
  value       = google_firestore_database.watchdog_db.name
}

output "watchdog_service_account_email" {
  description = "Service account email for the Watchdog Cloud Run service"
  value       = google_service_account.watchdog_service.email
}

output "image_processor_service_account_email" {
  description = "Service account email for the Image Processor Cloud Run service"
  value       = google_service_account.image_processor_service.email
}

output "scheduler_job_name" {
  description = "Cloud Scheduler job name"
  value       = google_cloud_scheduler_job.watchdog_checker.name
}

output "media_pubsub_topic" {
  description = "Pub/Sub topic for all media processing notifications"
  value       = google_pubsub_topic.media_notifications.id
}

output "cloudsql_instance_name" {
  description = "Cloud SQL instance name"
  value       = google_sql_database_instance.postgres.name
}

output "cloudsql_public_ip" {
  description = "Cloud SQL public IP address (set as DB_HOST in the app)"
  value       = google_sql_database_instance.postgres.public_ip_address
}

output "cloudsql_connection_name" {
  description = "Cloud SQL connection name — used with the Cloud SQL Auth Proxy (project:region:instance)"
  value       = google_sql_database_instance.postgres.connection_name
}

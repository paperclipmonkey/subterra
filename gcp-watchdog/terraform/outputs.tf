output "cloud_run_url" {
  description = "URL of the Cloud Run service"
  value       = google_cloud_run_v2_service.watchdog.uri
}

output "firestore_database" {
  description = "Firestore database name"
  value       = google_firestore_database.watchdog_db.name
}

output "service_account_email" {
  description = "Service account email for Cloud Run"
  value       = google_service_account.watchdog_service.email
}

output "scheduler_job_name" {
  description = "Cloud Scheduler job name"
  value       = google_cloud_scheduler_job.watchdog_checker.name
}

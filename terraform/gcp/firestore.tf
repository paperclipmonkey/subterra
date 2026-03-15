# Firestore Database
resource "google_firestore_database" "watchdog_db" {
  project     = var.project_id
  name        = "(default)"
  location_id = var.region
  type        = "FIRESTORE_NATIVE"

  depends_on = [google_project_service.firestore]
}

# Composite index for efficient queries on status + callout_time
resource "google_firestore_index" "callout_status_time" {
  project    = var.project_id
  database   = google_firestore_database.watchdog_db.name
  collection = "callouts"

  fields {
    field_path = "status"
    order      = "ASCENDING"
  }

  fields {
    field_path = "callout_time"
    order      = "ASCENDING"
  }

  depends_on = [google_firestore_database.watchdog_db]
}

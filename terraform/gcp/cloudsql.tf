# ---------------------------------------------------------------------------
# Cloud SQL – PostgreSQL (single-zone, cost-optimised)
# ---------------------------------------------------------------------------
# Estimated cost (europe-west2):
#   db-f1-micro compute  ~$7.67 / month
#   10 GB SSD storage    ~$1.70 / month
#   Backup storage       ~$0.50 / month  (varies with DB size)
#   PITR log storage     ~$0.50 / month  (varies with write volume)
#   ─────────────────────────────────────────
#   Total                ~$10–12 / month
# ---------------------------------------------------------------------------

resource "google_sql_database_instance" "postgres" {
  name             = "${var.app_name}-postgres-${var.environment}"
  database_version = "POSTGRES_17"
  region           = var.region

  # Prevent accidental destruction of production data.
  # Set to false only when you explicitly want to delete the instance.
  deletion_protection = true

  settings {
    # db-f1-micro: shared vCPU, 0.6 GB RAM — lowest-cost Cloud SQL tier.
    # Hard limit of 25 connections; add PgBouncer if you need more.
    tier = "db-f1-micro"

    # ZONAL = single instance, no standby replica.
    # Change to "REGIONAL" to enable automatic HA failover ($2× compute cost).
    availability_type = "ZONAL"

    disk_size             = 10    # Minimum SSD size (GB)
    disk_type             = "PD_SSD"
    disk_autoresize       = true
    disk_autoresize_limit = 50 # Cap growth at 50 GB to prevent cost surprises

    # ------------------------------------------------------------------
    # Backup & Point-in-Time Recovery
    # ------------------------------------------------------------------
    backup_configuration {
      enabled    = true
      start_time = "03:00" # 03:00 UTC — low-traffic window

      # PITR: keeps 7 days of WAL so you can restore to any second in that window.
      point_in_time_recovery_enabled = true
      transaction_log_retention_days = 7

      backup_retention_settings {
        retained_backups = 14         # 2 weeks of daily snapshots
        retention_unit   = "COUNT"
      }
    }

    # ------------------------------------------------------------------
    # Maintenance window
    # ------------------------------------------------------------------
    maintenance_window {
      day          = 7 # Sunday
      hour         = 4 # 04:00 UTC
      update_track = "stable"
    }

    # ------------------------------------------------------------------
    # Network / IP
    # ------------------------------------------------------------------
    ip_configuration {
      # Public IP is required while the app runs on Fly.io.
      # After migrating the app to Cloud Run you can disable this and
      # switch to a private IP + VPC connector instead.
      ipv4_enabled = true

      # Reject plain-text connections; TLS is mandatory.
      ssl_mode = "ENCRYPTED_ONLY"

      # Allowlist specific CIDRs (e.g. Fly.io egress IPs, your office).
      # Populate var.db_authorized_networks in terraform.tfvars.
      dynamic "authorized_networks" {
        for_each = var.db_authorized_networks
        content {
          name  = authorized_networks.value.name
          value = authorized_networks.value.value
        }
      }
    }

    # ------------------------------------------------------------------
    # Database flags
    # ------------------------------------------------------------------
    database_flags {
      name  = "log_min_duration_statement"
      value = "1000" # Log queries taking longer than 1 s
    }

    # ------------------------------------------------------------------
    # Query Insights (included in Cloud SQL cost, no extra charge)
    # ------------------------------------------------------------------
    insights_config {
      query_insights_enabled  = true
      query_string_length     = 1024
      record_application_tags = false
      record_client_address   = false
    }
  }

  depends_on = [google_project_service.sqladmin]
}

# ---------------------------------------------------------------------------
# Database and user
# ---------------------------------------------------------------------------

resource "google_sql_database" "app" {
  name     = var.db_name
  instance = google_sql_database_instance.postgres.name
}

resource "google_sql_user" "app" {
  name     = var.db_username
  instance = google_sql_database_instance.postgres.name
  password = var.db_password
}

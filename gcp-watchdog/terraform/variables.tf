variable "project_id" {
  description = "GCP Project ID"
  type        = string
}

variable "region" {
  description = "GCP Region"
  type        = string
  default     = "europe-west2"
}

variable "app_name" {
  description = "Application name prefix"
  type        = string
  default     = "subterra-watchdog"
}

variable "environment" {
  description = "Environment (dev, staging, prod)"
  type        = string
  default     = "prod"
}

variable "textmagic_username" {
  description = "TextMagic API username"
  type        = string
  sensitive   = true
}

variable "textmagic_api_key" {
  description = "TextMagic API key"
  type        = string
  sensitive   = true
}

variable "smtp_server" {
  description = "SMTP server hostname"
  type        = string
}

variable "smtp_port" {
  description = "SMTP server port"
  type        = number
  default     = 587
}

variable "smtp_username" {
  description = "SMTP username"
  type        = string
  sensitive   = true
}

variable "smtp_password" {
  description = "SMTP password"
  type        = string
  sensitive   = true
}

variable "smtp_from_email" {
  description = "SMTP from email address"
  type        = string
}

variable "smtp_from_name" {
  description = "SMTP from name"
  type        = string
  default     = "Subterra Watchdog"
}

variable "slack_webhook_url" {
  description = "Slack webhook URL for monitoring (optional)"
  type        = string
  default     = ""
}

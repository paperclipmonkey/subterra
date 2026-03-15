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

variable "smtp_server" {
  description = "SMTP server hostname"
  type        = string
}

variable "smtp_port" {
  description = "SMTP server port"
  type        = number
  default     = 587
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

variable "image_tag" {
  description = "Docker image tag to deploy (typically the git commit SHA)"
  type        = string
  default     = "latest"
}

variable "webhook_secret" {
  description = "Secret used to authenticate webhook callbacks from image processor & transcoder"
  type        = string
  sensitive   = true
}

variable "app_url" {
  description = "Domain (with https://) of the Laravel application to send webhooks back to"
  type        = string
  default     = "https://subterra.world"
}


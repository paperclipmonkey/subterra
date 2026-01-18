terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 4.16"
    }
  }
  required_version = ">= 1.2.0"
}

provider "aws" {
  region = "us-east-1"
}

# --- Variables ---
variable "app_name" {
  default = "subterra-watchdog"
}

variable "stage" {
  default = "prod"
}

# --- DynamoDB Table ---
resource "aws_dynamodb_table" "watchdogs" {
  name           = "${var.app_name}-trips-${var.stage}"
  billing_mode   = "PAY_PER_REQUEST"
  hash_key       = "trip_id"

  attribute {
    name = "trip_id"
    type = "S"
  }

  tags = {
    Name        = "${var.app_name}-table"
    Environment = var.stage
  }

  ttl {
    attribute_name = "expires_at"
    enabled        = true
  }
}

# --- SNS Topic ---
resource "aws_sns_topic" "alerts" {
  name = "${var.app_name}-alerts-${var.stage}"
}

# --- IAM Roles ---
resource "aws_iam_role" "lambda_role" {
  name = "${var.app_name}-lambda-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "lambda_policy" {
  role       = aws_iam_role.lambda_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole"
}

resource "aws_iam_role_policy" "lambda_dynamo_sns" {
  name = "${var.app_name}-lambda-policy"
  role = aws_iam_role.lambda_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "dynamodb:PutItem",
          "dynamodb:GetItem",
          "dynamodb:UpdateItem",
          "dynamodb:DeleteItem"
        ]
        Resource = aws_dynamodb_table.watchdogs.arn
      },
      {
        Effect = "Allow"
        Action = [
          "sns:Publish"
        ]
        Resource = aws_sns_topic.alerts.arn
      },
      {
        Effect = "Allow"
        Action = [
          "states:StartExecution"
        ]
        Resource = aws_sfn_state_machine.watchdog_flow.arn
      }
    ]
  })
}

# --- Lambda Function (Shared / Monolith for now, or Split) ---
# For simplicity, using a single zip for now, logic handled in handlers
data "archive_file" "lambda_zip" {
  type        = "zip"
  source_dir  = "${path.module}/lambda"
  output_path = "${path.module}/lambda.zip"
}

resource "aws_lambda_function" "watchdog_fn" {
  filename      = data.archive_file.lambda_zip.output_path
  function_name = "${var.app_name}-handler-${var.stage}"
  role          = aws_iam_role.lambda_role.arn
  handler       = "index.handler"
  runtime       = "nodejs22.x"
  source_code_hash = data.archive_file.lambda_zip.output_base64sha256

  environment {
    variables = {
      TABLE_NAME        = aws_dynamodb_table.watchdogs.name
      SNS_TOPIC_ARN     = aws_sns_topic.alerts.arn
      STATE_MACHINE_ARN = aws_sfn_state_machine.watchdog_flow.arn
    }
  }
}

# --- Step Functions ---
resource "aws_iam_role" "sfn_role" {
  name = "${var.app_name}-sfn-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "states.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy" "sfn_policy" {
  name = "${var.app_name}-sfn-policy"
  role = aws_iam_role.sfn_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "lambda:InvokeFunction"
        ]
        Resource = aws_lambda_function.watchdog_fn.arn
      }
    ]
  })
}

resource "aws_sfn_state_machine" "watchdog_flow" {
  name     = "${var.app_name}-flow-${var.stage}"
  role_arn = aws_iam_role.sfn_role.arn

  definition = <<EOF
{
  "Comment": "Watchdog Timer",
  "StartAt": "WaitForReturn",
  "States": {
    "WaitForReturn": {
      "Type": "Wait",
      "TimestampPath": "$.expected_return_time",
      "Next": "CheckStatus"
    },
    "CheckStatus": {
      "Type": "Task",
      "Resource": "${aws_lambda_function.watchdog_fn.arn}",
      "Parameters": {
        "action": "check_status",
        "trip_id.$": "$.trip_id",
        "emergency_contact.$": "$.emergency_contact"
      },
      "End": true
    }
  }
}
EOF
}

# --- API Gateway (HTTP API) ---
resource "aws_apigatewayv2_api" "http_api" {
  name          = "${var.app_name}-api-${var.stage}"
  protocol_type = "HTTP"
}

resource "aws_apigatewayv2_stage" "default" {
  api_id = aws_apigatewayv2_api.http_api.id
  name   = "$default"
  auto_deploy = true
}

# POST /watchdog/start -> Lambda (which starts SFN)
resource "aws_apigatewayv2_integration" "start_integration" {
  api_id           = aws_apigatewayv2_api.http_api.id
  integration_type = "AWS_PROXY"
  integration_uri  = aws_lambda_function.watchdog_fn.invoke_arn
  payload_format_version = "2.0"
}

resource "aws_apigatewayv2_route" "start_route" {
  api_id    = aws_apigatewayv2_api.http_api.id
  route_key = "POST /watchdog"
  target    = "integrations/${aws_apigatewayv2_integration.start_integration.id}"
}

# DELETE /watchdog -> Lambda (Cancel)
resource "aws_apigatewayv2_route" "cancel_route" {
  api_id    = aws_apigatewayv2_api.http_api.id
  route_key = "DELETE /watchdog"
  target    = "integrations/${aws_apigatewayv2_integration.start_integration.id}"
}

# Permission for Gateway to invoke Lambda
resource "aws_lambda_permission" "apigw" {
  statement_id  = "AllowAPIGatewayInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.watchdog_fn.function_name
  principal     = "apigateway.amazonaws.com"
  source_arn    = "${aws_apigatewayv2_api.http_api.execution_arn}/*/*"
}

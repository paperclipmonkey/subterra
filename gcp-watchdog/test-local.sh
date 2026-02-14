#!/bin/bash
# Test script for local watchdog service

BASE_URL="http://localhost:8080"

echo "🧪 Testing Watchdog Service..."
echo ""

# Test 1: Health check
echo "1️⃣ Health Check:"
curl -s "$BASE_URL/health" | jq '.'
echo ""
echo ""

# Test 2: Register a watchdog
echo "2️⃣ Registering a test watchdog:"
CALLOUT_TIME=$(date -u -v+2H +"%Y-%m-%dT%H:%M:%SZ" 2>/dev/null || date -u -d "+2 hours" +"%Y-%m-%dT%H:%M:%SZ")
curl -s -X POST "$BASE_URL/watchdog" \
  -H "Content-Type: application/json" \
  -d "{
    \"callout_id\": \"test-$(date +%s)\",
    \"callout_time\": \"$CALLOUT_TIME\",
    \"user\": {
      \"name\": \"Test User\",
      \"phone\": \"+1234567890\",
      \"email\": \"test@example.com\"
    },
    \"participants\": [
      {
        \"name\": \"Participant 1\",
        \"phone\": \"+0987654321\"
      }
    ],
    \"trip_plan\": \"Testing the local watchdog service\",
    \"cave_name\": \"Test Cave\"
  }" | jq '.'
echo ""
echo ""

# Test 3: Check for overdue callouts (shouldn't find any since we set time to +2 hours)
echo "3️⃣ Checking for overdue callouts:"
curl -s -X POST "$BASE_URL/check" | jq '.'
echo ""
echo ""

echo "✅ Tests complete! Check the service logs for detailed output."
echo "To cancel a watchdog: curl -X DELETE \"$BASE_URL/watchdog?callout_id=YOUR_ID\""

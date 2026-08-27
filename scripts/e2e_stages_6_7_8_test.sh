#!/bin/bash
# E2E Test Script for Timetable Stages 6, 7 & 8

API_BASE="http://localhost/shule-cafe/api"
COOKIE_JAR="/tmp/shule_test_cookie.txt"

echo "============================================================"
echo "🧪 STARTING E2E TEST: STAGES 6, 7 & 8 TIMETABLE PIPELINE"
echo "============================================================"

# Step 1: Login as Headmaster
echo "[1/5] Authenticating Headmaster..."
curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$API_BASE/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"headmaster@shulecafe.com","password":"password123"}' > /tmp/login_res.json

LOGIN_OK=$(grep -o '"success":true' /tmp/login_res.json)
if [ -n "$LOGIN_OK" ]; then
    echo "✅ Headmaster authenticated successfully."
else
    echo "⚠️ Login response: $(cat /tmp/login_res.json)"
fi

# Step 2: Test Save Draft (Stage 7)
echo "[2/5] Testing Stage 7 Save as Draft..."
curl -s -b "$COOKIE_JAR" -X POST "$API_BASE/timetable/timetable_config_api.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"save_draft"}' > /tmp/draft_res.json

echo "Response: $(cat /tmp/draft_res.json)"

# Step 3: Test Teacher Schedule API in Draft Mode (Stage 8)
echo "[3/5] Testing Teacher Schedule API while in Draft Mode (Expect is_published=0)..."
curl -s -b "$COOKIE_JAR" "$API_BASE/timetable/teacher_schedule_api.php" > /tmp/teacher_draft_res.json
echo "Response: $(cat /tmp/teacher_draft_res.json)"

# Step 4: Test Publish Live (Stage 7)
echo "[4/5] Testing Stage 7 Publish Live..."
curl -s -b "$COOKIE_JAR" -X POST "$API_BASE/timetable/timetable_config_api.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"publish_live"}' > /tmp/pub_res.json

echo "Response: $(cat /tmp/pub_res.json)"

# Step 5: Test Teacher Schedule API in Live Mode (Stage 8)
echo "[5/5] Testing Teacher Schedule API while Live Published (Expect is_published=1)..."
curl -s -b "$COOKIE_JAR" "$API_BASE/timetable/teacher_schedule_api.php" > /tmp/teacher_live_res.json
echo "Response: $(cat /tmp/teacher_live_res.json)"

echo "============================================================"
echo "🎉 E2E TEST COMPLETED FOR STAGES 6, 7 & 8!"
echo "============================================================"

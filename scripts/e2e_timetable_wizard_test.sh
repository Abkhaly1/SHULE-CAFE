#!/usr/bin/env bash
# E2E Automated Timetable Generation Wizard & Engine Test Script
SESSID='ltmupe4r05c6i1u3u9403ll6sa'
BASE='http://localhost/shule-cafe'
YEAR='2026'

echo "1) Database Migration (Timetable Config Tables)"
curl -s -b "PHPSESSID=${SESSID}" "${BASE}/api/database/migrations/create_timetable_config_tables.php" | jq '.'

echo -e "\n2) Status Check (Timetable Config Status)"
curl -s -b "PHPSESSID=${SESSID}" "${BASE}/api/timetable/timetable_config_api.php?action=status&year=${YEAR}" | jq '.'

echo -e "\n3) Fetch Wizard Data (Levels, Grades, Subjects)"
curl -s -b "PHPSESSID=${SESSID}" "${BASE}/api/timetable/timetable_config_api.php?action=get_wizard_data&year=${YEAR}" | jq '. | {success: .success, levels: (.education_levels|length), grades: (.grades|length)}'

echo -e "\n4) Save Configuration (Stages 1 to 4)"
curl -s -b "PHPSESSID=${SESSID}" -X POST -H 'Content-Type: application/json' -d '{
  "action": "save_config",
  "level_code": "O-LEVEL",
  "selected_grades": [1, 2, 3, 4],
  "operational_days": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
  "periods_per_day": 8,
  "breaks_count": 2,
  "time_matrix": [
    {"period_number":1, "period_name":"Period 1", "start_time":"08:00", "end_time":"08:40", "is_break":0},
    {"period_number":2, "period_name":"Period 2", "start_time":"08:40", "end_time":"09:20", "is_break":0},
    {"period_number":3, "period_name":"Period 3", "start_time":"09:20", "end_time":"10:00", "is_break":0},
    {"period_number":4, "period_name":"Tea Break", "start_time":"10:00", "end_time":"10:30", "is_break":1},
    {"period_number":5, "period_name":"Period 4", "start_time":"10:30", "end_time":"11:10", "is_break":0},
    {"period_number":6, "period_name":"Period 5", "start_time":"11:10", "end_time":"11:50", "is_break":0},
    {"period_number":7, "period_name":"Period 6", "start_time":"11:50", "end_time":"12:30", "is_break":0},
    {"period_number":8, "period_name":"Lunch Break", "start_time":"12:30", "end_time":"13:30", "is_break":1},
    {"period_number":9, "period_name":"Period 7", "start_time":"13:30", "end_time":"14:10", "is_break":0},
    {"period_number":10, "period_name":"Period 8", "start_time":"14:10", "end_time":"14:50", "is_break":0}
  ],
  "frequencies": [
    {"grade_id":1, "subject_code":"B-MATH", "weekly_frequency":6},
    {"grade_id":1, "subject_code":"ENG", "weekly_frequency":5},
    {"grade_id":1, "subject_code":"CHE", "weekly_frequency":4},
    {"grade_id":1, "subject_code":"BIO", "weekly_frequency":4},
    {"grade_id":1, "subject_code":"PHY", "weekly_frequency":4},
    {"grade_id":1, "subject_code":"GEO", "weekly_frequency":4},
    {"grade_id":1, "subject_code":"HIST", "weekly_frequency":4},
    {"grade_id":1, "subject_code":"KISW", "weekly_frequency":5},
    {"grade_id":1, "subject_code":"CIV", "weekly_frequency":4}
  ]
}' "${BASE}/api/timetable/timetable_config_api.php" | jq '.'

echo -e "\n5) Stage 5 Automated Conflict-Free Engine Run"
curl -s -b "PHPSESSID=${SESSID}" -X POST -H 'Content-Type: application/json' -d '{
  "action": "generate_automated_timetable"
}' "${BASE}/api/timetable/timetable_config_api.php" | jq '.'

echo -e "\n6) Retrieve Final Timetable Matrix Grid"
curl -s -b "PHPSESSID=${SESSID}" "${BASE}/api/timetable/timetable_config_api.php?action=get_timetable_matrix&year=${YEAR}" | jq '. | {success: .success, total_scheduled_slots: .total_scheduled_slots}'

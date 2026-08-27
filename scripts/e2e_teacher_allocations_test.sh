#!/usr/bin/env bash
# Simple curl-based E2E test for teacher master subject allocations
SESSID='ltmupe4r05c6i1u3u9403ll6sa'
BASE='http://localhost/shule-cafe'
TEACHER_ID='7717a810-bc2f-42b5-ae28-8fafb7cf0494'
SUBJECT_CODE='ADV-MATH'
YEAR='2026'

echo "1) Directory (Step 1 Master Staff Roster)"
curl -s -b "PHPSESSID=${SESSID}" "${BASE}/api/headmaster/academics/teacher_allocations_api.php?action=directory" | jq '. | {success: .success, teachers: (.teachers|length)}'

echo -e "\n2) Profile (Step 2 Master Subject Qualifications Context)"
curl -s -b "PHPSESSID=${SESSID}" "${BASE}/api/headmaster/academics/teacher_allocations_api.php?action=profile&teacher_id=${TEACHER_ID}&year=${YEAR}" | jq '. | {success: .success, qualifications: (.qualifications|length), all_subjects: (.all_subjects|length)}'

echo -e "\n3) Save Master Subject Qualifications (Advanced Mathematics)"
curl -s -b "PHPSESSID=${SESSID}" -X POST -H 'Content-Type: application/json' -d '{"action":"save_qualifications","teacher_id":"'${TEACHER_ID}'","subject_codes":["'${SUBJECT_CODE}'"]}' "${BASE}/api/headmaster/academics/teacher_allocations_api.php" | jq '{success: .success, message: .message}'

echo -e "\n4) Re-verify Teacher Profile & Qualifications"
curl -s -b "PHPSESSID=${SESSID}" "${BASE}/api/headmaster/academics/teacher_allocations_api.php?action=profile&teacher_id=${TEACHER_ID}&year=${YEAR}" | jq '. | {success: .success, qualifications: .qualifications}'

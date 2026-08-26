Migration: teacher allocation tables

Files:
- create_teacher_allocation_tables.php

Purpose:
- Adds `teacher_subject_qualifications` and `teacher_classroom_assignments` tables used by the Teacher Allocation module.

How to run:
From the project root execute:

/opt/lampp/bin/php api/database/migrations/create_teacher_allocation_tables.php

Notes:
- The script requires a working `config/db.php` with valid DB credentials.
- The migration is idempotent and uses `CREATE TABLE IF NOT EXISTS`.

Rollback:
- Manual DROP TABLE statements are required if rollback is necessary.


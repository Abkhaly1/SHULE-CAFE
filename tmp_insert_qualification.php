<?php
require 'api/config/db.php';
$teacherId = '7717a810-bc2f-42b5-ae28-8fafb7cf0494';
$subjectId = 'd4387898-8e00-11f1-b74a-340286880450';
$stmt = $conn->prepare('INSERT IGNORE INTO teacher_subject_qualifications (teacher_id, subject_id) VALUES (?, ?)');
$res = $stmt->execute([$teacherId, $subjectId]);
var_export($res);
?>
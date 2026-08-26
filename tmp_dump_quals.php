<?php
require 'api/config/db.php';
$r = $conn->query('SELECT * FROM teacher_subject_qualifications LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
var_export($r);
?>
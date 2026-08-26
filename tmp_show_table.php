<?php
require 'api/config/db.php';
$r = $conn->query('SHOW CREATE TABLE teacher_classroom_assignments')->fetch(PDO::FETCH_ASSOC);
var_export($r);
?>
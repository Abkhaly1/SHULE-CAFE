<?php
require 'api/config/db.php';
$stmt = $conn->prepare('SELECT id, school_id, code, name FROM subjects WHERE code=? LIMIT 10');
$stmt->execute(['B-MATH']);
$r = $stmt->fetchAll(PDO::FETCH_ASSOC);
var_export($r);

?>
<?php
require 'api/config/db.php';
$r = $conn->query('SHOW CREATE TABLE users')->fetch(PDO::FETCH_ASSOC);
var_export($r);
?>
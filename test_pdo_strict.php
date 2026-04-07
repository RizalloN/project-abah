<?php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=project_abah';
$username = 'root';
$password = '';
$options = [8 => 0, 3 => 2, 11 => 0, 17 => false, 20 => false];
$pdo = new PDO($dsn, $username, $password, $options);
$stmt = $pdo->query("SHOW VARIABLES LIKE 'local_infile'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

<?php
try {
    $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ];
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=project_abah', 'root', '', $options);
    $stmt = $pdo->prepare('select * from users where pn = ? limit 1');
    $stmt->execute([90179583]);
    print_r($stmt->fetchAll());
    echo "Prepared Statement OK\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

<?php
require 'config/database.php';
var_dump($pdo);
if ($pdo) {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' LIMIT 10");
    var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
}

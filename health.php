<?php
try {
    require 'config/database.php'; // sesuaikan

    $pdo->query("SELECT 1");

    http_response_code(200);
    echo "OK";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR";
}
<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "english-kids";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Kết nối database thất bại: ' . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->set_charset("utf8mb4");
?>
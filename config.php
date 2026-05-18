<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "englishforkids";

$conn = new mysqli($host, $user, $pass, $db);

header("Content-Type: application/json; charset=utf-8");

if ($conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Kết nối database thất bại: " . $conn->connect_error
    ]);
    exit;
}

$conn->set_charset("utf8mb4");
?>
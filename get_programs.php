<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$data = [];

$sql = "SELECT * FROM programs ORDER BY id DESC";
$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi SQL: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => (int)($row["id"] ?? 0),
        "name" => $row["name"] ?? "",
        "grade" => $row["grade"] ?? "",
        "subject" => $row["subject"] ?? "",
        "description" => $row["description"] ?? "",
        "img" => $row["img"] ?? "",
        "status" => $row["status"] ?? "Đã duyệt",
        "createdAt" => $row["created_at"] ?? ""
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
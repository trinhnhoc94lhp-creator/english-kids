<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$id = (int)($_GET['program_id'] ?? 0);
$source = trim($_GET['source'] ?? 'admin');

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu program_id"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($source === 'teacher') {
    $stmt = $conn->prepare("SELECT * FROM teacher_programs WHERE id = ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
}

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi prepare: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;

if (!$row) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy chương trình"
    ], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $conn->close();
    exit;
}

$data = [
    "id" => (int)($row["id"] ?? 0),
    "name" => $row["name"] ?? "",
    "grade" => $row["grade"] ?? "",
    "subject" => $row["subject"] ?? "",
    "description" => $row["description"] ?? "",
    "img" => $row["img"] ?? "",
    "status" => $row["status"] ?? "Đã duyệt",
    "level" => $row["level"] ?? "",
    "goal" => $row["goal"] ?? "",
    "source" => $source
];

echo json_encode([
    "success" => true,
    "data" => $data
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
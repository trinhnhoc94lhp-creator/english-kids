<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM qlgiaovien_teachers WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data) {
    echo json_encode([
        'success' => true,
        'teacher' => $data
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy'
    ]);
}
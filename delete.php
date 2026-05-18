<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$id = $_POST['id'] ?? 0;

$stmt = $conn->prepare("DELETE FROM qlgiaovien_teachers WHERE id=?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Đã xoá'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $stmt->error
    ]);
}
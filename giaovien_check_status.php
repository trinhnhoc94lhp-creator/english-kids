<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../db.php';

if (!isset($_SESSION['teacher_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$teacherId = (int) $_SESSION['teacher_id'];

$stmt = $conn->prepare("SELECT status FROM qlgiaovien_teachers WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$teacher) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy giáo viên'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'status' => $teacher['status'] ?: 'active'
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
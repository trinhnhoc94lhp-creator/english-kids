<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

if (!isset($conn) || !$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Không kết nối được database'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = trim($_POST['status'] ?? '');

$allowedStatuses = ['active', 'suspended', 'inactive'];

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu id giáo viên'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Trạng thái không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = $conn->prepare("SELECT id FROM qlgiaovien_teachers WHERE id = ? LIMIT 1");
if (!$check) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare check: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check->bind_param("i", $id);
$check->execute();
$result = $check->get_result();
$teacher = $result ? $result->fetch_assoc() : null;
$check->close();

if (!$teacher) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy giáo viên'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("UPDATE qlgiaovien_teachers SET status = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare update: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật trạng thái thành công'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi execute: ' . $stmt->error
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
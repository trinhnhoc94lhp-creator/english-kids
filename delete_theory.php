<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$teacherId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$role = $_SESSION["role"] ?? '';

if ($teacherId <= 0 || $role !== 'giaovien') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* CHẶN GIÁO VIÊN BỊ ĐÌNH CHỈ */
$checkTeacher = $conn->prepare("
    SELECT status
    FROM qlgiaovien_teachers
    WHERE id = ?
    LIMIT 1
");

if (!$checkTeacher) {
    echo json_encode([
        "success" => false,
        "message" => "Không kiểm tra được trạng thái giáo viên"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$checkTeacher->bind_param("i", $teacherId);
$checkTeacher->execute();
$teacherResult = $checkTeacher->get_result();
$teacher = $teacherResult ? $teacherResult->fetch_assoc() : null;
$checkTeacher->close();

if (!$teacher) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy giáo viên"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($teacher["status"] ?? "active") === "suspended") {
    echo json_encode([
        "success" => false,
        "message" => "Tài khoản giáo viên đang bị đình chỉ, không thể xóa lý thuyết"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu ID lý thuyết"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$get = $conn->prepare("SELECT file_path FROM theory_lessons WHERE id = ?");
if (!$get) {
    echo json_encode([
        "success" => false,
        "message" => "Không lấy được thông tin file"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$get->bind_param("i", $id);
$get->execute();
$result = $get->get_result();
$row = $result ? $result->fetch_assoc() : null;
$get->close();

if (!$row) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy lý thuyết"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("DELETE FROM theory_lessons WHERE id = ?");
if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh xóa lý thuyết"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if (!empty($row['file_path'])) {
        $full = dirname(__DIR__) . '/' . $row['file_path'];
        if (file_exists($full)) {
            @unlink($full);
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Đã xóa lý thuyết"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Xóa lý thuyết thất bại"
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
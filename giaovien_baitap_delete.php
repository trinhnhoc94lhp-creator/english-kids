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
        "message" => "Tài khoản giáo viên đang bị đình chỉ, không thể xoá bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$exerciseId = (int)($_POST["exercise_id"] ?? 0);

if ($exerciseId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu mã bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = $conn->prepare("
    SELECT id
    FROM teacher_exercises
    WHERE id = ? AND teacher_id = ?
    LIMIT 1
");

if (!$check) {
    echo json_encode([
        "success" => false,
        "message" => "Không kiểm tra được bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check->bind_param("ii", $exerciseId, $teacherId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$delQ = $conn->prepare("DELETE FROM teacher_exercise_questions WHERE exercise_id = ?");
if (!$delQ) {
    echo json_encode([
        "success" => false,
        "message" => "Không xoá được câu hỏi bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$delQ->bind_param("i", $exerciseId);
$delQ->execute();
$delQ->close();

$del = $conn->prepare("DELETE FROM teacher_exercises WHERE id = ? AND teacher_id = ?");
if (!$del) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh xoá bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$del->bind_param("ii", $exerciseId, $teacherId);

if ($del->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Đã xoá bài tập"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Không xoá được bài tập"
    ], JSON_UNESCAPED_UNICODE);
}
$del->close();

$conn->close();
?>
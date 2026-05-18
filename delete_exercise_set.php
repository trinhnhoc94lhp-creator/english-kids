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
        "message" => "Tài khoản giáo viên đang bị đình chỉ, không thể xóa bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu ID bộ bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
  Nếu bảng exercise_sets của bạn có teacher_id
  thì nên kiểm tra quyền sở hữu ở đây.
  Nếu chưa có cột teacher_id thì tạm bỏ qua bước này.
*/
$checkSet = $conn->prepare("SELECT id FROM exercise_sets WHERE id = ? LIMIT 1");
if (!$checkSet) {
    echo json_encode([
        "success" => false,
        "message" => "Không kiểm tra được bộ bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$checkSet->bind_param("i", $id);
$checkSet->execute();
$setResult = $checkSet->get_result();
$setRow = $setResult ? $setResult->fetch_assoc() : null;
$checkSet->close();

if (!$setRow) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy bộ bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* XÓA FILE ẢNH CÂU HỎI */
$get = $conn->prepare("SELECT question_image FROM exercise_questions WHERE exercise_set_id = ?");
if (!$get) {
    echo json_encode([
        "success" => false,
        "message" => "Không lấy được danh sách ảnh câu hỏi"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$get->bind_param("i", $id);
$get->execute();
$result = $get->get_result();

while ($row = $result->fetch_assoc()) {
    if (!empty($row['question_image'])) {
        $full = dirname(__DIR__) . '/' . $row['question_image'];
        if (file_exists($full)) {
            @unlink($full);
        }
    }
}
$get->close();

/* XÓA CÂU HỎI */
$delQ = $conn->prepare("DELETE FROM exercise_questions WHERE exercise_set_id = ?");
if (!$delQ) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh xóa câu hỏi"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$delQ->bind_param("i", $id);
$delQ->execute();
$delQ->close();

/* XÓA BỘ BÀI TẬP */
$delS = $conn->prepare("DELETE FROM exercise_sets WHERE id = ?");
if (!$delS) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh xóa bộ bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$delS->bind_param("i", $id);

if ($delS->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Đã xóa bộ bài tập"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Xóa bộ bài tập thất bại"
    ], JSON_UNESCAPED_UNICODE);
}

$delS->close();
$conn->close();
?>
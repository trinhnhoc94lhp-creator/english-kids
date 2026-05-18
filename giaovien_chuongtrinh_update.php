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

$programId = (int)($_POST["program_id"] ?? 0);
$img = $_POST["img"] ?? "";
$name = trim($_POST["name"] ?? "");
$grade = trim($_POST["grade"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$level = trim($_POST["level"] ?? "Nâng cao");
$description = trim($_POST["description"] ?? "");
$goal = trim($_POST["goal"] ?? "");
$lessonsJson = $_POST["lessons_json"] ?? "[]";

if ($programId <= 0 || $name === "" || $grade === "" || $subject === "") {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu cập nhật"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = $conn->prepare("
    SELECT id FROM teacher_programs
    WHERE id = ? AND teacher_id = ?
    LIMIT 1
");
$check->bind_param("ii", $programId, $teacherId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy chương trình để sửa"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$lessons = json_decode($lessonsJson, true);
if (!is_array($lessons)) $lessons = [];

$stmt = $conn->prepare("
    UPDATE teacher_programs
    SET img = ?, name = ?, grade = ?, subject = ?, level = ?, description = ?, goal = ?, status = 'Chờ phê duyệt', updated_at = NOW()
    WHERE id = ? AND teacher_id = ?
");
$stmt->bind_param("sssssssii", $img, $name, $grade, $subject, $level, $description, $goal, $programId, $teacherId);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Không cập nhật được chương trình"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->close();

$deleteStmt = $conn->prepare("DELETE FROM teacher_program_lessons WHERE program_id = ?");
$deleteStmt->bind_param("i", $programId);
$deleteStmt->execute();
$deleteStmt->close();

if (!empty($lessons)) {
    $lessonStmt = $conn->prepare("
        INSERT INTO teacher_program_lessons (program_id, lesson_name, lesson_description, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    foreach ($lessons as $lesson) {
        $lessonName = trim($lesson["name"] ?? "");
        $lessonDescription = trim($lesson["description"] ?? "");
        if ($lessonName === "") continue;

        $lessonStmt->bind_param("iss", $programId, $lessonName, $lessonDescription);
        $lessonStmt->execute();
    }
    $lessonStmt->close();
}

echo json_encode([
    "success" => true,
    "message" => "Đã cập nhật chương trình"
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
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

$img = $_POST["img"] ?? "";
$name = trim($_POST["name"] ?? "");
$grade = trim($_POST["grade"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$level = trim($_POST["level"] ?? "Nâng cao");
$description = trim($_POST["description"] ?? "");
$goal = trim($_POST["goal"] ?? "");
$lessonsJson = $_POST["lessons_json"] ?? "[]";

if ($name === "" || $grade === "" || $subject === "") {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu thông tin bắt buộc"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$lessons = json_decode($lessonsJson, true);
if (!is_array($lessons)) $lessons = [];

$stmt = $conn->prepare("
    INSERT INTO teacher_programs
    (teacher_id, img, name, grade, subject, level, description, goal, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Chờ phê duyệt', NOW(), NOW())
");
$stmt->bind_param("isssssss", $teacherId, $img, $name, $grade, $subject, $level, $description, $goal);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Không lưu được chương trình"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$programId = $stmt->insert_id;
$stmt->close();

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
    "message" => "Đã tạo chương trình mới và đang chờ phê duyệt"
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
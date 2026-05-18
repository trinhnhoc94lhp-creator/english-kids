<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$program_id = (int)($_POST['program_id'] ?? 0);
$semester = trim($_POST['semester'] ?? '');
$lesson_title = trim($_POST['lesson_title'] ?? '');
$exercise_set_title = trim($_POST['exercise_set_title'] ?? '');

if ($program_id <= 0 || $semester === '' || $lesson_title === '' || $exercise_set_title === '') {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("INSERT INTO exercise_sets (program_id, semester, lesson_title, exercise_set_title, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("isss", $program_id, $semester, $lesson_title, $exercise_set_title);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "exercise_set_id" => $conn->insert_id
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Tạo bộ bài tập thất bại"
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
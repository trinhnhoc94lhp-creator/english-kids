<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$studentId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$role = $_SESSION["role"] ?? '';

if ($studentId <= 0 || $role !== 'hocsinh') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$exerciseId = (int)($_POST["exercise_id"] ?? 0);
$assignmentTitle = trim($_POST["assignment_title"] ?? '');
$grade = trim($_POST["grade"] ?? '');
$sourceType = trim($_POST["source_type"] ?? 'teacher');
$correctCount = (int)($_POST["correct_count"] ?? 0);
$essayCount = (int)($_POST["essay_count"] ?? 0);
$score = (float)($_POST["score"] ?? 0);
$maxScore = (float)($_POST["max_score"] ?? 0);
$answersJson = $_POST["answers_json"] ?? '';

if ($exerciseId <= 0 || $assignmentTitle === '') {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu bài làm"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO student_assignment_results
    (student_id, exercise_id, assignment_title, grade, source_type, correct_count, essay_count, score, max_score, answers_json, submitted_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "iisssiidds",
    $studentId,
    $exerciseId,
    $assignmentTitle,
    $grade,
    $sourceType,
    $correctCount,
    $essayCount,
    $score,
    $maxScore,
    $answersJson
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Đã lưu bài làm thành công"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Không lưu được bài làm"
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
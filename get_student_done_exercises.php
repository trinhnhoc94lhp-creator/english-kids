<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$studentId = (int)($_GET['student_id'] ?? 0);

if ($studentId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu student_id",
        "exercises" => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
    SELECT id, exercise_id, exercise_name, subject, grade, score, max_score,
           correct_count, essay_count, submitted_at
    FROM student_exercise_results
    WHERE student_id = ?
    ORDER BY submitted_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi SQL: " . $conn->error,
        "exercises" => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

$exercises = [];

while ($row = $result->fetch_assoc()) {
    $exercises[] = [
        "id" => (int)$row["id"],
        "exerciseId" => $row["exercise_id"] ?? "",
        "exerciseName" => $row["exercise_name"] ?? "",
        "subject" => $row["subject"] ?? "",
        "grade" => $row["grade"] ?? "",
        "score" => (float)$row["score"],
        "maxScore" => (float)$row["max_score"],
        "correctCount" => (int)$row["correct_count"],
        "essayCount" => (int)$row["essay_count"],
        "submittedAt" => $row["submitted_at"] ?? ""
    ];
}

echo json_encode([
    "success" => true,
    "exercises" => $exercises
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
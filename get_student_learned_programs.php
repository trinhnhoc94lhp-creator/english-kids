<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$studentId = (int)($_GET['student_id'] ?? 0);

if ($studentId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu student_id",
        "programs" => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
    SELECT id, program_id, program_name, lesson_name, subject, grade, status, learned_at
    FROM student_learning_history
    WHERE student_id = ?
    ORDER BY learned_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi SQL: " . $conn->error,
        "programs" => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

$programs = [];

while ($row = $result->fetch_assoc()) {
    $programs[] = [
        "id" => (int)$row["id"],
        "programId" => (int)$row["program_id"],
        "programName" => $row["program_name"] ?? "",
        "lessonName" => $row["lesson_name"] ?? "",
        "subject" => $row["subject"] ?? "",
        "grade" => $row["grade"] ?? "",
        "status" => $row["status"] ?? "Đã học",
        "learnedAt" => $row["learned_at"] ?? ""
    ];
}

echo json_encode([
    "success" => true,
    "programs" => $programs
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
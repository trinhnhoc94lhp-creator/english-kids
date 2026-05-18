<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$program_id = (int)($_GET['program_id'] ?? 0);
$source = trim($_GET['source'] ?? 'admin');

if ($program_id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
Yêu cầu bảng exercise_sets nên có các cột:
- id
- program_id
- semester
- lesson_title
- exercise_set_title
- source
- created_at
*/

$hasSourceColumn = false;
$checkSource = $conn->query("SHOW COLUMNS FROM exercise_sets LIKE 'source'");
if ($checkSource && $checkSource->num_rows > 0) {
    $hasSourceColumn = true;
}

if ($hasSourceColumn) {
    $stmt = $conn->prepare("
        SELECT 
            es.*,
            COUNT(eq.id) AS total_questions
        FROM exercise_sets es
        LEFT JOIN exercise_questions eq ON es.id = eq.exercise_set_id
        WHERE es.program_id = ? AND es.source = ?
        GROUP BY es.id
        ORDER BY es.semester ASC, es.id DESC
    ");
    $stmt->bind_param("is", $program_id, $source);
} else {
    $stmt = $conn->prepare("
        SELECT 
            es.*,
            COUNT(eq.id) AS total_questions
        FROM exercise_sets es
        LEFT JOIN exercise_questions eq ON es.id = eq.exercise_set_id
        WHERE es.program_id = ?
        GROUP BY es.id
        ORDER BY es.semester ASC, es.id DESC
    ");
    $stmt->bind_param("i", $program_id);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => (int)($row["id"] ?? 0),
        "program_id" => (int)($row["program_id"] ?? 0),
        "semester" => $row["semester"] ?? "",
        "lesson_title" => $row["lesson_title"] ?? "",
        "exercise_set_title" => $row["exercise_set_title"] ?? "",
        "source" => $row["source"] ?? $source,
        "teacher_id" => (int)($row["teacher_id"] ?? 0),
        "created_at" => $row["created_at"] ?? "",
        "total_questions" => (int)($row["total_questions"] ?? 0)
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
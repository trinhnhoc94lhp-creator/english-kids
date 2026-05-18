<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$program_id = (int)($_GET['program_id'] ?? 0);

if ($program_id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT 
    es.id AS exercise_set_id,
    es.program_id,
    es.semester,
    es.lesson_title,
    es.exercise_set_title,
    eq.id AS question_id,
    eq.question_code,
    eq.question_type,
    eq.question_level,
    eq.question_text,
    eq.question_image,
    eq.options_json,
    eq.pairs_json
FROM exercise_sets es
LEFT JOIN exercise_questions eq ON es.id = eq.exercise_set_id
WHERE es.program_id = ?
ORDER BY es.id DESC, eq.id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $program_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
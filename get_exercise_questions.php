<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$exercise_set_id = (int)($_GET['exercise_set_id'] ?? 0);

if ($exercise_set_id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM exercise_questions WHERE exercise_set_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $exercise_set_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['options'] = $row['options_json'] ? json_decode($row['options_json'], true) : [];
    $row['pairs'] = $row['pairs_json'] ? json_decode($row['pairs_json'], true) : [];
    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
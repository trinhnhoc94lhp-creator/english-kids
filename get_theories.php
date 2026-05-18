<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$program_id = (int)($_GET['program_id'] ?? 0);

if ($program_id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM theory_lessons WHERE program_id = ? ORDER BY id DESC");
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
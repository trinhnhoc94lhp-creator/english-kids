<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$result = $conn->query("SELECT * FROM qlgiaovien_teachers ORDER BY id DESC");

$data = [];

while ($row = $result->fetch_assoc()) {
    $row['statusText'] = $row['status'] === 'suspended'
        ? 'Đình chỉ'
        : 'Đang hoạt động';
    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
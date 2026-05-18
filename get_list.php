<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$sql = "SELECT 
            id,
            name,
            phone,
            grade,
            birthday,
            gender,
            city,
            district,
            ward,
            address_detail,
            address,
            student_code,
            note,
            created_at
        FROM qlhocsinh_students
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi SQL: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$list = [];

while ($row = $result->fetch_assoc()) {
    $list[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"] ?? "",
        "phone" => $row["phone"] ?? "",
        "grade" => $row["grade"] ?? "",
        "birthday" => $row["birthday"] ?? "",
        "gender" => $row["gender"] ?? "",
        "city" => $row["city"] ?? "",
        "district" => $row["district"] ?? "",
        "ward" => $row["ward"] ?? "",
        "addressDetail" => $row["address_detail"] ?? "",
        "address" => $row["address"] ?? "",
        "studentCode" => $row["student_code"] ?? "",
        "note" => $row["note"] ?? "",
        "createdAt" => $row["created_at"] ?? ""
    ];
}

echo json_encode($list, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
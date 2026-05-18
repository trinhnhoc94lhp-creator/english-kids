<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu id học sinh"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
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
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy học sinh"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    "success" => true,
    "student" => [
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
    ]
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
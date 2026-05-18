<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$grade = trim($_POST['grade'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$city = trim($_POST['city'] ?? '');
$district = trim($_POST['district'] ?? '');
$ward = trim($_POST['ward'] ?? '');
$addressDetail = trim($_POST['addressDetail'] ?? '');
$address = trim($_POST['address'] ?? '');
$studentCode = trim($_POST['studentCode'] ?? '');
$note = trim($_POST['note'] ?? '');

if ($id <= 0 || $name === '' || $grade === '' || $studentCode === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu dữ liệu cập nhật'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = $conn->prepare("SELECT id FROM qlhocsinh_students WHERE student_code = ? AND id <> ? LIMIT 1");
if (!$check) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare check: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check->bind_param("si", $studentCode, $id);
$check->execute();
$result = $check->get_result();
$exists = $result ? $result->fetch_assoc() : null;
$check->close();

if ($exists) {
    echo json_encode([
        'success' => false,
        'message' => 'Mã học sinh đã tồn tại'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("
    UPDATE qlhocsinh_students
    SET name = ?, phone = ?, grade = ?, birthday = ?, gender = ?, city = ?, district = ?, ward = ?, address_detail = ?, address = ?, student_code = ?, note = ?
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare update: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param(
    "ssssssssssssi",
    $name,
    $phone,
    $grade,
    $birthday,
    $gender,
    $city,
    $district,
    $ward,
    $addressDetail,
    $address,
    $studentCode,
    $note,
    $id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật học sinh'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi execute: ' . $stmt->error
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
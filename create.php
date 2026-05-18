<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';

if (!isset($conn) || !$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Không kết nối được database'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

if ($name === '' || $grade === '' || $studentCode === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin bắt buộc'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = $conn->prepare("SELECT id FROM qlhocsinh_students WHERE student_code = ? LIMIT 1");
if (!$check) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare check: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check->bind_param("s", $studentCode);
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
    INSERT INTO qlhocsinh_students
    (name, phone, grade, birthday, gender, city, district, ward, address_detail, address, student_code, note, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare insert: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param(
    "ssssssssssss",
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
    $note
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Đã lưu học sinh'
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
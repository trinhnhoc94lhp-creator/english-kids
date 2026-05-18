<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

if (!isset($conn) || !$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Không kết nối được database'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$city = trim($_POST['city'] ?? '');
$district = trim($_POST['district'] ?? '');
$ward = trim($_POST['ward'] ?? '');
$addressDetail = trim($_POST['addressDetail'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu ID giáo viên'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($name === '' || $phone === '' || $email === '' || $birthday === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin bắt buộc'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "UPDATE qlgiaovien_teachers
        SET name = ?,
            subject = ?,
            city = ?,
            district = ?,
            ward = ?,
            address_detail = ?,
            full_address = ?,
            birthday = ?,
            gender = ?,
            phone = ?,
            email = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare update: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param(
    "sssssssssssi",
    $name,
    $subject,
    $city,
    $district,
    $ward,
    $addressDetail,
    $address,
    $birthday,
    $gender,
    $phone,
    $email,
    $id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật giáo viên thành công'
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
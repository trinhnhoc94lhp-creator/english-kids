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
$status = trim($_POST['status'] ?? 'active');

if ($name === '' || $phone === '' || $email === '' || $birthday === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin bắt buộc'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedStatuses = ['active', 'suspended', 'inactive'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}

$check = $conn->prepare("SELECT id FROM qlgiaovien_teachers WHERE email = ? LIMIT 1");
if (!$check) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare check: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();
$exists = $result ? $result->fetch_assoc() : null;
$check->close();

if ($exists) {
    echo json_encode([
        'success' => false,
        'message' => 'Email đã tồn tại'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$teacherCode = 'GV' . time();

$sql = "INSERT INTO qlgiaovien_teachers (
    teacher_code,
    teacherCode,
    name,
    subject,
    city,
    district,
    ward,
    address_detail,
    full_address,
    birthday,
    gender,
    phone,
    email,
    status_text,
    status,
    created_at,
    createdAt
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi prepare insert: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$statusText = ($status === 'suspended') ? 'Đình chỉ' : (($status === 'inactive') ? 'Ngưng hoạt động' : 'Đang hoạt động');

$fullAddress = $address;

$stmt->bind_param(
    "sssssssssssssss",
    $teacherCode,
    $teacherCode,
    $name,
    $subject,
    $city,
    $district,
    $ward,
    $addressDetail,
    $fullAddress,
    $birthday,
    $gender,
    $phone,
    $email,
    $statusText,
    $status
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Thêm giáo viên thành công'
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
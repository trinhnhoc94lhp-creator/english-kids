<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$name = trim($_POST['name'] ?? '');
$grade = trim($_POST['grade'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$level = trim($_POST['level'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($name === '' || $grade === '' || $subject === '' || $level === '') {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu thông tin bắt buộc"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$imgPath = '';

if (!empty($_FILES['image']['name'])) {
    $uploadDir = dirname(__DIR__) . '/uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) {
        echo json_encode([
            "success" => false,
            "message" => "Chỉ hỗ trợ ảnh jpg, jpeg, png, gif, webp"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $newName = time() . '_' . uniqid() . '.' . $ext;
    $target = $uploadDir . $newName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        echo json_encode([
            "success" => false,
            "message" => "Tải ảnh lên thất bại",
            "tmp_name" => $_FILES['image']['tmp_name'],
            "target" => $target,
            "upload_dir" => $uploadDir,
            "upload_dir_exists" => is_dir($uploadDir),
            "upload_dir_writable" => is_writable($uploadDir)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $imgPath = 'uploads/' . $newName;
}

$stmt = $conn->prepare("INSERT INTO programs (name, grade, subject, level, description, img, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Prepare lỗi: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("ssssss", $name, $grade, $subject, $level, $description, $imgPath);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Lưu thành công"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Execute lỗi: " . $stmt->error
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
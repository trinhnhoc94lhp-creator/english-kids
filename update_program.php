<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$grade = trim($_POST['grade'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$level = trim($_POST['level'] ?? '');
$description = trim($_POST['description'] ?? '');
$oldImg = trim($_POST['old_img'] ?? '');

if ($id <= 0 || $name === '' || $grade === '' || $subject === '' || $level === '') {
    echo json_encode(["success" => false, "message" => "Thiếu thông tin bắt buộc"], JSON_UNESCAPED_UNICODE);
    exit;
}

$imgPath = $oldImg;

if (!empty($_FILES['image']['name'])) {
    $uploadDir = dirname(__DIR__) . '/uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(["success" => false, "message" => "Chỉ hỗ trợ ảnh jpg, jpeg, png, gif, webp"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $newName = time() . '_' . uniqid() . '.' . $ext;
    $target = $uploadDir . $newName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        echo json_encode(["success" => false, "message" => "Tải ảnh lên thất bại"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $imgPath = 'uploads/' . $newName;

    if ($oldImg !== '') {
        $oldFile = dirname(__DIR__) . '/' . $oldImg;
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
    }
}

$stmt = $conn->prepare("UPDATE programs SET name=?, grade=?, subject=?, level=?, description=?, img=? WHERE id=?");
$stmt->bind_param("ssssssi", $name, $grade, $subject, $level, $description, $imgPath, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["success" => false, "message" => "Cập nhật dữ liệu thất bại"], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
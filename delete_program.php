<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "ID không hợp lệ"], JSON_UNESCAPED_UNICODE);
    exit;
}

$get = $conn->prepare("SELECT img FROM programs WHERE id=?");
$get->bind_param("i", $id);
$get->execute();
$result = $get->get_result();
$row = $result->fetch_assoc();
$get->close();

$stmt = $conn->prepare("DELETE FROM programs WHERE id=?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if (!empty($row['img'])) {
        $file = dirname(__DIR__) . '/' . $row['img'];
        if (file_exists($file)) {
            @unlink($file);
        }
    }
    echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["success" => false, "message" => "Xóa dữ liệu thất bại"], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$studentId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$role = $_SESSION["role"] ?? '';

if ($studentId <= 0 || $role !== 'hocsinh') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$theoryId = (int)($_POST["theory_id"] ?? 0);
$theoryTitle = trim($_POST["theory_title"] ?? '');
$programId = (int)($_POST["program_id"] ?? 0);
$grade = trim($_POST["grade"] ?? '');

if ($theoryId <= 0 || $theoryTitle === '') {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu lý thuyết"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* chống lưu trùng quá nhiều lần trong thời gian ngắn */
$stmt = $conn->prepare("
    SELECT id
    FROM student_theory_history
    WHERE student_id = ? AND theory_id = ?
    ORDER BY viewed_at DESC
    LIMIT 1
");
$stmt->bind_param("ii", $studentId, $theoryId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exists) {
    $stmt = $conn->prepare("
        INSERT INTO student_theory_history (student_id, theory_id, theory_title, program_id, grade, viewed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iisis", $studentId, $theoryId, $theoryTitle, $programId, $grade);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    "success" => true,
    "message" => "Đã ghi nhận lý thuyết"
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
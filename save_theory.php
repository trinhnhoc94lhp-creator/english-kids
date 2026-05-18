<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$role = $_SESSION["role"] ?? '';
$userId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;

$program_id  = (int)($_POST['program_id'] ?? 0);
$semester    = trim($_POST['semester'] ?? 'ky1');
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$source      = trim($_POST['source'] ?? 'admin');

/* =========================
   1. KIỂM TRA ĐĂNG NHẬP
========================= */
if ($role === '') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   2. PHÂN QUYỀN
   - Admin được lưu
   - Giáo viên được lưu nếu không bị đình chỉ
========================= */
if ($source === 'teacher' || $role === 'giaovien') {
    if ($role !== 'giaovien' || $userId <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Giáo viên chưa đăng nhập"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $checkTeacher = $conn->prepare("
        SELECT status
        FROM qlgiaovien_teachers
        WHERE id = ?
        LIMIT 1
    ");

    if (!$checkTeacher) {
        echo json_encode([
            "success" => false,
            "message" => "Không kiểm tra được trạng thái giáo viên: " . $conn->error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $checkTeacher->bind_param("i", $userId);
    $checkTeacher->execute();
    $teacherResult = $checkTeacher->get_result();
    $teacher = $teacherResult ? $teacherResult->fetch_assoc() : null;
    $checkTeacher->close();

    if (!$teacher) {
        echo json_encode([
            "success" => false,
            "message" => "Không tìm thấy giáo viên"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($teacher["status"] ?? "active") === "suspended") {
        echo json_encode([
            "success" => false,
            "message" => "Tài khoản giáo viên đang bị đình chỉ, không thể lưu lý thuyết"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    // ADMIN
    if ($role !== 'admin') {
        echo json_encode([
            "success" => false,
            "message" => "Bạn không có quyền lưu lý thuyết"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* =========================
   3. KIỂM TRA DỮ LIỆU
========================= */
if ($program_id <= 0 || $title === '') {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu bắt buộc"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_FILES['pdf_file'])) {
    echo json_encode([
        "success" => false,
        "message" => "Không nhận được file PDF"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_FILES['pdf_file']['error'] !== 0) {
    echo json_encode([
        "success" => false,
        "message" => "Upload lỗi, mã lỗi: " . $_FILES['pdf_file']['error']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   4. XỬ LÝ FILE PDF
========================= */
$file = $_FILES['pdf_file'];
$originalName = $file['name'] ?? '';
$tmpName = $file['tmp_name'] ?? '';
$fileSize = (int)($file['size'] ?? 0);

if ($originalName === '' || $tmpName === '') {
    echo json_encode([
        "success" => false,
        "message" => "Không nhận được file PDF hợp lệ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    echo json_encode([
        "success" => false,
        "message" => "Chỉ hỗ trợ file PDF"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($fileSize <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "File PDF không hợp lệ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   5. TẠO THƯ MỤC UPLOAD
========================= */
$uploadDir = __DIR__ . '/../uploads/theory/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode([
            "success" => false,
            "message" => "Không tạo được thư mục uploads/theory: " . $uploadDir
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$newName = 'theory_' . time() . '_' . uniqid() . '.pdf';
$targetPath = $uploadDir . $newName;
$filePath = 'uploads/theory/' . $newName;
$fileName = $originalName;

/* =========================
   6. LƯU FILE PDF
========================= */
if (!move_uploaded_file($tmpName, $targetPath)) {
    echo json_encode([
        "success" => false,
        "message" => "MOVE FAIL: " . $tmpName . " => " . $targetPath
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   7. LƯU DATABASE
========================= */
$stmt = $conn->prepare("
    INSERT INTO theory_lessons
    (program_id, semester, title, description, file_name, file_path, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh lưu lý thuyết: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param(
    "isssss",
    $program_id,
    $semester,
    $title,
    $description,
    $fileName,
    $filePath
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Lưu lý thuyết thành công"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Lưu lý thuyết thất bại: " . $stmt->error
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
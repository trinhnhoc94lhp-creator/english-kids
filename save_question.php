<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$role = $_SESSION["role"] ?? '';
$userId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;

$exercise_set_id = (int)($_POST['exercise_set_id'] ?? 0);
$question_code   = trim($_POST['question_code'] ?? '');
$question_type   = trim($_POST['question_type'] ?? '');
$question_level  = trim($_POST['question_level'] ?? '');
$question_text   = trim($_POST['question_text'] ?? '');
$options_json    = $_POST['options_json'] ?? '';
$pairs_json      = $_POST['pairs_json'] ?? '';
$source          = trim($_POST['source'] ?? 'admin');

/* =========================
   1. KIỂM TRA QUYỀN
========================= */
if ($role === '') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($source === 'teacher') {
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
            "message" => "Tài khoản giáo viên đang bị đình chỉ, không thể lưu câu hỏi"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    if ($role !== 'admin') {
        echo json_encode([
            "success" => false,
            "message" => "Admin chưa đăng nhập"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* =========================
   2. KIỂM TRA DỮ LIỆU
========================= */
if (
    $exercise_set_id <= 0 ||
    $question_code === '' ||
    $question_type === '' ||
    $question_level === '' ||
    $question_text === ''
) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu bắt buộc"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($question_type !== 'multiple' && $question_type !== 'matching') {
    echo json_encode([
        "success" => false,
        "message" => "Loại câu hỏi không hợp lệ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($question_type === 'multiple' && trim($options_json) === '') {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu danh sách đáp án"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($question_type === 'matching' && trim($pairs_json) === '') {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu câu nối"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   3. KIỂM TRA EXERCISE SET
========================= */
$setHasSource = false;
$checkSourceCol = $conn->query("SHOW COLUMNS FROM exercise_sets LIKE 'source'");
if ($checkSourceCol && $checkSourceCol->num_rows > 0) {
    $setHasSource = true;
}

if ($setHasSource) {
    $stmtCheck = $conn->prepare("
        SELECT id, source
        FROM exercise_sets
        WHERE id = ?
        LIMIT 1
    ");
} else {
    $stmtCheck = $conn->prepare("
        SELECT id
        FROM exercise_sets
        WHERE id = ?
        LIMIT 1
    ");
}

if (!$stmtCheck) {
    echo json_encode([
        "success" => false,
        "message" => "Không kiểm tra được bộ bài tập: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtCheck->bind_param("i", $exercise_set_id);
$stmtCheck->execute();
$setResult = $stmtCheck->get_result();
$setRow = $setResult ? $setResult->fetch_assoc() : null;
$stmtCheck->close();

if (!$setRow) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy bộ bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($setHasSource && !empty($setRow["source"]) && $setRow["source"] !== $source) {
    echo json_encode([
        "success" => false,
        "message" => "Bộ bài tập không thuộc đúng nguồn dữ liệu"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   4. UPLOAD ẢNH CÂU HỎI
========================= */
$questionImage = '';

if (!empty($_FILES['question_image']['name'])) {
    $uploadDir = __DIR__ . '/../uploads/questions/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            echo json_encode([
                "success" => false,
                "message" => "Không tạo được thư mục uploads/questions"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $ext = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) {
        echo json_encode([
            "success" => false,
            "message" => "Ảnh không hợp lệ"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $newName = 'question_' . time() . '_' . uniqid() . '.' . $ext;
    $target = $uploadDir . $newName;

    if (!move_uploaded_file($_FILES['question_image']['tmp_name'], $target)) {
        echo json_encode([
            "success" => false,
            "message" => "Tải ảnh câu hỏi thất bại"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $questionImage = 'uploads/questions/' . $newName;
}

/* =========================
   5. CHUẨN BỊ CỘT source NẾU CÓ
========================= */
$questionHasSource = false;
$checkQuestionSource = $conn->query("SHOW COLUMNS FROM exercise_questions LIKE 'source'");
if ($checkQuestionSource && $checkQuestionSource->num_rows > 0) {
    $questionHasSource = true;
}

/* =========================
   6. LƯU CÂU HỎI
========================= */
if ($questionHasSource) {
    $stmt = $conn->prepare("
        INSERT INTO exercise_questions
        (exercise_set_id, question_code, question_type, question_level, question_text, question_image, options_json, pairs_json, source, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
} else {
    $stmt = $conn->prepare("
        INSERT INTO exercise_questions
        (exercise_set_id, question_code, question_type, question_level, question_text, question_image, options_json, pairs_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
}

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh lưu câu hỏi: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($questionHasSource) {
    $stmt->bind_param(
        "issssssss",
        $exercise_set_id,
        $question_code,
        $question_type,
        $question_level,
        $question_text,
        $questionImage,
        $options_json,
        $pairs_json,
        $source
    );
} else {
    $stmt->bind_param(
        "isssssss",
        $exercise_set_id,
        $question_code,
        $question_type,
        $question_level,
        $question_text,
        $questionImage,
        $options_json,
        $pairs_json
    );
}

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Lưu câu hỏi thành công"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Lưu câu hỏi thất bại: " . $stmt->error
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
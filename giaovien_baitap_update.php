<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$teacherId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$role = $_SESSION["role"] ?? '';

if ($teacherId <= 0 || $role !== 'giaovien') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* CHẶN GIÁO VIÊN BỊ ĐÌNH CHỈ */
$checkTeacher = $conn->prepare("
    SELECT status
    FROM qlgiaovien_teachers
    WHERE id = ?
    LIMIT 1
");

if (!$checkTeacher) {
    echo json_encode([
        "success" => false,
        "message" => "Không kiểm tra được trạng thái giáo viên"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$checkTeacher->bind_param("i", $teacherId);
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
        "message" => "Tài khoản giáo viên đang bị đình chỉ, không thể cập nhật bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$exerciseId = (int)($_POST["exercise_id"] ?? 0);
$name = trim($_POST["name"] ?? "");
$className = trim($_POST["class_name"] ?? "");
$deadline = trim($_POST["deadline"] ?? "");
$totalScore = (int)($_POST["total_score"] ?? 0);
$desc = trim($_POST["description"] ?? "");
$questionsJson = $_POST["questions_json"] ?? "[]";

if ($exerciseId <= 0 || $name === "") {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu cập nhật"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$questions = json_decode($questionsJson, true);
if (!is_array($questions) || count($questions) === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Bài tập phải có ít nhất 1 câu hỏi"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = $conn->prepare("
    SELECT id
    FROM teacher_exercises
    WHERE id = ? AND teacher_id = ?
    LIMIT 1
");

if (!$check) {
    echo json_encode([
        "success" => false,
        "message" => "Không kiểm tra được bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$check->bind_param("ii", $exerciseId, $teacherId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy bài tập để sửa"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("
    UPDATE teacher_exercises
    SET name = ?, class_name = ?, deadline = ?, total_score = ?, description = ?, updated_at = NOW()
    WHERE id = ? AND teacher_id = ?
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh cập nhật bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("sssissi", $name, $className, $deadline, $totalScore, $desc, $exerciseId, $teacherId);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Không cập nhật được bài tập"
    ], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    exit;
}
$stmt->close();

$del = $conn->prepare("DELETE FROM teacher_exercise_questions WHERE exercise_id = ?");
if (!$del) {
    echo json_encode([
        "success" => false,
        "message" => "Không xoá được câu hỏi cũ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$del->bind_param("i", $exerciseId);
$del->execute();
$del->close();

$qStmt = $conn->prepare("
    INSERT INTO teacher_exercise_questions
    (exercise_id, type, question, option_a, option_b, option_c, option_d, correct_answer, pairs_json, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

if (!$qStmt) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh lưu câu hỏi"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach ($questions as $q) {
    $type = $q["type"] ?? "Trắc nghiệm";
    $question = trim($q["question"] ?? "");

    if ($question === "") {
        continue;
    }

    $optionA = $q["options"]["A"] ?? null;
    $optionB = $q["options"]["B"] ?? null;
    $optionC = $q["options"]["C"] ?? null;
    $optionD = $q["options"]["D"] ?? null;
    $correct = $q["correct"] ?? null;
    $pairsJson = isset($q["pairs"]) ? json_encode($q["pairs"], JSON_UNESCAPED_UNICODE) : null;

    $qStmt->bind_param(
        "issssssss",
        $exerciseId,
        $type,
        $question,
        $optionA,
        $optionB,
        $optionC,
        $optionD,
        $correct,
        $pairsJson
    );

    $qStmt->execute();
}
$qStmt->close();

echo json_encode([
    "success" => true,
    "message" => "Đã cập nhật bài tập"
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
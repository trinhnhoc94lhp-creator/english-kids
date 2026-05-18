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
        "message" => "Tài khoản giáo viên đang bị đình chỉ, không thể thêm bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim($_POST["name"] ?? "");
$className = trim($_POST["class_name"] ?? "");
$deadline = trim($_POST["deadline"] ?? "");
$totalScore = (int)($_POST["total_score"] ?? 0);
$desc = trim($_POST["description"] ?? "");
$questionsJson = $_POST["questions_json"] ?? "[]";

if ($name === "") {
    echo json_encode([
        "success" => false,
        "message" => "Vui lòng nhập tên bài tập"
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

$stmt = $conn->prepare("
    INSERT INTO teacher_exercises
    (teacher_id, name, class_name, deadline, total_score, description, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Không tạo được lệnh lưu bài tập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("isssis", $teacherId, $name, $className, $deadline, $totalScore, $desc);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Không lưu được bài tập"
    ], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    exit;
}

$exerciseId = $stmt->insert_id;
$stmt->close();

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
    "message" => "Đã lưu bài tập"
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
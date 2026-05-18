<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/db.php';

/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['student_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$studentId = (int)$_SESSION['student_id'];

/*
|--------------------------------------------------------------------------
| GET POST DATA
|--------------------------------------------------------------------------
*/
$exerciseId      = trim($_POST['exercise_id'] ?? '');
$title           = trim($_POST['assignment_title'] ?? '');
$grade           = trim($_POST['grade'] ?? '');
$sourceType      = trim($_POST['source_type'] ?? '');
$sourceLabel     = trim($_POST['source_label'] ?? '');
$correctCount    = (int)($_POST['correct_count'] ?? 0);
$essayCount      = (int)($_POST['essay_count'] ?? 0);
$score           = (float)($_POST['score'] ?? 0);
$maxScore        = (float)($_POST['max_score'] ?? 0);
$answersJson     = $_POST['answers_json'] ?? '{}';

/*
|--------------------------------------------------------------------------
| VALIDATE
|--------------------------------------------------------------------------
*/
if ($exerciseId === '' || $title === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin bài tập'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decodedAnswers = json_decode($answersJson, true);
if (!is_array($decodedAnswers)) {
    $decodedAnswers = [];
    $answersJson = json_encode($decodedAnswers, JSON_UNESCAPED_UNICODE);
}

$realExerciseId = 0;

if (strpos($exerciseId, 'admin_') === 0) {
    $sourceType = 'admin';
    if ($sourceLabel === '') $sourceLabel = 'QLbai admin';
    $realExerciseId = (int)str_replace('admin_', '', $exerciseId);
} elseif (strpos($exerciseId, 'teacher_') === 0) {
    $sourceType = 'teacher';
    if ($sourceLabel === '') $sourceLabel = 'QLbaitapgiaovien';
    $realExerciseId = (int)str_replace('teacher_', '', $exerciseId);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Mã bài tập không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($realExerciseId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID bài tập không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
|--------------------------------------------------------------------------
| OPTIONAL: CHECK SOURCE EXERCISE EXISTS
|--------------------------------------------------------------------------
*/
$tableName = $sourceType === 'teacher' ? 'qlbaitap_giaovien' : 'qlbai_admin';

$sqlCheck = "SELECT id FROM {$tableName} WHERE id = ? LIMIT 1";
$stmtCheck = $conn->prepare($sqlCheck);

if ($stmtCheck) {
    $stmtCheck->bind_param("i", $realExerciseId);
    $stmtCheck->execute();
    $exists = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$exists) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy bài tập gốc'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| CREATE TABLE NOTE
|--------------------------------------------------------------------------
| File này giả định bạn có bảng bai_lam_hoc_sinh với các cột:
| id
| student_id
| exercise_id
| source_type
| source_label
| assignment_title
| grade
| correct_count
| essay_count
| score
| max_score
| answers_json
| submitted_at
|
| Nếu bảng bạn khác tên/cột thì sửa phần INSERT bên dưới.
*/

/*
|--------------------------------------------------------------------------
| SAVE SUBMISSION
|--------------------------------------------------------------------------
*/
$sqlInsert = "
    INSERT INTO bai_lam_hoc_sinh (
        student_id,
        exercise_id,
        source_type,
        source_label,
        assignment_title,
        grade,
        correct_count,
        essay_count,
        score,
        max_score,
        answers_json,
        submitted_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
";

$stmtInsert = $conn->prepare($sqlInsert);

if (!$stmtInsert) {
    echo json_encode([
        'success' => false,
        'message' => 'Không chuẩn bị được truy vấn lưu bài: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtInsert->bind_param(
    "iissssiidds",
    $studentId,
    $realExerciseId,
    $sourceType,
    $sourceLabel,
    $title,
    $grade,
    $correctCount,
    $essayCount,
    $score,
    $maxScore,
    $answersJson
);

if ($stmtInsert->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Nộp bài thành công'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lưu bài làm thất bại: ' . $stmtInsert->error
    ], JSON_UNESCAPED_UNICODE);
}

$stmtInsert->close();
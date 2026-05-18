<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$studentId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$role = $_SESSION["role"] ?? '';

$selectedGrade = trim($_GET["grade"] ?? '');
$programId = (int)($_GET["program_id"] ?? 0);
$exerciseId = (int)($_GET["exercise_id"] ?? 0);

if ($studentId <= 0 || $role !== 'hocsinh') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Lấy thông tin học sinh */
$stmt = $conn->prepare("
    SELECT id, name, grade, student_code
    FROM qlhocsinh_students
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy học sinh"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$grade = $selectedGrade !== '' ? $selectedGrade : ($student["grade"] ?? "Lớp 1");

/* Lấy danh sách chương trình từ bảng programs */
$programs = [];
$stmt = $conn->prepare("
    SELECT id, name, subject, grade, description, img, status, created_at
    FROM programs
    WHERE grade = ? OR grade = 'Tất cả'
    ORDER BY id DESC
");
$stmt->bind_param("s", $grade);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $programs[] = [
        "id" => (int)$row["id"],
        "title" => $row["name"] ?? "Chương trình chưa đặt tên",
        "subject" => $row["subject"] ?? "Tiếng Anh",
        "grade" => $row["grade"] ?? $grade,
        "desc" => $row["description"] ?? "Chưa có mô tả chương trình.",
        "img" => $row["img"] ?? "",
        "status" => $row["status"] ?? "Đang mở",
        "createdAt" => $row["created_at"] ?? ""
    ];
}
$stmt->close();

/* Chọn chương trình hiện tại */
if ($programId <= 0 && count($programs) > 0) {
    $programId = (int)$programs[0]["id"];
}

$currentProgram = null;
foreach ($programs as $p) {
    if ((int)$p["id"] === $programId) {
        $currentProgram = $p;
        break;
    }
}

/* Lấy lý thuyết từ bảng nhaplieu_theory */
$theories = [];
if ($programId > 0) {
    $stmt = $conn->prepare("
        SELECT id, semester, title, description, pdf_url, created_at
        FROM nhaplieu_theory
        WHERE program_id = ? AND (grade = ? OR grade = 'Tất cả')
        ORDER BY id ASC
    ");
    $stmt->bind_param("is", $programId, $grade);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $theories[] = [
            "id" => (int)$row["id"],
            "semester" => $row["semester"] ?? "ky1",
            "title" => $row["title"] ?? "Bài lý thuyết",
            "description" => $row["description"] ?? "",
            "pdfUrl" => $row["pdf_url"] ?? "",
            "createdAt" => $row["created_at"] ?? ""
        ];
    }
    $stmt->close();
}

/* Lấy bài tập từ bảng nhaplieu_exercises + nhaplieu_questions */
$exercises = [];
$stmt = $conn->prepare("
    SELECT e.id, e.program_id, e.grade, e.subject, e.title, e.description, e.deadline, e.total_score, e.created_at,
           COUNT(q.id) AS total_questions
    FROM nhaplieu_exercises e
    LEFT JOIN nhaplieu_questions q ON e.id = q.exercise_id
    WHERE (e.grade = ? OR e.grade = 'Tất cả')
      AND (? = 0 OR e.program_id = ?)
    GROUP BY e.id
    ORDER BY e.id DESC
");
$stmt->bind_param("sii", $grade, $programId, $programId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $itemExerciseId = (int)$row["id"];

    $qStmt = $conn->prepare("
        SELECT id, type, question, option_a, option_b, option_c, option_d, correct_answer
        FROM nhaplieu_questions
        WHERE exercise_id = ?
        ORDER BY id ASC
    ");
    $qStmt->bind_param("i", $itemExerciseId);
    $qStmt->execute();
    $qResult = $qStmt->get_result();

    $questions = [];
    $index = 1;

    while ($q = $qResult->fetch_assoc()) {
        $type = trim($q["type"] ?? "Trắc nghiệm");
        $questionItem = [
            "index" => $index,
            "type" => $type,
            "question" => $q["question"] ?? "",
            "correct" => $q["correct_answer"] ?? "",
            "details" => ""
        ];

        if ($type === "Trắc nghiệm") {
            $questionItem["options"] = [
                "A" => $q["option_a"] ?? "",
                "B" => $q["option_b"] ?? "",
                "C" => $q["option_c"] ?? "",
                "D" => $q["option_d"] ?? ""
            ];
        } else {
            $questionItem["options"] = [];
        }

        $questions[] = $questionItem;
        $index++;
    }
    $qStmt->close();

    $exercises[] = [
        "id" => $itemExerciseId,
        "title" => $row["title"] ?? "Bài tập chưa đặt tên",
        "desc" => $row["description"] ?? "Chưa có mô tả bài tập.",
        "grade" => $row["grade"] ?? $grade,
        "subject" => $row["subject"] ?? "Tiếng Anh",
        "deadline" => $row["deadline"] ?? "Chưa đặt hạn",
        "totalScore" => (int)($row["total_score"] ?? count($questions)),
        "createdAt" => $row["created_at"] ?? "",
        "questions" => $questions,
        "source" => "nhaplieu"
    ];
}
$stmt->close();

/* Chọn bài tập hiện tại */
$currentExercise = null;
if ($exerciseId <= 0 && count($exercises) > 0) {
    $exerciseId = (int)$exercises[0]["id"];
}
foreach ($exercises as $e) {
    if ((int)$e["id"] === $exerciseId) {
        $currentExercise = $e;
        break;
    }
}

echo json_encode([
    "success" => true,
    "student" => [
        "id" => (int)$student["id"],
        "name" => $student["name"] ?? "",
        "grade" => $student["grade"] ?? "",
        "studentCode" => $student["student_code"] ?? ""
    ],
    "selectedGrade" => $grade,
    "programs" => $programs,
    "currentProgram" => $currentProgram,
    "theories" => $theories,
    "exercises" => $exercises,
    "currentExercise" => $currentExercise
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
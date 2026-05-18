<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$studentId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$role = $_SESSION["role"] ?? '';
$selectedGrade = trim($_GET["grade"] ?? '');

if ($studentId <= 0 || $role !== 'hocsinh') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

$assignments = [];

$stmt = $conn->prepare("
    SELECT e.id, e.name, e.subject, e.grade, e.description, e.created_at,
           COUNT(q.id) AS total_questions
    FROM qlbai_exercises e
    LEFT JOIN qlbai_questions q ON e.id = q.exercise_id
    WHERE e.grade = ?
    GROUP BY e.id
    ORDER BY e.id DESC
");
$stmt->bind_param("s", $grade);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $exerciseId = (int)$row["id"];

    $qStmt = $conn->prepare("
        SELECT id, type, question, option_a, option_b, option_c, option_d, correct_answer
        FROM qlbai_questions
        WHERE exercise_id = ?
        ORDER BY id ASC
    ");
    $qStmt->bind_param("i", $exerciseId);
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
        } elseif ($type === "Đúng/Sai") {
            $questionItem["options"] = [];
        } elseif ($type === "Điền chỗ trống") {
            $questionItem["options"] = [];
        } elseif ($type === "Tự luận") {
            $questionItem["options"] = [];
        } else {
            $questionItem["options"] = [];
        }

        $questions[] = $questionItem;
        $index++;
    }
    $qStmt->close();

    $assignments[] = [
        "id" => $exerciseId,
        "title" => $row["name"] ?? "Bài tập chưa đặt tên",
        "desc" => $row["description"] ?? "Chưa có mô tả bài tập.",
        "grade" => $row["grade"] ?? $grade,
        "subject" => $row["subject"] ?? "Tiếng Anh",
        "deadline" => "Chưa đặt hạn",
        "totalScore" => count($questions),
        "createdAt" => $row["created_at"] ?? "",
        "questions" => $questions,
        "source" => "teacher"
    ];
}
$stmt->close();

echo json_encode([
    "success" => true,
    "student" => [
        "id" => (int)$student["id"],
        "name" => $student["name"] ?? "",
        "grade" => $student["grade"] ?? "",
        "studentCode" => $student["student_code"] ?? ""
    ],
    "selectedGrade" => $grade,
    "assignments" => $assignments
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
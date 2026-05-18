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

/* Thông tin học sinh */
$stmt = $conn->prepare("
    SELECT id, student_code, name, grade, birthday, gender, address, phone, created_at
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

/* Chương trình hiện có theo khối */
$programCount = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM programs
    WHERE grade = ? OR grade = 'Tất cả'
");
$stmt->bind_param("s", $grade);
$stmt->execute();
$programCount = (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

/* Số bài lý thuyết đã học */
$theoryViewedCount = 0;
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT theory_id) AS total
    FROM student_theory_history
    WHERE student_id = ? AND (grade = ? OR grade IS NULL OR grade = '')
");
$stmt->bind_param("is", $studentId, $grade);
$stmt->execute();
$theoryViewedCount = (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

/* Danh sách bài lý thuyết đã học gần đây */
$theoryHistory = [];
$stmt = $conn->prepare("
    SELECT theory_id, theory_title, grade, viewed_at
    FROM student_theory_history
    WHERE student_id = ? AND (grade = ? OR grade IS NULL OR grade = '')
    ORDER BY viewed_at DESC
    LIMIT 10
");
$stmt->bind_param("is", $studentId, $grade);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $theoryHistory[] = [
        "theoryId" => (int)$row["theory_id"],
        "title" => $row["theory_title"] ?? "",
        "grade" => $row["grade"] ?? "",
        "viewedAt" => $row["viewed_at"] ?? ""
    ];
}
$stmt->close();

/* Danh sách bài tập đã nộp */
$exerciseResults = [];
$stmt = $conn->prepare("
    SELECT id, exercise_id, assignment_title, grade, correct_count, essay_count, score, max_score, submitted_at
    FROM student_assignment_results
    WHERE student_id = ? AND (grade = ? OR grade IS NULL OR grade = '')
    ORDER BY submitted_at DESC
");
$stmt->bind_param("is", $studentId, $grade);
$stmt->execute();
$result = $stmt->get_result();

$totalExercisesDone = 0;
$totalScore = 0;
$totalMaxScore = 0;

while ($row = $result->fetch_assoc()) {
    $score = (float)($row["score"] ?? 0);
    $maxScore = (float)($row["max_score"] ?? 0);

    $exerciseResults[] = [
        "id" => (int)$row["id"],
        "exerciseId" => (int)$row["exercise_id"],
        "title" => $row["assignment_title"] ?? "",
        "grade" => $row["grade"] ?? "",
        "correctCount" => (int)($row["correct_count"] ?? 0),
        "essayCount" => (int)($row["essay_count"] ?? 0),
        "score" => $score,
        "maxScore" => $maxScore,
        "submittedAt" => $row["submitted_at"] ?? ""
    ];

    $totalExercisesDone++;
    $totalScore += $score;
    $totalMaxScore += $maxScore;
}
$stmt->close();

$avgScore10 = 0;
if ($totalExercisesDone > 0 && $totalMaxScore > 0) {
    $avgScore10 = round(($totalScore / $totalMaxScore) * 10, 1);
}

/* Timeline */
$timeline = [];

/* từ bài lý thuyết */
foreach ($theoryHistory as $item) {
    $timeline[] = [
        "type" => "theory",
        "title" => "Đã học lý thuyết",
        "desc" => $item["title"],
        "date" => $item["viewedAt"]
    ];
}

/* từ bài tập */
foreach ($exerciseResults as $item) {
    $timeline[] = [
        "type" => "exercise",
        "title" => "Đã nộp bài tập",
        "desc" => $item["title"] . " (" . $item["score"] . "/" . $item["maxScore"] . ")",
        "date" => $item["submittedAt"]
    ];
}

/* sort timeline mới nhất */
usort($timeline, function($a, $b){
    return strcmp($b["date"], $a["date"]);
});
$timeline = array_slice($timeline, 0, 8);

echo json_encode([
    "success" => true,
    "student" => [
        "id" => (int)$student["id"],
        "studentCode" => $student["student_code"] ?? "",
        "name" => $student["name"] ?? "",
        "grade" => $student["grade"] ?? "",
        "birthday" => $student["birthday"] ?? "",
        "gender" => $student["gender"] ?? "",
        "address" => $student["address"] ?? "",
        "phone" => $student["phone"] ?? "",
        "createdAt" => $student["created_at"] ?? ""
    ],
    "selectedGrade" => $grade,
    "stats" => [
        "programCount" => $programCount,
        "theoryViewedCount" => $theoryViewedCount,
        "exerciseDoneCount" => $totalExercisesDone,
        "averageScore10" => $avgScore10
    ],
    "exerciseResults" => $exerciseResults,
    "theoryHistory" => $theoryHistory,
    "timeline" => $timeline
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
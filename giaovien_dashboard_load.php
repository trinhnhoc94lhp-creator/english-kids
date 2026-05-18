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

/* Thông tin giáo viên */
$stmt = $conn->prepare("
    SELECT id, teacher_code, name, email, phone, subject
    FROM qlgiaovien_teachers
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy giáo viên"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Chương trình của giáo viên */
$programs = [];
$totalPrograms = 0;

$stmt = $conn->prepare("
    SELECT id, name, subject, grade, description, status, created_at
    FROM teacher_programs
    WHERE teacher_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $programs[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"] ?? "",
        "subject" => $row["subject"] ?? "",
        "grade" => $row["grade"] ?? "",
        "description" => $row["description"] ?? "",
        "status" => $row["status"] ?? "Chờ duyệt",
        "createdAt" => $row["created_at"] ?? ""
    ];
    $totalPrograms++;
}
$stmt->close();

/* Bài tập của giáo viên */
$exercises = [];
$totalExercises = 0;
$totalQuestions = 0;

$stmt = $conn->prepare("
    SELECT e.id, e.name, e.subject, e.grade, e.description, e.created_at,
           COUNT(q.id) AS total_questions
    FROM teacher_exercises e
    LEFT JOIN teacher_questions q ON e.id = q.exercise_id
    WHERE e.teacher_id = ?
    GROUP BY e.id
    ORDER BY e.id DESC
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $questionCount = (int)($row["total_questions"] ?? 0);

    $exercises[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"] ?? "",
        "subject" => $row["subject"] ?? "",
        "grade" => $row["grade"] ?? "",
        "description" => $row["description"] ?? "",
        "questionCount" => $questionCount,
        "createdAt" => $row["created_at"] ?? ""
    ];

    $totalExercises++;
    $totalQuestions += $questionCount;
}
$stmt->close();

/* Hoạt động gần đây */
$activities = [];

foreach (array_slice($programs, 0, 4) as $p) {
    $activities[] = [
        "type" => "program",
        "icon" => "📚",
        "title" => "Cập nhật chương trình",
        "desc" => ($p["name"] ?: "Một chương trình") . " • " . ($p["grade"] ?: "Chưa rõ khối"),
        "time" => $p["createdAt"] ?: "Gần đây"
    ];
}

foreach (array_slice($exercises, 0, 4) as $e) {
    $activities[] = [
        "type" => "exercise",
        "icon" => "📝",
        "title" => "Tạo bài tập",
        "desc" => ($e["name"] ?: "Một bài tập") . " • " . $e["questionCount"] . " câu hỏi",
        "time" => $e["createdAt"] ?: "Gần đây"
    ];
}

usort($activities, function ($a, $b) {
    return strcmp($b["time"], $a["time"]);
});
$activities = array_slice($activities, 0, 6);

echo json_encode([
    "success" => true,
    "teacher" => [
        "id" => (int)$teacher["id"],
        "teacherCode" => $teacher["teacher_code"] ?? "",
        "name" => $teacher["name"] ?? "",
        "email" => $teacher["email"] ?? "",
        "phone" => $teacher["phone"] ?? "",
        "subject" => $teacher["subject"] ?? ""
    ],
    "stats" => [
        "totalPrograms" => $totalPrograms,
        "totalExercises" => $totalExercises,
        "totalQuestions" => $totalQuestions,
        "teacherStatus" => "Sẵn sàng"
    ],
    "programs" => $programs,
    "exercises" => $exercises,
    "activities" => $activities
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
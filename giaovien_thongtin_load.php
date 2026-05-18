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

/* Lấy thông tin giáo viên */
$stmt = $conn->prepare("
    SELECT id, teacher_code, name, email, phone, subject, birthday, gender, address, created_at
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

/* Thống kê chương trình */
$totalPrograms = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM teacher_programs
    WHERE teacher_id = ?
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$totalPrograms = (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

/* Thống kê bài tập */
$totalExercises = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM teacher_exercises
    WHERE teacher_id = ?
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$totalExercises = (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

/* Thống kê tổng câu hỏi */
$totalQuestions = 0;
$stmt = $conn->prepare("
    SELECT COUNT(q.id) AS total
    FROM teacher_exercises e
    LEFT JOIN teacher_exercise_questions q ON e.id = q.exercise_id
    WHERE e.teacher_id = ?
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$totalQuestions = (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

/* Danh sách chương trình gần đây */
$recentPrograms = [];
$stmt = $conn->prepare("
    SELECT id, name, grade, subject, status, created_at
    FROM teacher_programs
    WHERE teacher_id = ?
    ORDER BY id DESC
    LIMIT 5
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentPrograms[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"] ?? "",
        "grade" => $row["grade"] ?? "",
        "subject" => $row["subject"] ?? "",
        "status" => $row["status"] ?? "",
        "createdAt" => $row["created_at"] ?? ""
    ];
}
$stmt->close();

/* Danh sách bài tập gần đây */
$recentExercises = [];
$stmt = $conn->prepare("
    SELECT e.id, e.name, e.class_name, e.total_score, e.created_at, COUNT(q.id) AS question_count
    FROM teacher_exercises e
    LEFT JOIN teacher_exercise_questions q ON e.id = q.exercise_id
    WHERE e.teacher_id = ?
    GROUP BY e.id
    ORDER BY e.id DESC
    LIMIT 5
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentExercises[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"] ?? "",
        "className" => $row["class_name"] ?? "",
        "totalScore" => (int)($row["total_score"] ?? 0),
        "questionCount" => (int)($row["question_count"] ?? 0),
        "createdAt" => $row["created_at"] ?? ""
    ];
}
$stmt->close();

/* Hoạt động gần đây */
$activities = [];

foreach ($recentPrograms as $item) {
    $activities[] = [
        "icon" => "📘",
        "title" => "Tạo / cập nhật chương trình",
        "desc" => ($item["name"] ?: "Chương trình") . " • " . ($item["grade"] ?: "Chưa rõ khối"),
        "time" => $item["createdAt"] ?: "Gần đây"
    ];
}

foreach ($recentExercises as $item) {
    $activities[] = [
        "icon" => "📝",
        "title" => "Tạo bài tập",
        "desc" => ($item["name"] ?: "Bài tập") . " • " . $item["questionCount"] . " câu hỏi",
        "time" => $item["createdAt"] ?: "Gần đây"
    ];
}

usort($activities, function($a, $b){
    return strcmp($b["time"], $a["time"]);
});
$activities = array_slice($activities, 0, 8);

/* Nhận xét tự động */
$comment = "Giáo viên đang sử dụng hệ thống ổn định.";
if ($totalPrograms === 0 && $totalExercises === 0) {
    $comment = "Giáo viên chưa tạo chương trình hoặc bài tập nào. Hãy bắt đầu thêm dữ liệu để quản lý lớp học hiệu quả hơn.";
} elseif ($totalPrograms > 0 && $totalExercises === 0) {
    $comment = "Giáo viên đã có chương trình học, nên bổ sung thêm bài tập để học sinh luyện tập tốt hơn.";
} elseif ($totalPrograms > 0 && $totalExercises > 0) {
    $comment = "Giáo viên đã xây dựng được nội dung giảng dạy và bài tập tương đối đầy đủ trên hệ thống.";
}

echo json_encode([
    "success" => true,
    "teacher" => [
        "id" => (int)$teacher["id"],
        "teacherCode" => $teacher["teacher_code"] ?? "",
        "name" => $teacher["name"] ?? "",
        "email" => $teacher["email"] ?? "",
        "phone" => $teacher["phone"] ?? "",
        "subject" => $teacher["subject"] ?? "",
        "birthday" => $teacher["birthday"] ?? "",
        "gender" => $teacher["gender"] ?? "",
        "address" => $teacher["address"] ?? "",
        "createdAt" => $teacher["created_at"] ?? ""
    ],
    "stats" => [
        "totalPrograms" => $totalPrograms,
        "totalExercises" => $totalExercises,
        "totalQuestions" => $totalQuestions
    ],
    "recentPrograms" => $recentPrograms,
    "recentExercises" => $recentExercises,
    "activities" => $activities,
    "comment" => $comment
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
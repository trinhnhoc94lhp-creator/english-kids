<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'db.php';

    if (!isset($conn)) {
        echo json_encode([
            "success" => false,
            "message" => "db.php không tạo ra biến \$conn"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $studentName = $_SESSION["user_name"] ?? "";
    $studentId = (int)($_SESSION["user_id"] ?? 0);
    $selectedGrade = trim($_GET["grade"] ?? '');

    if ($studentId <= 0) {
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

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "Lỗi prepare học sinh: " . $conn->error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

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

    $programs = [];
    $stmt = $conn->prepare("
        SELECT id, name, subject, grade, description, img, status, created_at
        FROM programs
        WHERE grade = ?
        ORDER BY id DESC
        LIMIT 6
    ");

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "Lỗi prepare chương trình: " . $conn->error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $programs[] = [
            "id" => (int)$row["id"],
            "name" => $row["name"] ?? "",
            "subject" => $row["subject"] ?? "",
            "grade" => $row["grade"] ?? "",
            "description" => $row["description"] ?? "",
            "img" => $row["img"] ?? "",
            "status" => $row["status"] ?? "Đã duyệt",
            "createdAt" => $row["created_at"] ?? ""
        ];
    }
    $stmt->close();

    $exercises = [];
    $stmt = $conn->prepare("
        SELECT e.id, e.name, e.subject, e.grade, e.description, e.created_at,
               COUNT(q.id) AS total_questions
        FROM qlbai_exercises e
        LEFT JOIN qlbai_questions q ON e.id = q.exercise_id
        WHERE e.grade = ?
        GROUP BY e.id
        ORDER BY e.id DESC
        LIMIT 6
    ");

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "Lỗi prepare bài tập: " . $conn->error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $exercises[] = [
            "id" => (int)$row["id"],
            "name" => $row["name"] ?? "",
            "subject" => $row["subject"] ?? "",
            "grade" => $row["grade"] ?? "",
            "description" => $row["description"] ?? "",
            "createdAt" => $row["created_at"] ?? "",
            "totalQuestions" => (int)$row["total_questions"]
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
        "programs" => $programs,
        "exercises" => $exercises
    ], JSON_UNESCAPED_UNICODE);

    $conn->close();

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "message" => "PHP Error: " . $e->getMessage(),
        "line" => $e->getLine(),
        "file" => $e->getFile()
    ], JSON_UNESCAPED_UNICODE);
}
?>
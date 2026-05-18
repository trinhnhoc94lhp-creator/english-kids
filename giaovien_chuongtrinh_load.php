<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$teacherId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$role = $_SESSION["role"] ?? '';

if ($teacherId <= 0 || $role !== 'giaovien') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập giáo viên"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$adminPrograms = [];
$teacherPrograms = [];

/* =========================================================
   1. LẤY CHƯƠNG TRÌNH DO ADMIN TẠO
   Bảng: programs
========================================================= */
$sqlAdmin = "SELECT * FROM programs ORDER BY id DESC";
$resultAdmin = $conn->query($sqlAdmin);

if (!$resultAdmin) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi SQL admin: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

while ($row = $resultAdmin->fetch_assoc()) {
    $adminPrograms[] = [
        "id" => "admin_" . (int)($row["id"] ?? 0),
        "realId" => (int)($row["id"] ?? 0),
        "name" => $row["name"] ?? "",
        "grade" => $row["grade"] ?? "",
        "subject" => $row["subject"] ?? "",
        "level" => $row["level"] ?? "",
        "description" => $row["description"] ?? "",
        "goal" => $row["goal"] ?? "",
        "img" => $row["img"] ?? "",
        "status" => $row["status"] ?? "Đã duyệt",
        "date" => !empty($row["created_at"]) ? date("d/m/Y", strtotime($row["created_at"])) : "",
        "source" => "admin",
        "lessons" => []
    ];
}

/* =========================================================
   2. LẤY CHƯƠNG TRÌNH DO GIÁO VIÊN TẠO
   Bảng: teacher_programs
========================================================= */
$sqlTeacher = "SELECT * FROM teacher_programs WHERE teacher_id = ? ORDER BY id DESC";
$stmtTeacher = $conn->prepare($sqlTeacher);

if ($stmtTeacher) {
    $stmtTeacher->bind_param("i", $teacherId);
    $stmtTeacher->execute();
    $resultTeacher = $stmtTeacher->get_result();

    while ($row = $resultTeacher->fetch_assoc()) {
        $programId = (int)($row["id"] ?? 0);

        $lessons = [];

        /* Nếu có bảng lesson của giáo viên thì lấy luôn */
        $lessonStmt = $conn->prepare("SELECT * FROM teacher_program_lessons WHERE program_id = ? ORDER BY id ASC");
        if ($lessonStmt) {
            $lessonStmt->bind_param("i", $programId);
            $lessonStmt->execute();
            $lessonResult = $lessonStmt->get_result();

            while ($lesson = $lessonResult->fetch_assoc()) {
                $lessons[] = [
                    "name" => $lesson["lesson_name"] ?? ($lesson["name"] ?? ""),
                    "description" => $lesson["lesson_description"] ?? ($lesson["description"] ?? "")
                ];
            }

            $lessonStmt->close();
        }

        $teacherPrograms[] = [
            "id" => "teacher_" . $programId,
            "realId" => $programId,
            "name" => $row["name"] ?? "",
            "grade" => $row["grade"] ?? "",
            "subject" => $row["subject"] ?? "",
            "level" => $row["level"] ?? "",
            "description" => $row["description"] ?? "",
            "goal" => $row["goal"] ?? "",
            "img" => $row["img"] ?? "",
            "status" => $row["status"] ?? "Chờ phê duyệt",
            "date" => !empty($row["created_at"]) ? date("d/m/Y", strtotime($row["created_at"])) : "",
            "source" => "teacher",
            "lessons" => $lessons
        ];
    }

    $stmtTeacher->close();
}

echo json_encode([
    "success" => true,
    "adminPrograms" => $adminPrograms,
    "teacherPrograms" => $teacherPrograms
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
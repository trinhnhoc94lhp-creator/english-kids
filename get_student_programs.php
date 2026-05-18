<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$studentId = isset($_SESSION["user_id"]) ? (int)($_SESSION["user_id"] ?? 0) : 0;
$role = $_SESSION["role"] ?? '';
$selectedGrade = trim($_GET["grade"] ?? '');

if ($studentId <= 0 || $role !== 'hocsinh') {
    echo json_encode([
        "success" => false,
        "message" => "Chưa đăng nhập"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function tableExists($conn, $tableName) {
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $tableName, $columnName) {
    $safeTable = $conn->real_escape_string($tableName);
    $safeCol = $conn->real_escape_string($columnName);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
    return $res && $res->num_rows > 0;
}

function normalizeGrade($value) {
    $v = trim((string)$value);
    $lower = mb_strtolower($v, 'UTF-8');

    if ($lower === 'khối 1' || $lower === 'lớp 1' || $lower === 'lop 1' || $lower === '1') return 'Lớp 1';
    if ($lower === 'khối 2' || $lower === 'lớp 2' || $lower === 'lop 2' || $lower === '2') return 'Lớp 2';
    if ($lower === 'khối 3' || $lower === 'lớp 3' || $lower === 'lop 3' || $lower === '3') return 'Lớp 3';
    if ($lower === 'khối 4' || $lower === 'lớp 4' || $lower === 'lop 4' || $lower === '4') return 'Lớp 4';
    if ($lower === 'khối 5' || $lower === 'lớp 5' || $lower === 'lop 5' || $lower === '5') return 'Lớp 5';
    if ($lower === 'tất cả' || $lower === 'tat ca' || $lower === 'all') return 'Tất cả';

    return $v ?: 'Lớp 1';
}

function gradeMatch($programGrade, $studentGrade) {
    $p = normalizeGrade($programGrade);
    $s = normalizeGrade($studentGrade);
    return $p === 'Tất cả' || $p === $s;
}

function loadTitlesFromTable($conn, $tableName, $programId) {
    $contents = [];

    if (!tableExists($conn, $tableName)) {
        return $contents;
    }

    $possibleColumns = ['title', 'name', 'lesson_name'];
    $foundColumn = null;

    foreach ($possibleColumns as $col) {
        if (columnExists($conn, $tableName, $col)) {
            $foundColumn = $col;
            break;
        }
    }

    if ($foundColumn === null) {
        return $contents;
    }

    $sql = "SELECT `$foundColumn` AS lesson_title FROM `$tableName` WHERE program_id = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $programId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $title = trim((string)($row["lesson_title"] ?? ''));
            if ($title !== '') {
                $contents[] = $title;
            }
        }

        $stmt->close();
    }

    return $contents;
}

function loadAdminContents($conn, $programId) {
    $contents = [];

    $contents = loadTitlesFromTable($conn, 'program_lessons', $programId);

    if (count($contents) === 0) {
        $contents = loadTitlesFromTable($conn, 'theory_lessons', $programId);
    }

    if (count($contents) === 0) {
        $contents[] = "Chưa có danh sách nội dung chi tiết.";
    }

    return $contents;
}

function loadTeacherContents($conn, $programId) {
    $contents = [];

    $contents = loadTitlesFromTable($conn, 'teacher_program_lessons', $programId);

    if (count($contents) === 0) {
        $contents = loadTitlesFromTable($conn, 'theory_lessons', $programId);
    }

    if (count($contents) === 0) {
        $contents[] = "Chưa có danh sách nội dung chi tiết.";
    }

    return $contents;
}

/* Thông tin học sinh */
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
$programs = [];

/* Admin */
$resultAdmin = $conn->query("SELECT * FROM programs ORDER BY id DESC");
if (!$resultAdmin) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi bảng programs: " . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

while ($row = $resultAdmin->fetch_assoc()) {
    if (!gradeMatch($row["grade"] ?? '', $grade)) {
        continue;
    }

    $programId = (int)($row["id"] ?? 0);

    $programs[] = [
        "id" => $programId,
        "title" => $row["name"] ?? "Chương trình chưa đặt tên",
        "desc" => $row["description"] ?? "Chưa có mô tả chương trình.",
        "grade" => normalizeGrade($row["grade"] ?? $grade),
        "subject" => $row["subject"] ?? "Tiếng Anh",
        "source" => "admin",
        "teacher" => "Quản trị viên",
        "status" => $row["status"] ?? "Đã duyệt",
        "createdAt" => $row["created_at"] ?? "",
        "contents" => loadAdminContents($conn, $programId),
        "img" => $row["img"] ?? ""
    ];
}

/* Teacher */
if (tableExists($conn, 'teacher_programs')) {
    $resultTeacher = $conn->query("SELECT * FROM teacher_programs ORDER BY id DESC");

    if ($resultTeacher) {
        while ($row = $resultTeacher->fetch_assoc()) {
            if (!gradeMatch($row["grade"] ?? '', $grade)) {
                continue;
            }

            $programId = (int)($row["id"] ?? 0);
            $teacherIdOfProgram = (int)($row["teacher_id"] ?? 0);
            $teacherName = "Giáo viên";

            $teacherStmt = $conn->prepare("
                SELECT name
                FROM qlgiaovien_teachers
                WHERE id = ?
                LIMIT 1
            ");
            if ($teacherStmt) {
                $teacherStmt->bind_param("i", $teacherIdOfProgram);
                $teacherStmt->execute();
                $teacherResult = $teacherStmt->get_result();
                $teacher = $teacherResult->fetch_assoc();
                if ($teacher && !empty($teacher["name"])) {
                    $teacherName = $teacher["name"];
                }
                $teacherStmt->close();
            }

            $programs[] = [
                "id" => $programId,
                "title" => $row["name"] ?? "Chương trình giáo viên",
                "desc" => $row["description"] ?? "Chưa có mô tả chương trình.",
                "grade" => normalizeGrade($row["grade"] ?? $grade),
                "subject" => $row["subject"] ?? "Tiếng Anh",
                "source" => "teacher",
                "teacher" => $teacherName,
                "status" => $row["status"] ?? "Chờ phê duyệt",
                "createdAt" => $row["created_at"] ?? "",
                "contents" => loadTeacherContents($conn, $programId),
                "img" => $row["img"] ?? ""
            ];
        }
    }
}

usort($programs, function($a, $b){
    return strcmp((string)($b["createdAt"] ?? ""), (string)($a["createdAt"] ?? ""));
});

echo json_encode([
    "success" => true,
    "student" => [
        "id" => (int)$student["id"],
        "name" => $student["name"] ?? "",
        "grade" => normalizeGrade($student["grade"] ?? ""),
        "studentCode" => $student["student_code"] ?? ""
    ],
    "selectedGrade" => normalizeGrade($grade),
    "programs" => $programs
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
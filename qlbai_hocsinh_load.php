<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_OFF);

require_once 'db.php';

function jsonResponse($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeGrade($grade) {
    $g = strtolower(trim((string)$grade));

    if ($g === '1' || $g === 'lớp 1' || $g === 'lop 1') return 'Lớp 1';
    if ($g === '2' || $g === 'lớp 2' || $g === 'lop 2') return 'Lớp 2';
    if ($g === '3' || $g === 'lớp 3' || $g === 'lop 3') return 'Lớp 3';
    if ($g === '4' || $g === 'lớp 4' || $g === 'lop 4') return 'Lớp 4';
    if ($g === '5' || $g === 'lớp 5' || $g === 'lop 5') return 'Lớp 5';

    return 'Lớp 1';
}

function normalizeQuestionList($rawQuestions) {
    $result = [];

    if (!is_array($rawQuestions)) {
        return $result;
    }

    foreach ($rawQuestions as $index => $q) {
        if (!is_array($q)) continue;

        $options = [];
        if (isset($q['options']) && is_array($q['options'])) {
            $options = [
                'A' => $q['options']['A'] ?? '',
                'B' => $q['options']['B'] ?? '',
                'C' => $q['options']['C'] ?? '',
                'D' => $q['options']['D'] ?? ''
            ];
        } else {
            $options = [
                'A' => $q['A'] ?? '',
                'B' => $q['B'] ?? '',
                'C' => $q['C'] ?? '',
                'D' => $q['D'] ?? ''
            ];
        }

        $result[] = [
            'index'    => isset($q['index']) ? (int)$q['index'] : ($index + 1),
            'question' => $q['question'] ?? ($q['noi_dung'] ?? 'Chưa có nội dung câu hỏi'),
            'type'     => $q['type'] ?? 'Trắc nghiệm',
            'options'  => $options,
            'correct'  => $q['correct'] ?? ($q['dap_an_dung'] ?? ''),
            'details'  => $q['details'] ?? ($q['giai_thich'] ?? ''),
            'image'    => $q['image'] ?? '',
            'audio'    => $q['audio'] ?? ''
        ];
    }

    return $result;
}

/**
 * BÀI TẬP ADMIN
 * Bảng thật hiện tại: exercises
 * Cột thật hiện tại: id, title, subject, grade, created_at
 * Chưa có mô tả / câu hỏi trong bảng này
 */
function loadAssignmentsFromAdminExercises($conn, $grade) {
    $assignments = [];

    $sql = "
        SELECT 
            id,
            title,
            subject,
            grade,
            created_at
        FROM exercises
        WHERE grade = ?
           OR grade = REPLACE(?, 'Lớp ', '')
           OR LOWER(grade) = LOWER(?)
        ORDER BY id DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [
            'error' => true,
            'message' => 'Không prepare được bảng exercises: ' . $conn->error
        ];
    }

    $stmt->bind_param("sss", $grade, $grade, $grade);

    if (!$stmt->execute()) {
        return [
            'error' => true,
            'message' => 'Không execute được bảng exercises: ' . $stmt->error
        ];
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $assignments[] = [
            'id'           => 'admin_' . (int)$row['id'],
            'title'        => $row['title'] ?? 'Chưa có tiêu đề',
            'desc'         => 'Bài tập được tạo từ trang QLbai admin.',
            'grade'        => normalizeGrade($row['grade'] ?? $grade),
            'subject'      => $row['subject'] ?? 'Tiếng Anh',
            'deadline'     => '',
            'totalScore'   => 0,
            'source_type'  => 'admin',
            'source_label' => 'QLbai admin',
            'questions'    => [],
            'createdAt'    => $row['created_at'] ?? ''
        ];
    }

    $stmt->close();

    return [
        'error' => false,
        'data' => $assignments
    ];
}

/**
 * BÀI TẬP GIÁO VIÊN
 * Chỉ load nếu bảng qlbaitap_giaovien tồn tại và query được
 */
function loadAssignmentsFromTeacherTable($conn, $grade) {
    $assignments = [];

    $sql = "
        SELECT 
            id,
            tieu_de,
            mo_ta,
            khoi_lop,
            mon_hoc,
            han_nop,
            tong_diem,
            cau_hoi_json,
            created_at
        FROM qlbaitap_giaovien
        WHERE khoi_lop = ?
           OR khoi_lop = REPLACE(?, 'Lớp ', '')
           OR LOWER(khoi_lop) = LOWER(?)
        ORDER BY id DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [
            'error' => false,
            'data' => []
        ];
    }

    $stmt->bind_param("sss", $grade, $grade, $grade);

    if (!$stmt->execute()) {
        $stmt->close();
        return [
            'error' => false,
            'data' => []
        ];
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $decodedQuestions = json_decode($row['cau_hoi_json'] ?? '[]', true);
        $questions = normalizeQuestionList($decodedQuestions);

        $assignments[] = [
            'id'           => 'teacher_' . (int)$row['id'],
            'title'        => $row['tieu_de'] ?? 'Chưa có tiêu đề',
            'desc'         => $row['mo_ta'] ?? 'Chưa có mô tả bài tập.',
            'grade'        => normalizeGrade($row['khoi_lop'] ?? $grade),
            'subject'      => $row['mon_hoc'] ?? 'Tiếng Anh',
            'deadline'     => $row['han_nop'] ?? '',
            'totalScore'   => is_numeric($row['tong_diem'] ?? null) ? (float)$row['tong_diem'] : count($questions),
            'source_type'  => 'teacher',
            'source_label' => 'QLbaitapgiaovien',
            'questions'    => $questions,
            'createdAt'    => $row['created_at'] ?? ''
        ];
    }

    $stmt->close();

    return [
        'error' => false,
        'data' => $assignments
    ];
}

try {
    if (!isset($conn)) {
        jsonResponse([
            'success' => false,
            'message' => 'db.php không tạo ra biến $conn'
        ]);
    }

    if (!isset($_SESSION['user_id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Bạn chưa đăng nhập',
            'redirect' => 'dangnhap.html'
        ]);
    }

    $studentId = (int)$_SESSION['user_id'];
    $selectedGrade = isset($_GET['grade']) ? trim($_GET['grade']) : '';

    /**
     * LẤY HỌC SINH
     * Bảng thật: qlhocsinh_students
     */
    $sqlStudent = "
        SELECT 
            id,
            name,
            grade,
            student_code
        FROM qlhocsinh_students
        WHERE id = ?
        LIMIT 1
    ";

    $stmtStudent = $conn->prepare($sqlStudent);

    if (!$stmtStudent) {
        jsonResponse([
            'success' => false,
            'message' => 'Lỗi prepare học sinh: ' . $conn->error
        ]);
    }

    $stmtStudent->bind_param("i", $studentId);

    if (!$stmtStudent->execute()) {
        jsonResponse([
            'success' => false,
            'message' => 'Lỗi execute học sinh: ' . $stmtStudent->error
        ]);
    }

    $student = $stmtStudent->get_result()->fetch_assoc();
    $stmtStudent->close();

    if (!$student) {
        jsonResponse([
            'success' => false,
            'message' => 'Không tìm thấy học sinh với user_id=' . $studentId
        ]);
    }

    if ($selectedGrade === '') {
        $selectedGrade = $student['grade'] ?? 'Lớp 1';
    }

    $selectedGrade = normalizeGrade($selectedGrade);

    /**
     * LOAD BÀI ADMIN
     */
    $adminAssignments = loadAssignmentsFromAdminExercises($conn, $selectedGrade);
    if (!empty($adminAssignments['error'])) {
        jsonResponse([
            'success' => false,
            'message' => $adminAssignments['message']
        ]);
    }

    /**
     * LOAD BÀI GIÁO VIÊN
     */
    $teacherAssignments = loadAssignmentsFromTeacherTable($conn, $selectedGrade);

    $assignments = array_merge(
        $adminAssignments['data'],
        $teacherAssignments['data']
    );

    /**
     * Sắp xếp:
     * - ưu tiên bài có hạn nộp
     * - nếu không có hạn nộp thì dùng createdAt mới hơn lên trước
     */
    usort($assignments, function ($a, $b) {
        $deadlineA = trim((string)($a['deadline'] ?? ''));
        $deadlineB = trim((string)($b['deadline'] ?? ''));

        if ($deadlineA !== '' && $deadlineB !== '') {
            $timeA = strtotime($deadlineA);
            $timeB = strtotime($deadlineB);
            if ($timeA !== false && $timeB !== false) {
                return $timeA <=> $timeB;
            }
        }

        if ($deadlineA !== '' && $deadlineB === '') return -1;
        if ($deadlineA === '' && $deadlineB !== '') return 1;

        $createdA = strtotime($a['createdAt'] ?? '') ?: 0;
        $createdB = strtotime($b['createdAt'] ?? '') ?: 0;

        return $createdB <=> $createdA;
    });

    jsonResponse([
        'success' => true,
        'student' => [
            'id' => (int)$student['id'],
            'name' => $student['name'] ?? 'Học sinh',
            'grade' => $student['grade'] ?? '',
            'studentCode' => $student['student_code'] ?? ''
        ],
        'selectedGrade' => $selectedGrade,
        'assignments' => $assignments
    ]);

} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => 'PHP Error: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
}
?>
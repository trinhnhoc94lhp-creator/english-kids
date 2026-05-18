<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

function getCount($conn, $table) {
    $sql = "SELECT COUNT(*) AS total FROM $table";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['total'];
    }
    return 0;
}

function getRecentPrograms($conn) {
    $list = [];
    $sql = "SELECT id, name, subject, grade, status, created_at
            FROM programs
            ORDER BY id DESC
            LIMIT 4";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list[] = [
                "id" => (int)$row["id"],
                "name" => $row["name"],
                "subject" => $row["subject"],
                "grade" => $row["grade"],
                "status" => $row["status"],
                "createdAt" => $row["created_at"]
            ];
        }
    }

    return $list;
}

function getRecentActivities($conn) {
    $activities = [];

    $sqlProgram = "SELECT id, name, created_at FROM programs ORDER BY id DESC LIMIT 1";
    $resultProgram = $conn->query($sqlProgram);
    if ($resultProgram && $row = $resultProgram->fetch_assoc()) {
        $activities[] = [
            "icon" => "📚",
            "title" => "Tạo chương trình mới",
            "desc" => ($row["name"] ?: "Một chương trình mới") . " vừa được thêm vào hệ thống.",
            "time" => $row["created_at"]
        ];
    }

    $sqlExercise = "SELECT id, title, created_at FROM exercises ORDER BY id DESC LIMIT 1";
    $resultExercise = $conn->query($sqlExercise);
    if ($resultExercise && $row = $resultExercise->fetch_assoc()) {
        $activities[] = [
            "icon" => "📝",
            "title" => "Tạo bài tập mới",
            "desc" => ($row["title"] ?: "Một bài tập mới") . " đã được lưu trong hệ thống.",
            "time" => $row["created_at"]
        ];
    }

    $sqlStudent = "SELECT id, full_name, created_at FROM students ORDER BY id DESC LIMIT 1";
    $resultStudent = $conn->query($sqlStudent);
    if ($resultStudent && $row = $resultStudent->fetch_assoc()) {
        $activities[] = [
            "icon" => "👨‍🎓",
            "title" => "Thêm học sinh",
            "desc" => ($row["full_name"] ?: "Một học sinh mới") . " đã được thêm vào danh sách.",
            "time" => $row["created_at"]
        ];
    }

    $sqlTeacher = "SELECT id, full_name, created_at FROM teachers ORDER BY id DESC LIMIT 1";
    $resultTeacher = $conn->query($sqlTeacher);
    if ($resultTeacher && $row = $resultTeacher->fetch_assoc()) {
        $activities[] = [
            "icon" => "👩‍🏫",
            "title" => "Thêm giáo viên",
            "desc" => ($row["full_name"] ?: "Một giáo viên mới") . " đã được thêm vào danh sách.",
            "time" => $row["created_at"]
        ];
    }

    return array_slice($activities, 0, 4);
}

$data = [
    "totals" => [
        "students" => getCount($conn, "students"),
        "teachers" => getCount($conn, "teachers"),
        "programs" => getCount($conn, "programs"),
        "exercises" => getCount($conn, "exercises")
    ],
    "recentPrograms" => getRecentPrograms($conn),
    "recentActivities" => getRecentActivities($conn)
];

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
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

$exercises = [];

$stmt = $conn->prepare("
    SELECT id, name, class_name, deadline, total_score, description, created_at
    FROM teacher_exercises
    WHERE teacher_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $exerciseId = (int)$row["id"];
    $questions = [];

    $qStmt = $conn->prepare("
        SELECT id, type, question, option_a, option_b, option_c, option_d, correct_answer, pairs_json
        FROM teacher_exercise_questions
        WHERE exercise_id = ?
        ORDER BY id ASC
    ");
    $qStmt->bind_param("i", $exerciseId);
    $qStmt->execute();
    $qResult = $qStmt->get_result();

    while ($q = $qResult->fetch_assoc()) {
        $type = $q["type"] ?? "Trắc nghiệm";
        $item = [
            "type" => $type,
            "question" => $q["question"] ?? "",
            "correct" => $q["correct_answer"] ?? ""
        ];

        if ($type === "Trắc nghiệm") {
            $item["options"] = [
                "A" => $q["option_a"] ?? "",
                "B" => $q["option_b"] ?? "",
                "C" => $q["option_c"] ?? "",
                "D" => $q["option_d"] ?? ""
            ];
        }

        if ($type === "Nối") {
            $pairs = json_decode($q["pairs_json"] ?? "[]", true);
            $item["pairs"] = is_array($pairs) ? $pairs : [];
        }

        $questions[] = $item;
    }
    $qStmt->close();

    $exercises[] = [
        "id" => $exerciseId,
        "name" => $row["name"] ?? "",
        "className" => $row["class_name"] ?? "",
        "deadline" => $row["deadline"] ?? "",
        "totalScore" => (int)($row["total_score"] ?? 0),
        "desc" => $row["description"] ?? "",
        "createdAt" => $row["created_at"] ?? "",
        "questions" => $questions
    ];
}
$stmt->close();

echo json_encode([
    "success" => true,
    "exercises" => $exercises
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
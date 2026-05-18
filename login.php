<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$account = trim($_POST['account'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($account === '' || $password === '') {
    echo json_encode([
        "success" => false,
        "message" => "Vui lòng nhập đầy đủ tài khoản và mật khẩu"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   1. ADMIN MẶC ĐỊNH
   Tài khoản: admin@gmail.com
   Mật khẩu: 123456
========================= */
if ($account === 'admin@gmail.com' && $password === '123456') {
    $_SESSION["role"] = "admin";
    $_SESSION["user_id"] = 1;
    $_SESSION["user_name"] = "Admin";
    $_SESSION["account"] = "admin@gmail.com";

    echo json_encode([
        "success" => true,
        "role" => "admin",
        "name" => "Admin",
        "redirect" => "trangchu.html"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   2. GIÁO VIÊN
   Tài khoản: email hoặc mã GV hoặc SĐT
   Mật khẩu: 123456
========================= */
$stmt = $conn->prepare("
    SELECT id, teacher_code, name, email, phone
    FROM qlgiaovien_teachers
    WHERE email = ? OR teacher_code = ? OR phone = ?
    LIMIT 1
");
$stmt->bind_param("sss", $account, $account, $account);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($teacher && $password === '123456') {
    $_SESSION["role"] = "giaovien";
    $_SESSION["user_id"] = $teacher["id"];
    $_SESSION["user_name"] = $teacher["name"];
    $_SESSION["account"] = $teacher["email"] ?: $teacher["teacher_code"];

    echo json_encode([
        "success" => true,
        "role" => "giaovien",
        "name" => $teacher["name"],
        "redirect" => "trangchugiaovien.html"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   3. HỌC SINH
   Tài khoản: mã học sinh hoặc SĐT
   Mật khẩu: 123456
========================= */
$stmt = $conn->prepare("
    SELECT id, student_code, name, phone
    FROM qlhocsinh_students
    WHERE student_code = ? OR phone = ?
    LIMIT 1
");
$stmt->bind_param("ss", $account, $account);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($student && $password === '123456') {
    $_SESSION["role"] = "hocsinh";
    $_SESSION["user_id"] = $student["id"];
    $_SESSION["user_name"] = $student["name"];
    $_SESSION["account"] = $student["student_code"];

    echo json_encode([
        "success" => true,
        "role" => "hocsinh",
        "name" => $student["name"],
        "redirect" => "trangchuhocsinh.html"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    "success" => false,
    "message" => "Sai tài khoản hoặc mật khẩu"
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
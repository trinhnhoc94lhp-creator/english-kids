<?php
function ensure_dir($dirPath) {
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0777, true);
    }
}

function uploadFile($file, $type = "image") {
    if (!isset($file) || !isset($file["tmp_name"]) || $file["error"] !== UPLOAD_ERR_OK) {
        return [
            "success" => false,
            "message" => "File upload không hợp lệ"
        ];
    }

    $baseDir = dirname(__DIR__, 2);
    $folder = $type === "audio" ? "/uploads/audio/" : "/uploads/images/";
    $targetDir = $baseDir . $folder;

    ensure_dir($targetDir);

    $originalName = $file["name"];
    $tmpName = $file["tmp_name"];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedImage = ["jpg", "jpeg", "png", "gif", "webp"];
    $allowedAudio = ["mp3", "wav", "ogg", "m4a"];

    if ($type === "image" && !in_array($ext, $allowedImage)) {
        return [
            "success" => false,
            "message" => "Chỉ cho phép ảnh jpg, jpeg, png, gif, webp"
        ];
    }

    if ($type === "audio" && !in_array($ext, $allowedAudio)) {
        return [
            "success" => false,
            "message" => "Chỉ cho phép audio mp3, wav, ogg, m4a"
        ];
    }

    $newName = uniqid($type . "_", true) . "." . $ext;
    $targetPath = $targetDir . $newName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return [
            "success" => false,
            "message" => "Không thể lưu file upload"
        ];
    }

    return [
        "success" => true,
        "path" => "uploads/" . ($type === "audio" ? "audio/" : "images/") . $newName
    ];
}

function deleteOldFile($relativePath) {
    if (!$relativePath) return;

    $baseDir = dirname(__DIR__, 2);
    $fullPath = $baseDir . "/" . ltrim($relativePath, "/");

    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}
?>
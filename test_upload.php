<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "TEST OK<br>";

$dir = dirname(__DIR__) . '/uploads/';
echo "DIR: " . $dir . "<br>";

if (!is_dir($dir)) {
    echo "Tạo thư mục uploads<br>";
    mkdir($dir, 0777, true);
}

echo "is_dir: " . (is_dir($dir) ? 'YES' : 'NO') . "<br>";
echo "is_writable: " . (is_writable($dir) ? 'YES' : 'NO') . "<br>";

$file = $dir . 'test.txt';

if (file_put_contents($file, 'hello') !== false) {
    echo "WRITE OK";
} else {
    echo "WRITE FAIL";
}
?>
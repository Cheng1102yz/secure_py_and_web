<?php
/**
 * 测试文件 - 用于验证backend目录是否可访问
 * 上传到 backend/ 目录后，访问 http://你的域名/backend/test.php
 */
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => 'backend目录可访问，test.php执行成功',
    'php_version' => PHP_VERSION,
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'current_time' => date('Y-m-d H:i:s'),
    'current_dir' => __DIR__,
    'files_in_dir' => scandir(__DIR__)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

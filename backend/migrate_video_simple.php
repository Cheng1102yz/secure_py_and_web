<?php
require_once __DIR__ . '/helpers.php';
$db = Database::getInstance();

// 检查video字段是否已存在
$columns = $db->fetchAll("PRAGMA table_info(posts)");
$hasVideo = false;
foreach ($columns as $col) {
    if ($col['name'] === 'video') {
        $hasVideo = true;
        break;
    }
}

if (!$hasVideo) {
    $db->execute("ALTER TABLE posts ADD COLUMN video TEXT DEFAULT ''");
    echo "video字段添加成功\n";
} else {
    echo "video字段已存在\n";
}

// 创建视频上传目录
$videoDir = __DIR__ . '/../uploads/videos';
if (!is_dir($videoDir)) {
    mkdir($videoDir, 0755, true);
    echo "视频上传目录已创建\n";
}

echo "迁移完成\n";

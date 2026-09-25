<?php
/**
 * 数据库迁移脚本：为posts表添加video字段
 * 运行方式：访问 backend/migrate_add_video.php?password=cyznb666
 */

require_once __DIR__ . '/helpers.php';

// 密码验证
$password = getParam('password', '');
if ($password !== 'cyznb666') {
    die('密码错误');
}

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

if ($hasVideo) {
    echo '<h2>video字段已存在，无需迁移</h2>';
} else {
    // 添加video字段
    $result = $db->execute("ALTER TABLE posts ADD COLUMN video TEXT DEFAULT ''");
    if ($result !== false) {
        echo '<h2 style="color:green;">✅ video字段添加成功！</h2>';
    } else {
        echo '<h2 style="color:red;">❌ video字段添加失败</h2>';
    }
}

// 验证字段
$columns = $db->fetchAll("PRAGMA table_info(posts)");
echo '<h3>posts表当前字段：</h3>';
echo '<ul>';
foreach ($columns as $col) {
    $mark = $col['name'] === 'video' ? ' <strong style="color:green;">(新增)</strong>' : '';
    echo "<li>{$col['name']} ({$col['type']}){$mark}</li>";
}
echo '</ul>';

// 创建视频上传目录
$videoDir = __DIR__ . '/../uploads/videos';
if (!is_dir($videoDir)) {
    mkdir($videoDir, 0755, true);
    echo '<p style="color:green;">✅ 视频上传目录已创建：uploads/videos</p>';
} else {
    echo '<p>视频上传目录已存在：uploads/videos</p>';
}

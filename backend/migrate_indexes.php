<?php
/**
 * 数据库索引优化脚本
 * 运行方式：访问 backend/migrate_indexes.php?password=cyznb666
 */

require_once __DIR__ . '/helpers.php';

// 密码验证
$password = getParam('password', '');
if ($password !== 'cyznb666') {
    die('密码错误');
}

$db = Database::getInstance();

echo "<h2>数据库索引优化</h2>";
echo "<hr>";

// 1. 检查现有索引
echo "<h3>1. posts表现有索引：</h3>";
$indexes = $db->fetchAll("PRAGMA index_list(posts)");
echo "<ul>";
foreach ($indexes as $idx) {
    echo "<li>{$idx['name']} (unique: {$idx['unique']})</li>";
}
echo "</ul>";

// 2. 添加复合索引（如果不存在）
echo "<h3>2. 添加复合索引：</h3>";
$indexesToAdd = [
    'idx_posts_status_created' => 'CREATE INDEX IF NOT EXISTS idx_posts_status_created ON posts(status, created_at DESC)',
    'idx_posts_status_category_created' => 'CREATE INDEX IF NOT EXISTS idx_posts_status_category_created ON posts(status, category_id, created_at DESC)',
    'idx_posts_user_status_created' => 'CREATE INDEX IF NOT EXISTS idx_posts_user_status_created ON posts(user_id, status, created_at DESC)',
    'idx_posts_is_top_created' => 'CREATE INDEX IF NOT EXISTS idx_posts_is_top_created ON posts(is_top, created_at DESC)',
];

foreach ($indexesToAdd as $name => $sql) {
    try {
        $db->execute($sql);
        echo "<p style='color:green;'>✅ 索引 {$name} 添加成功（或已存在）</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ 索引 {$name} 添加失败：{$e->getMessage()}</p>";
    }
}

// 3. 为其他表添加索引
echo "<h3>3. 为其他表添加索引：</h3>";
$otherIndexes = [
    'idx_post_likes_post_user' => 'CREATE INDEX IF NOT EXISTS idx_post_likes_post_user ON post_likes(post_id, user_id)',
    'idx_post_comments_post' => 'CREATE INDEX IF NOT EXISTS idx_post_comments_post ON post_comments(post_id, status, created_at)',
    'idx_post_comments_user' => 'CREATE INDEX IF NOT EXISTS idx_post_comments_user ON post_comments(user_id, status, created_at)',
    'idx_notifications_user' => 'CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read, created_at DESC)',
    'idx_follows_follower' => 'CREATE INDEX IF NOT EXISTS idx_follows_follower ON user_follows(follower_id, following_id)',
    'idx_follows_following' => 'CREATE INDEX IF NOT EXISTS idx_follows_following ON user_follows(following_id, follower_id)',
];

foreach ($otherIndexes as $name => $sql) {
    try {
        $db->execute($sql);
        echo "<p style='color:green;'>✅ 索引 {$name} 添加成功（或已存在）</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ 索引 {$name} 添加失败：{$e->getMessage()}</p>";
    }
}

// 4. 验证索引
echo "<h3>4. 验证posts表索引：</h3>";
$indexes = $db->fetchAll("PRAGMA index_list(posts)");
echo "<ul>";
foreach ($indexes as $idx) {
    echo "<li>{$idx['name']} (unique: {$idx['unique']})</li>";
}
echo "</ul>";

echo "<hr>";
echo "<h2 style='color:green;'>✅ 数据库索引优化完成！</h2>";

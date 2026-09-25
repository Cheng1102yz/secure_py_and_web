<?php
require_once __DIR__ . '/helpers.php';
$db = Database::getInstance();

// 查看所有公告
$announcements = $db->fetchAll("SELECT id, title, type, status, created_at FROM announcements ORDER BY id DESC");
echo "<h2>公告列表</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>标题</th><th>类型</th><th>状态</th><th>创建时间</th></tr>";
foreach ($announcements as $ann) {
    $statusText = $ann['status'] == 1 ? '已发布' : '草稿';
    $statusColor = $ann['status'] == 1 ? 'green' : 'red';
    echo "<tr><td>{$ann['id']}</td><td>{$ann['title']}</td><td>{$ann['type']}</td><td style='color:$statusColor;'>$statusText ({$ann['status']})</td><td>{$ann['created_at']}</td></tr>";
}
echo "</table>";

// 修复所有公告的status为1（已发布）
$count = $db->execute("UPDATE announcements SET status = 1 WHERE status != 1");
echo "<p style='color:green; font-weight:bold;'>已修复 $count 条公告的状态为「已发布」</p>";

// 再次查看
$announcements = $db->fetchAll("SELECT id, title, status FROM announcements ORDER BY id DESC");
echo "<h3>修复后</h3>";
echo "<ul>";
foreach ($announcements as $ann) {
    echo "<li>ID: {$ann['id']}, 标题: {$ann['title']}, 状态: " . ($ann['status'] == 1 ? '已发布' : '草稿') . "</li>";
}
echo "</ul>";

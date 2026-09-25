<?php
require_once __DIR__ . '/helpers.php';
$db = Database::getInstance();

echo "删除旧的messages表...\n";
$db->execute("DROP TABLE IF EXISTS messages");
echo "✅ 旧表已删除\n";

echo "重新创建messages表...\n";
$db->execute("
    CREATE TABLE messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id INTEGER NOT NULL,
        sender_id INTEGER NOT NULL,
        receiver_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        type TEXT DEFAULT 'text',
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");
echo "✅ messages表创建成功\n";

echo "验证表结构：\n";
$columns = $db->fetchAll("PRAGMA table_info(messages)");
foreach ($columns as $col) {
    echo "  - {$col['name']} ({$col['type']})\n";
}

echo "\n测试插入消息：\n";
$msgId = $db->insert(
    "INSERT INTO messages (conversation_id, sender_id, receiver_id, content, type, is_read, created_at)
     VALUES (?, ?, ?, ?, 'text', 0, ?)",
    [1, 1, 16, '测试消息', date('Y-m-d H:i:s')]
);

if ($msgId) {
    echo "✅ 消息插入成功，ID: {$msgId}\n";
    $msg = $db->fetchOne("SELECT * FROM messages WHERE id = ?", [$msgId]);
    echo "消息内容：{$msg['content']}\n";
} else {
    echo "❌ 消息插入失败\n";
}

echo "\n✅ 修复完成！\n";

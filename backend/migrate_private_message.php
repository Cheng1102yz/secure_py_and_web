<?php
/**
 * 私聊功能数据库迁移脚本
 * 运行方式：访问 backend/migrate_private_message.php?password=cyznb666
 */

require_once __DIR__ . '/helpers.php';

// 密码验证
$password = getParam('password', '');
if ($password !== 'cyznb666') {
    die('密码错误');
}

$db = Database::getInstance();

echo "<h2>私聊功能数据库迁移</h2>";
echo "<hr>";

// 1. 创建会话表
echo "<h3>1. 创建会话表（conversations）...</h3>";
try {
    $db->execute("
        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user1_id INTEGER NOT NULL,
            user2_id INTEGER NOT NULL,
            last_message TEXT DEFAULT '',
            last_message_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_sender_id INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user1_id, user2_id)
        )
    ");
    echo "<p style='color:green;'>✅ 会话表创建成功（或已存在）</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ 会话表创建失败：{$e->getMessage()}</p>";
}

// 2. 创建消息表
echo "<h3>2. 创建消息表（messages）...</h3>";
try {
    $db->execute("
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            type TEXT DEFAULT 'text',
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        )
    ");
    echo "<p style='color:green;'>✅ 消息表创建成功（或已存在）</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ 消息表创建失败：{$e->getMessage()}</p>";
}

// 3. 创建索引
echo "<h3>3. 创建索引...</h3>";
$indexes = [
    'idx_conversations_user1' => 'CREATE INDEX IF NOT EXISTS idx_conversations_user1 ON conversations(user1_id)',
    'idx_conversations_user2' => 'CREATE INDEX IF NOT EXISTS idx_conversations_user2 ON conversations(user2_id)',
    'idx_conversations_last_time' => 'CREATE INDEX IF NOT EXISTS idx_conversations_last_time ON conversations(last_message_time DESC)',
    'idx_messages_conversation' => 'CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, created_at)',
    'idx_messages_sender' => 'CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id)',
    'idx_messages_receiver' => 'CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_id, is_read)',
];

foreach ($indexes as $name => $sql) {
    try {
        $db->execute($sql);
        echo "<p style='color:green;'>✅ 索引 {$name} 创建成功</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ 索引 {$name} 创建失败：{$e->getMessage()}</p>";
    }
}

// 4. 验证表结构
echo "<h3>4. 验证表结构...</h3>";
echo "<p><strong>conversations表字段：</strong></p>";
$columns = $db->fetchAll("PRAGMA table_info(conversations)");
echo "<ul>";
foreach ($columns as $col) {
    echo "<li>{$col['name']} ({$col['type']})</li>";
}
echo "</ul>";

echo "<p><strong>messages表字段：</strong></p>";
$columns = $db->fetchAll("PRAGMA table_info(messages)");
echo "<ul>";
foreach ($columns as $col) {
    echo "<li>{$col['name']} ({$col['type']})</li>";
}
echo "</ul>";

echo "<hr>";
echo "<h2 style='color:green;'>✅ 私聊功能数据库迁移完成！</h2>";

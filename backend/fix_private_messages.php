<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 私聊数据库表修复脚本（安全版）
 * ============================================================
 * 功能：
 *   1. 检查并创建 conversations 表（如果不存在）
 *   2. 检查 messages 表，如果缺少字段则添加（不删除旧数据）
 *   3. 为旧数据创建对应的会话记录
 *   4. 添加必要的索引
 * 
 * 使用方法：
 *   访问 http://你的域名/backend/fix_private_messages.php?password=cyznb666
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 密码验证
$password = getParam('password', '');
if ($password !== 'cyznb666') {
    jsonError('密码错误', 403);
}

$db = Database::getInstance();

echo "<pre style='background:#f5f5f5;padding:20px;font-family:monospace;'>";
echo "=== SecureVault 私聊数据库表修复（安全版）===\n\n";

// ============================================================
// 1. 检查并创建 conversations 表
// ============================================================
echo "【1/5】检查 conversations 表...\n";
$convTable = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='conversations'");

if (!$convTable) {
    echo "  conversations 表不存在，创建中...\n";
    $db->execute("
        CREATE TABLE conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user1_id INTEGER NOT NULL,
            user2_id INTEGER NOT NULL,
            last_message TEXT,
            last_message_time DATETIME,
            last_sender_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user1_id, user2_id)
        )
    ");
    echo "  ✅ conversations 表创建成功\n";
} else {
    echo "  ✅ conversations 表已存在\n";
    
    // 检查并添加缺失的字段
    $convColumns = $db->fetchAll("PRAGMA table_info(conversations)");
    $convColumnNames = array_column($convColumns, 'name');
    
    $requiredConvColumns = [
        'last_message' => 'TEXT',
        'last_message_time' => 'DATETIME',
        'last_sender_id' => 'INTEGER'
    ];
    
    foreach ($requiredConvColumns as $colName => $colType) {
        if (!in_array($colName, $convColumnNames)) {
            echo "  添加字段: {$colName} ({$colType})\n";
            $db->execute("ALTER TABLE conversations ADD COLUMN {$colName} {$colType}");
            echo "  ✅ 字段 {$colName} 添加成功\n";
        }
    }
}

// ============================================================
// 2. 检查 messages 表并添加缺失字段
// ============================================================
echo "\n【2/5】检查 messages 表...\n";
$msgTable = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='messages'");

if (!$msgTable) {
    echo "  messages 表不存在，创建中...\n";
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
    echo "  ✅ messages 表创建成功\n";
} else {
    echo "  ✅ messages 表已存在，检查字段...\n";
    
    $msgColumns = $db->fetchAll("PRAGMA table_info(messages)");
    $msgColumnNames = array_column($msgColumns, 'name');
    
    echo "  当前字段: " . implode(', ', $msgColumnNames) . "\n";
    
    // 检查并添加缺失的字段
    $requiredMsgColumns = [
        'conversation_id' => 'INTEGER',
        'sender_id' => 'INTEGER',
        'receiver_id' => 'INTEGER',
        'content' => 'TEXT',
        'type' => 'TEXT',
        'is_read' => 'INTEGER',
        'created_at' => 'DATETIME'
    ];
    
    $missingColumns = [];
    foreach ($requiredMsgColumns as $colName => $colType) {
        if (!in_array($colName, $msgColumnNames)) {
            $missingColumns[] = $colName;
            echo "  添加字段: {$colName} ({$colType})\n";
            $db->execute("ALTER TABLE messages ADD COLUMN {$colName} {$colType}");
            echo "  ✅ 字段 {$colName} 添加成功\n";
        }
    }
    
    if (empty($missingColumns)) {
        echo "  ✅ 所有字段都已存在\n";
    }
}

// ============================================================
// 3. 为旧数据创建对应的会话记录并更新 conversation_id
// ============================================================
echo "\n【3/5】修复旧数据（为没有 conversation_id 的消息创建会话）...\n";

// 查询没有 conversation_id 的消息（旧数据）
$oldMessages = $db->fetchAll("
    SELECT DISTINCT sender_id, receiver_id 
    FROM messages 
    WHERE conversation_id IS NULL OR conversation_id = 0
    LIMIT 100
");

if (empty($oldMessages)) {
    echo "  ✅ 没有需要修复的旧数据\n";
} else {
    echo "  找到 " . count($oldMessages) . " 组需要修复的会话\n";
    
    $fixedCount = 0;
    foreach ($oldMessages as $msg) {
        $user1 = min($msg['sender_id'], $msg['receiver_id']);
        $user2 = max($msg['sender_id'], $msg['receiver_id']);
        
        // 查找或创建会话
        $conv = $db->fetchOne("SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?", [$user1, $user2]);
        
        if (!$conv) {
            $convId = $db->insert(
                "INSERT INTO conversations (user1_id, user2_id, created_at) VALUES (?, ?, ?)",
                [$user1, $user2, date('Y-m-d H:i:s')]
            );
        } else {
            $convId = $conv['id'];
        }
        
        // 更新消息的 conversation_id
        $db->execute(
            "UPDATE messages SET conversation_id = ? WHERE (conversation_id IS NULL OR conversation_id = 0) AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))",
            [$convId, $msg['sender_id'], $msg['receiver_id'], $msg['receiver_id'], $msg['sender_id']]
        );
        
        $fixedCount++;
    }
    
    echo "  ✅ 修复了 {$fixedCount} 组会话\n";
}

// ============================================================
// 4. 添加索引
// ============================================================
echo "\n【4/5】添加索引...\n";

// conversations 表索引
$convIndexes = [
    'idx_conv_user1' => 'CREATE INDEX idx_conv_user1 ON conversations(user1_id)',
    'idx_conv_user2' => 'CREATE INDEX idx_conv_user2 ON conversations(user2_id)',
    'idx_conv_users' => 'CREATE INDEX idx_conv_users ON conversations(user1_id, user2_id)'
];

foreach ($convIndexes as $idxName => $idxSql) {
    $idxExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='index' AND name=?", [$idxName]);
    if (!$idxExists) {
        try {
            $db->execute($idxSql);
            echo "  ✅ 添加索引: {$idxName}\n";
        } catch (Exception $e) {
            echo "  ⚠️ 索引 {$idxName} 添加失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ✅ 索引已存在: {$idxName}\n";
    }
}

// messages 表索引
$msgIndexes = [
    'idx_msg_conv' => 'CREATE INDEX idx_msg_conv ON messages(conversation_id)',
    'idx_msg_sender' => 'CREATE INDEX idx_msg_sender ON messages(sender_id)',
    'idx_msg_receiver' => 'CREATE INDEX idx_msg_receiver ON messages(receiver_id)',
    'idx_msg_created' => 'CREATE INDEX idx_msg_created ON messages(created_at)'
];

foreach ($msgIndexes as $idxName => $idxSql) {
    $idxExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='index' AND name=?", [$idxName]);
    if (!$idxExists) {
        try {
            $db->execute($idxSql);
            echo "  ✅ 添加索引: {$idxName}\n";
        } catch (Exception $e) {
            echo "  ⚠️ 索引 {$idxName} 添加失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ✅ 索引已存在: {$idxName}\n";
    }
}

// ============================================================
// 5. 验证表结构并测试
// ============================================================
echo "\n【5/5】验证表结构...\n";

echo "\nconversations 表字段:\n";
$convColumns = $db->fetchAll("PRAGMA table_info(conversations)");
foreach ($convColumns as $col) {
    echo "  - {$col['name']} ({$col['type']})\n";
}

echo "\nmessages 表字段:\n";
$msgColumns = $db->fetchAll("PRAGMA table_info(messages)");
foreach ($msgColumns as $col) {
    echo "  - {$col['name']} ({$col['type']})\n";
}

// 统计记录数
$convCount = $db->fetchOne("SELECT COUNT(*) as count FROM conversations");
$msgCount = $db->fetchOne("SELECT COUNT(*) as count FROM messages");
echo "\n记录统计:\n";
echo "  conversations: {$convCount['count']} 条\n";
echo "  messages: {$msgCount['count']} 条\n";

// 测试插入消息
echo "\n测试插入消息...\n";
$testConvId = isset($convCount['count']) && $convCount['count'] > 0 
    ? $db->fetchOne("SELECT id FROM conversations LIMIT 1")['id'] 
    : 0;

if ($testConvId > 0) {
    $testMsgId = $db->insert(
        "INSERT INTO messages (conversation_id, sender_id, receiver_id, content, type, is_read, created_at)
         VALUES (?, ?, ?, ?, 'text', 0, ?)",
        [$testConvId, 1, 2, '修复测试消息', date('Y-m-d H:i:s')]
    );
    
    if ($testMsgId) {
        echo "  ✅ 消息插入测试成功，ID: {$testMsgId}\n";
        // 删除测试消息
        $db->execute("DELETE FROM messages WHERE id = ?", [$testMsgId]);
    } else {
        echo "  ❌ 消息插入测试失败\n";
    }
} else {
    echo "  ⚠️ 没有会话记录，跳过插入测试\n";
}

echo "\n=== ✅ 修复完成！===\n";
echo "现在可以正常发送私聊消息了！\n";
echo "</pre>";

<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 群聊功能数据库迁移脚本
 * ============================================================
 * 功能：创建群聊相关的三个数据表
 *   1. chat_groups         - 群聊表（群基本信息）
 *   2. chat_group_members  - 群成员表（群成员关系）
 *   3. chat_group_messages - 群消息表（群聊消息）
 * 
 * 运行方式：访问 backend/migrate_group_chat.php?password=cyznb666
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 密码验证
$password = getParam('password', '');
if ($password !== 'cyznb666') {
    jsonError('密码错误，无权执行数据库迁移', 403);
}

$db = Database::getInstance();

echo "<pre style='font-family:monospace;padding:20px;background:#f5f5f5;'>";
echo "========================================\n";
echo "  SecureVault 群聊功能数据库迁移\n";
echo "========================================\n\n";

// ============================================================
// 1. 创建群聊表 chat_groups
// ============================================================
echo "【1/3】创建群聊表 chat_groups...\n";

$tableExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='chat_groups'");

if (!$tableExists) {
    $db->execute("
        CREATE TABLE chat_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            avatar TEXT DEFAULT '',
            owner_id INTEGER NOT NULL,
            description TEXT DEFAULT '',
            member_count INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "  ✅ chat_groups 表创建成功\n";
} else {
    echo "  ✅ chat_groups 表已存在，检查字段...\n";
    
    $columns = $db->fetchAll("PRAGMA table_info(chat_groups)");
    $columnNames = array_column($columns, 'name');
    echo "  当前字段: " . implode(', ', $columnNames) . "\n";
    
    // 检查并添加缺失的字段
    $requiredColumns = [
        'name' => 'TEXT',
        'avatar' => 'TEXT',
        'owner_id' => 'INTEGER',
        'description' => 'TEXT',
        'member_count' => 'INTEGER',
        'created_at' => 'DATETIME',
        'updated_at' => 'DATETIME'
    ];
    
    foreach ($requiredColumns as $colName => $colType) {
        if (!in_array($colName, $columnNames)) {
            echo "  添加字段: {$colName} ({$colType})\n";
            $db->execute("ALTER TABLE chat_groups ADD COLUMN {$colName} {$colType}");
            echo "  ✅ 字段 {$colName} 添加成功\n";
        }
    }
}

echo "\n";

// ============================================================
// 2. 创建群成员表 chat_group_members
// ============================================================
echo "【2/3】创建群成员表 chat_group_members...\n";

$tableExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='chat_group_members'");

if (!$tableExists) {
    $db->execute("
        CREATE TABLE chat_group_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role TEXT DEFAULT 'member',
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(group_id, user_id)
        )
    ");
    echo "  ✅ chat_group_members 表创建成功\n";
} else {
    echo "  ✅ chat_group_members 表已存在，检查字段...\n";
    
    $columns = $db->fetchAll("PRAGMA table_info(chat_group_members)");
    $columnNames = array_column($columns, 'name');
    echo "  当前字段: " . implode(', ', $columnNames) . "\n";
    
    // 检查并添加缺失的字段
    $requiredColumns = [
        'group_id' => 'INTEGER',
        'user_id' => 'INTEGER',
        'role' => 'TEXT',
        'joined_at' => 'DATETIME',
        'last_read_at' => 'DATETIME'
    ];
    
    foreach ($requiredColumns as $colName => $colType) {
        if (!in_array($colName, $columnNames)) {
            echo "  添加字段: {$colName} ({$colType})\n";
            $db->execute("ALTER TABLE chat_group_members ADD COLUMN {$colName} {$colType}");
            echo "  ✅ 字段 {$colName} 添加成功\n";
        }
    }
}

echo "\n";

// ============================================================
// 3. 创建群消息表 chat_group_messages
// ============================================================
echo "【3/3】创建群消息表 chat_group_messages...\n";

$tableExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='chat_group_messages'");

if (!$tableExists) {
    $db->execute("
        CREATE TABLE chat_group_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            type TEXT DEFAULT 'text',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "  ✅ chat_group_messages 表创建成功\n";
} else {
    echo "  ✅ chat_group_messages 表已存在，检查字段...\n";
    
    $columns = $db->fetchAll("PRAGMA table_info(chat_group_messages)");
    $columnNames = array_column($columns, 'name');
    echo "  当前字段: " . implode(', ', $columnNames) . "\n";
    
    // 检查并添加缺失的字段
    $requiredColumns = [
        'group_id' => 'INTEGER',
        'sender_id' => 'INTEGER',
        'content' => 'TEXT',
        'type' => 'TEXT',
        'created_at' => 'DATETIME'
    ];
    
    foreach ($requiredColumns as $colName => $colType) {
        if (!in_array($colName, $columnNames)) {
            echo "  添加字段: {$colName} ({$colType})\n";
            $db->execute("ALTER TABLE chat_group_messages ADD COLUMN {$colName} {$colType}");
            echo "  ✅ 字段 {$colName} 添加成功\n";
        }
    }
}

echo "\n";
echo "========================================\n";
echo "  ✅ 数据库迁移完成！\n";
echo "========================================\n";
echo "\n";
echo "创建的表：\n";
echo "  1. chat_groups         - 群聊表\n";
echo "  2. chat_group_members  - 群成员表\n";
echo "  3. chat_group_messages - 群消息表\n";
echo "\n";
echo "下一步：同步 group_chat.php 后端文件和前端文件\n";
echo "</pre>";

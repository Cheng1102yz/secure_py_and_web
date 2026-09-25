<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 群聊功能增强数据库迁移脚本
 * ============================================================
 * 功能：添加群聊增强功能所需的数据库字段和表
 *   1. chat_group_members 表添加 nickname 字段（群昵称）
 *   2. chat_groups 表添加 announcement 字段（群公告）
 *   3. chat_groups 表添加 announcement_updated_at 字段
 *   4. 创建 chat_group_announcement_read 表（群公告已读确认）
 * 
 * 运行方式：访问 backend/migrate_group_chat_v2.php?password=cyznb666
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
echo "  群聊功能增强数据库迁移 v2\n";
echo "========================================\n\n";

// ============================================================
// 1. chat_group_members 表添加 nickname 字段
// ============================================================
echo "【1/4】chat_group_members 表添加 nickname 字段...\n";

$columns = $db->fetchAll("PRAGMA table_info(chat_group_members)");
$columnNames = array_column($columns, 'name');

if (!in_array('nickname', $columnNames)) {
    $db->execute("ALTER TABLE chat_group_members ADD COLUMN nickname TEXT DEFAULT ''");
    echo "  ✅ nickname 字段添加成功\n";
} else {
    echo "  ✅ nickname 字段已存在\n";
}

echo "\n";

// ============================================================
// 2. chat_groups 表添加 announcement 字段
// ============================================================
echo "【2/4】chat_groups 表添加 announcement 字段...\n";

$columns = $db->fetchAll("PRAGMA table_info(chat_groups)");
$columnNames = array_column($columns, 'name');

if (!in_array('announcement', $columnNames)) {
    $db->execute("ALTER TABLE chat_groups ADD COLUMN announcement TEXT DEFAULT ''");
    echo "  ✅ announcement 字段添加成功\n";
} else {
    echo "  ✅ announcement 字段已存在\n";
}

echo "\n";

// ============================================================
// 3. chat_groups 表添加 announcement_updated_at 字段
// ============================================================
echo "【3/4】chat_groups 表添加 announcement_updated_at 字段...\n";

$columns = $db->fetchAll("PRAGMA table_info(chat_groups)");
$columnNames = array_column($columns, 'name');

if (!in_array('announcement_updated_at', $columnNames)) {
    $db->execute("ALTER TABLE chat_groups ADD COLUMN announcement_updated_at DATETIME DEFAULT NULL");
    echo "  ✅ announcement_updated_at 字段添加成功\n";
} else {
    echo "  ✅ announcement_updated_at 字段已存在\n";
}

echo "\n";

// ============================================================
// 4. 创建 chat_group_announcement_read 表
// ============================================================
echo "【4/4】创建 chat_group_announcement_read 表...\n";

$tableExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='chat_group_announcement_read'");

if (!$tableExists) {
    $db->execute("
        CREATE TABLE chat_group_announcement_read (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(group_id, user_id)
        )
    ");
    echo "  ✅ chat_group_announcement_read 表创建成功\n";
} else {
    echo "  ✅ chat_group_announcement_read 表已存在\n";
}

echo "\n";
echo "========================================\n";
echo "  ✅ 数据库迁移完成！\n";
echo "========================================\n";
echo "\n";
echo "新增/修改的字段和表：\n";
echo "  1. chat_group_members.nickname - 群昵称\n";
echo "  2. chat_groups.announcement - 群公告\n";
echo "  3. chat_groups.announcement_updated_at - 群公告更新时间\n";
echo "  4. chat_group_announcement_read - 群公告已读确认表\n";
echo "\n";
echo "下一步：同步 group_chat.php 后端文件和前端文件\n";
echo "</pre>";

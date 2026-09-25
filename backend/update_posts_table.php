<?php
/**
 * 给posts表添加置顶、加精、管理员备注字段
 */

$dbPath = __DIR__ . '/../database/securevault.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查现有字段
    $columns = $pdo->query('PRAGMA table_info(posts)')->fetchAll(PDO::FETCH_COLUMN, 1);
    echo "posts表现有字段: " . implode(', ', $columns) . "\n";
    
    // 添加is_top字段（置顶）
    if (!in_array('is_top', $columns)) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN is_top INTEGER DEFAULT 0');
        echo "已添加 is_top 字段\n";
    } else {
        echo "is_top 字段已存在\n";
    }
    
    // 添加is_featured字段（加精）
    if (!in_array('is_featured', $columns)) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN is_featured INTEGER DEFAULT 0');
        echo "已添加 is_featured 字段\n";
    } else {
        echo "is_featured 字段已存在\n";
    }
    
    // 添加admin_note字段（管理员备注）
    if (!in_array('admin_note', $columns)) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN admin_note TEXT DEFAULT ""');
        echo "已添加 admin_note 字段\n";
    } else {
        echo "admin_note 字段已存在\n";
    }
    
    echo "\n数据库字段更新完成！\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

<?php
// 检查posts表结构和发帖相关问题
$dbPath = __DIR__ . '/../database/securevault.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查posts表结构
    $columns = $pdo->query('PRAGMA table_info(posts)')->fetchAll(PDO::FETCH_COLUMN, 1);
    echo "posts表字段: " . implode(', ', $columns) . "\n";
    
    // 检查是否有category_id字段
    if (!in_array('category_id', $columns)) {
        echo "⚠️  缺少 category_id 字段\n";
    }
    
    // 检查posts表记录数
    $count = $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    echo "posts表记录数: $count\n";
    
    // 检查users表
    $userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    echo "\nusers表字段: " . implode(', ', $userColumns) . "\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

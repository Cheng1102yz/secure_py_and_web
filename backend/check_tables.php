<?php
// 检查数据库表列表
$dbPath = __DIR__ . '/../database/securevault.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo "数据库表列表:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    // 检查notifications表
    if (in_array('notifications', $tables)) {
        echo "\n✓ notifications表存在\n";
        $columns = $pdo->query('PRAGMA table_info(notifications)')->fetchAll(PDO::FETCH_COLUMN, 1);
        echo "  字段: " . implode(', ', $columns) . "\n";
    } else {
        echo "\n✗ notifications表不存在，需要创建\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

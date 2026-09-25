<?php
/**
 * 软件自动更新系统数据库表初始化
 * 功能：创建 software 表和 software_files 表
 * 使用方法：访问此文件一次即可
 * 作者：work_by_cyz
 */

// 禁止直接访问
if (!defined('IN_APP')) {
    define('IN_APP', true);
}

// 引入配置和数据库（helpers.php会自动引入config.php和db.php）
require_once __DIR__ . '/helpers.php';

// 设置响应头
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>数据库初始化 - 软件自动更新系统</title>
    <style>
        body {
            font-family: '微软雅黑', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-top: 0;
            font-size: 24px;
            text-align: center;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }
        .result-item {
            padding: 15px 20px;
            margin: 10px 0;
            border-radius: 8px;
            font-size: 14px;
        }
        .success {
            background: #f0f9eb;
            color: #67c23a;
            border-left: 4px solid #67c23a;
        }
        .error {
            background: #fef0f0;
            color: #f56c6c;
            border-left: 4px solid #f56c6c;
        }
        .info {
            background: #ecf5ff;
            color: #409eff;
            border-left: 4px solid #409eff;
        }
        .icon {
            margin-right: 8px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            background: linear-gradient(135deg, #409eff, #66b1ff);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 软件自动更新系统</h1>
        <p class='subtitle'>数据库表初始化</p>
";

try {
    // 获取数据库实例
    $db = Database::getInstance();
    echo "<div class='result-item info'><span class='icon'>✅</span>数据库连接成功</div>";
    
    // SQL语句列表
    $sqlStatements = [
        // 软件版本表
        "CREATE TABLE IF NOT EXISTS `software` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL DEFAULT 'SecureVault' COMMENT '软件名称',
          `version` varchar(50) NOT NULL COMMENT '版本号，如 2.0.1',
          `description` text COMMENT '版本描述',
          `changelog` text COMMENT '更新日志（JSON格式）',
          `download_url` varchar(500) DEFAULT NULL COMMENT '完整包下载地址',
          `file_size` bigint(20) DEFAULT 0 COMMENT '完整包大小（字节）',
          `file_hash` varchar(64) DEFAULT NULL COMMENT '完整包MD5哈希',
          `min_version` varchar(50) DEFAULT NULL COMMENT '最低支持版本',
          `force_update` tinyint(1) DEFAULT 0 COMMENT '是否强制更新',
          `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用',
          `created_by` int(11) DEFAULT NULL COMMENT '创建者ID',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
          PRIMARY KEY (`id`),
          KEY `idx_version` (`version`),
          KEY `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='软件版本表'",
        
        // 软件文件表
        "CREATE TABLE IF NOT EXISTS `software_files` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `software_id` int(11) NOT NULL COMMENT '关联的软件版本ID',
          `path` varchar(500) NOT NULL COMMENT '文件相对路径',
          `file_hash` varchar(64) NOT NULL COMMENT '文件MD5哈希',
          `file_size` bigint(20) NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
          `is_directory` tinyint(1) DEFAULT 0 COMMENT '是否为目录',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_software_path` (`software_id`, `path`),
          KEY `idx_software_id` (`software_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='软件文件表（增量更新）'"
    ];
    
    // 执行SQL语句
    $tableNames = ['software 表', 'software_files 表'];
    $allSuccess = true;
    
    foreach ($sqlStatements as $index => $sql) {
        try {
            $db->execute($sql);
            echo "<div class='result-item success'><span class='icon'>✅</span>{$tableNames[$index]} 创建成功</div>";
        } catch (Exception $e) {
            $allSuccess = false;
            echo "<div class='result-item error'><span class='icon'>❌</span>{$tableNames[$index]} 创建失败: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // 插入初始版本数据（如果表为空）
    try {
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM software");
        if ($count && $count['count'] == 0) {
            $db->execute(
                "INSERT INTO software (name, version, description, changelog, is_active) VALUES (?, ?, ?, ?, ?)",
                ['SecureVault', '2.0.0', '初始版本', json_encode(['初始版本发布']), 1]
            );
            echo "<div class='result-item success'><span class='icon'>✅</span>初始版本数据插入成功 (v2.0.0)</div>";
        } else {
            echo "<div class='result-item info'><span class='icon'>ℹ️</span>software 表已有数据，跳过初始版本插入</div>";
        }
    } catch (Exception $e) {
        echo "<div class='result-item error'><span class='icon'>❌</span>初始版本数据插入失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // 验证表是否创建成功
    echo "<div class='result-item info'><span class='icon'>📊</span>验证表结构...</div>";
    
    try {
        $softwareCount = $db->fetchOne("SELECT COUNT(*) as count FROM software");
        $filesCount = $db->fetchOne("SELECT COUNT(*) as count FROM software_files");
        echo "<div class='result-item success'><span class='icon'>📊</span>software 表记录数: " . ($softwareCount['count'] ?? 0) . "</div>";
        echo "<div class='result-item success'><span class='icon'>📊</span>software_files 表记录数: " . ($filesCount['count'] ?? 0) . "</div>";
    } catch (Exception $e) {
        echo "<div class='result-item error'><span class='icon'>❌</span>验证失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    if ($allSuccess) {
        echo "<div class='result-item success' style='margin-top: 20px; text-align: center; font-size: 16px;'>
            <span class='icon'>🎉</span><strong>数据库初始化全部成功！</strong>
        </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result-item error'><span class='icon'>❌</span>数据库连接失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='result-item info'><span class='icon'>💡</span>请检查 config.php 中的数据库配置是否正确</div>";
}

echo "
        <div class='footer'>
            <p>软件自动更新系统 v1.0 | work_by_cyz</p>
            <p>初始化完成后，此文件可以删除或保留</p>
        </div>
    </div>
</body>
</html>";

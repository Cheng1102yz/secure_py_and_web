<?php
/**
 * 数据库更新脚本 - 添加软件标识字段（调试版）
 * 功能：自动检测数据库中的文件表，给它添加app_key字段
 * 使用方法：访问此文件一次即可
 * 作者：work_by_cyz
 */

// 禁止直接访问
if (!defined('IN_APP')) {
    define('IN_APP', true);
}

// 引入配置和数据库
require_once __DIR__ . '/helpers.php';

// 设置响应头
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>数据库更新 - 添加软件标识（调试版）</title>
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
            max-width: 900px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-top: 0;
            text-align: center;
        }
        .result-item {
            padding: 12px 16px;
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
        .warning {
            background: #fdf6ec;
            color: #e6a23c;
            border-left: 4px solid #e6a23c;
        }
        .debug {
            background: #f5f7fa;
            color: #606266;
            border-left: 4px solid #909399;
            font-family: Consolas, monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ebeef5;
            font-size: 13px;
        }
        th {
            background: #f5f7fa;
            color: #606266;
            font-weight: bold;
        }
        code {
            background: #f5f7fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: Consolas, monospace;
            font-size: 12px;
        }
        .highlight {
            background: #fff7e6 !important;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🗄️ 数据库更新 - 添加软件标识（调试版）</h1>
";

try {
    $db = Database::getInstance();
    
    echo "<div class='result-item info'>🔍 正在检测数据库连接...</div>";
    
    // 测试数据库连接
    try {
        $dbName = $db->fetchOne("SELECT DATABASE() as db_name");
        echo "<div class='result-item success'>✅ 数据库连接成功，当前数据库：<code>" . htmlspecialchars($dbName['db_name'] ?? '未知') . "</code></div>";
    } catch (Exception $e) {
        echo "<div class='result-item error'>❌ 数据库连接失败：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // 方式1：使用 information_schema 获取表列表（更可靠）
    echo "<div class='result-item info'>🔍 正在使用 information_schema 获取表列表...</div>";
    
    try {
        $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name");
        
        if (empty($tables)) {
            echo "<div class='result-item warning'>⚠️ information_schema 查询返回空，尝试其他方式...</div>";
            $tables = [];
        } else {
            echo "<div class='result-item success'>✅ 通过 information_schema 找到 " . count($tables) . " 个表</div>";
        }
    } catch (Exception $e) {
        echo "<div class='result-item error'>❌ information_schema 查询失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        $tables = [];
    }
    
    // 方式2：如果方式1失败，尝试 SHOW TABLES
    if (empty($tables)) {
        echo "<div class='result-item info'>🔍 尝试使用 SHOW TABLES...</div>";
        try {
            $rawTables = $db->fetchAll("SHOW TABLES");
            echo "<div class='debug'>SHOW TABLES 原始结果：" . htmlspecialchars(json_encode($rawTables, JSON_UNESCAPED_UNICODE)) . "</div>";
            
            if (!empty($rawTables)) {
                foreach ($rawTables as $row) {
                    foreach ($row as $val) {
                        $tables[] = ['table_name' => $val];
                    }
                }
                echo "<div class='result-item success'>✅ 通过 SHOW TABLES 找到 " . count($tables) . " 个表</div>";
            }
        } catch (Exception $e) {
            echo "<div class='result-item error'>❌ SHOW TABLES 失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // 如果还是没找到表，直接尝试查询 files 表
    if (empty($tables)) {
        echo "<div class='result-item warning'>⚠️ 无法获取表列表，直接尝试查询 files 表...</div>";
        try {
            $test = $db->fetchOne("SELECT COUNT(*) as cnt FROM files");
            echo "<div class='result-item success'>✅ files 表存在，当前有 " . ($test['cnt'] ?? 0) . " 条记录</div>";
            $tables = [['table_name' => 'files']];
        } catch (Exception $e) {
            echo "<div class='result-item error'>❌ files 表查询失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    if (empty($tables)) {
        echo "<div class='result-item error'>❌ 无法检测到任何表！请检查数据库配置。</div>";
        echo "</div></body></html>";
        exit;
    }
    
    // 显示所有表
    echo "<div class='result-item info'>📋 数据库中的所有表：</div>";
    echo "<table>";
    echo "<tr><th>序号</th><th>表名</th><th>可能的用途</th><th>记录数</th></tr>";
    
    $fileTable = null;
    $tableList = [];
    
    foreach ($tables as $index => $table) {
        $tableName = $table['table_name'] ?? array_values($table)[0];
        $tableList[] = $tableName;
        
        // 判断可能的用途
        $usage = "未知";
        $isHighlight = false;
        $rowCount = "-";
        
        if (stripos($tableName, 'file') !== false) {
            $usage = "📁 可能是文件表（下载中心）";
            $isHighlight = true;
            if ($fileTable === null) {
                $fileTable = $tableName;
            }
        } elseif (stripos($tableName, 'user') !== false) {
            $usage = "👤 用户表";
        } elseif (stripos($tableName, 'post') !== false) {
            $usage = "📝 帖子表";
        } elseif (stripos($tableName, 'comment') !== false) {
            $usage = "💬 评论表";
        } elseif (stripos($tableName, 'message') !== false) {
            $usage = "✉️ 消息表";
        } elseif (stripos($tableName, 'category') !== false) {
            $usage = "🏷️ 分类表";
        }
        
        // 尝试获取记录数
        try {
            $countResult = $db->fetchOne("SELECT COUNT(*) as cnt FROM `$tableName`");
            $rowCount = $countResult['cnt'] ?? 0;
        } catch (Exception $e) {
            $rowCount = "无法获取";
        }
        
        $highlightClass = $isHighlight ? 'highlight' : '';
        echo "<tr class='$highlightClass'>";
        echo "<td>" . ($index + 1) . "</td>";
        echo "<td><code>" . htmlspecialchars($tableName) . "</code></td>";
        echo "<td>" . $usage . "</td>";
        echo "<td>" . $rowCount . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 如果没找到文件表，让用户手动指定
    if ($fileTable === null) {
        echo "<div class='result-item warning'>⚠️ 没有自动检测到文件表，请在URL中指定表名：</div>";
        echo "<div class='result-item info'>";
        echo "访问格式：<code>update_add_app_key.php?table=表名</code><br><br>";
        echo "例如：<code>update_add_app_key.php?table=download_files</code>";
        echo "</div>";
        
        // 检查是否通过URL指定了表名
        $specifiedTable = getParam('table', '');
        if (!empty($specifiedTable) && in_array($specifiedTable, $tableList)) {
            $fileTable = $specifiedTable;
            echo "<div class='result-item success'>✅ 已使用指定的表：<code>$fileTable</code></div>";
        } else {
            echo "</div></body></html>";
            exit;
        }
    }
    
    echo "<div class='result-item info'>📋 检测到文件表：<code>$fileTable</code></div>";
    
    // 检查app_key字段是否已存在
    $columnCheck = $db->fetchOne("SHOW COLUMNS FROM `$fileTable` LIKE 'app_key'");
    
    if ($columnCheck) {
        echo "<div class='result-item warning'>⚠️ app_key字段已存在，无需重复添加</div>";
    } else {
        // 添加app_key字段
        $db->execute("ALTER TABLE `$fileTable` ADD COLUMN app_key VARCHAR(50) DEFAULT '' COMMENT '软件唯一标识' AFTER category");
        echo "<div class='result-item success'>✅ 已添加 app_key 字段到 <code>$fileTable</code> 表</div>";
    }
    
    // 检查索引
    $indexCheck = $db->fetchOne("SHOW INDEX FROM `$fileTable` WHERE Key_name = 'idx_app_key'");
    if (!$indexCheck) {
        $db->execute("ALTER TABLE `$fileTable` ADD INDEX idx_app_key (app_key)");
        echo "<div class='result-item success'>✅ 已添加 app_key 索引</div>";
    } else {
        echo "<div class='result-item info'>ℹ️ app_key 索引已存在</div>";
    }
    
    // 显示当前表结构（关键字段）
    echo "<div class='result-item info'>📋 当前 <code>$fileTable</code> 表结构（关键字段）：</div>";
    $columns = $db->fetchAll("SHOW COLUMNS FROM `$fileTable`");
    echo "<table>";
    echo "<tr><th>字段名</th><th>类型</th><th>默认值</th><th>说明</th></tr>";
    foreach ($columns as $col) {
        if (in_array($col['Field'], ['id', 'title', 'category', 'app_key', 'version', 'file_size', 'file_url', 'status', 'created_at'])) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($col['Field']) . "</code></td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($col['Comment'] ?? '') . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    echo "<div class='result-item success' style='margin-top: 20px; text-align: center; font-size: 16px;'>
        <strong>🎉 数据库更新完成！</strong><br>
        现在可以在上传文件时指定 app_key，自动更新系统会根据 app_key 查找对应软件的更新。
    </div>";
    
} catch (Exception $e) {
    echo "<div class='result-item error'>❌ 更新失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='debug'>错误追踪：" . htmlspecialchars($e->getTraceAsString()) . "</div>";
}

echo "
    </div>
</body>
</html>";

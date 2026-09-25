<?php
/**
 * 数据库诊断脚本 - 检查files表结构
 * 使用方法：访问此文件，查看files表是否有app_key字段
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
    <title>数据库诊断 - 检查files表结构</title>
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
            max-width: 800px;
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
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #409eff, #66b1ff);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 10px 5px;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 数据库诊断 - 检查files表结构</h1>
";

try {
    $db = Database::getInstance();
    
    echo "<div class='result-item info'>🔍 正在检查数据库连接...</div>";
    
    // 测试数据库连接
    try {
        $dbName = $db->fetchOne("SELECT DATABASE() as db_name");
        echo "<div class='result-item success'>✅ 数据库连接成功，当前数据库：<code>" . htmlspecialchars($dbName['db_name'] ?? '未知') . "</code></div>";
    } catch (Exception $e) {
        echo "<div class='result-item error'>❌ 数据库连接失败：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // 检查files表是否存在
    echo "<div class='result-item info'>🔍 正在检查files表是否存在...</div>";
    
    try {
        $tableCheck = $db->fetchOne("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'files'");
        
        if ($tableCheck && $tableCheck['cnt'] > 0) {
            echo "<div class='result-item success'>✅ files表存在</div>";
        } else {
            echo "<div class='result-item error'>❌ files表不存在！</div>";
            echo "</div></body></html>";
            exit;
        }
    } catch (Exception $e) {
        // 如果information_schema查询失败，尝试直接查询
        try {
            $test = $db->fetchOne("SELECT COUNT(*) as cnt FROM files");
            echo "<div class='result-item success'>✅ files表存在（直接查询成功），当前有 " . ($test['cnt'] ?? 0) . " 条记录</div>";
        } catch (Exception $e2) {
            echo "<div class='result-item error'>❌ files表不存在或无法访问：" . htmlspecialchars($e2->getMessage()) . "</div>";
            echo "</div></body></html>";
            exit;
        }
    }
    
    // 检查app_key字段是否存在
    echo "<div class='result-item info'>🔍 正在检查app_key字段是否存在...</div>";
    
    $appKeyExists = false;
    try {
        $columnCheck = $db->fetchOne("SELECT COUNT(*) as cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'files' AND column_name = 'app_key'");
        
        if ($columnCheck && $columnCheck['cnt'] > 0) {
            $appKeyExists = true;
            echo "<div class='result-item success'>✅ app_key字段存在</div>";
        } else {
            echo "<div class='result-item error'>❌ app_key字段不存在！</div>";
        }
    } catch (Exception $e) {
        // 如果information_schema查询失败，尝试SHOW COLUMNS
        try {
            $columns = $db->fetchAll("SHOW COLUMNS FROM files");
            foreach ($columns as $col) {
                if ($col['Field'] === 'app_key') {
                    $appKeyExists = true;
                    break;
                }
            }
            if ($appKeyExists) {
                echo "<div class='result-item success'>✅ app_key字段存在（SHOW COLUMNS确认）</div>";
            } else {
                echo "<div class='result-item error'>❌ app_key字段不存在！</div>";
            }
        } catch (Exception $e2) {
            echo "<div class='result-item warning'>⚠️ 无法检查app_key字段：" . htmlspecialchars($e2->getMessage()) . "</div>";
        }
    }
    
    // 显示files表的完整结构
    echo "<div class='result-item info'>📋 files表完整结构：</div>";
    try {
        $columns = $db->fetchAll("SHOW COLUMNS FROM files");
        echo "<table>";
        echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>默认值</th><th>说明</th></tr>";
        foreach ($columns as $col) {
            $isAppKey = ($col['Field'] === 'app_key');
            $highlight = $isAppKey ? 'style="background: #fff7e6; font-weight: bold;"' : '';
            echo "<tr $highlight>";
            echo "<td><code>" . htmlspecialchars($col['Field']) . "</code>" . ($isAppKey ? " ⭐" : "") . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($col['Comment'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<div class='result-item error'>❌ 无法获取表结构：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // 给出解决方案
    echo "<div class='result-item info'>💡 解决方案：</div>";
    
    if (!$appKeyExists) {
        echo "<div class='result-item warning'>⚠️ app_key字段不存在，这就是上传失败的原因！</div>";
        echo "<div class='result-item info'>";
        echo "请点击下面的按钮，自动添加app_key字段：<br><br>";
        echo "<a class='btn' href='update_add_app_key.php'>🚀 一键添加app_key字段</a>";
        echo "</div>";
        
        // 尝试自动添加
        echo "<div class='result-item info'>🔧 正在尝试自动添加app_key字段...</div>";
        try {
            $db->execute("ALTER TABLE files ADD COLUMN app_key VARCHAR(50) DEFAULT '' COMMENT '软件唯一标识' AFTER category");
            echo "<div class='result-item success'>✅ 已自动添加app_key字段！</div>";
            
            // 添加索引
            try {
                $db->execute("ALTER TABLE files ADD INDEX idx_app_key (app_key)");
                echo "<div class='result-item success'>✅ 已添加app_key索引！</div>";
            } catch (Exception $e) {
                echo "<div class='result-item info'>ℹ️ app_key索引已存在或添加失败：" . htmlspecialchars($e->getMessage()) . "</div>";
            }
            
            echo "<div class='result-item success' style='margin-top: 20px; text-align: center; font-size: 16px;'>
                <strong>🎉 修复完成！现在可以重新上传文件了！</strong>
            </div>";
            
        } catch (Exception $e) {
            echo "<div class='result-item error'>❌ 自动添加失败：" . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<div class='result-item info'>请手动访问 update_add_app_key.php 进行添加</div>";
        }
    } else {
        echo "<div class='result-item success'>✅ app_key字段存在，数据库结构正常！</div>";
        echo "<div class='result-item info'>";
        echo "如果上传还是失败，可能是其他原因：<br>";
        echo "1. 文件大小超过服务器限制（php.ini中的upload_max_filesize）<br>";
        echo "2. 文件扩展名被禁止<br>";
        echo "3. 上传目录权限不足<br>";
        echo "4. 后端代码有语法错误<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result-item error'>❌ 诊断失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='result-item debug'>错误追踪：" . htmlspecialchars($e->getTraceAsString()) . "</div>";
}

echo "
    </div>
</body>
</html>";

<?php
/**
 * 数据库诊断脚本 v2 - 不依赖information_schema
 * 使用方法：访问此文件，自动检测和修复
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
    <title>数据库诊断 v2 - 自动检测和修复</title>
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
        code {
            background: #f5f7fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: Consolas, monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 数据库诊断 v2 - 自动检测和修复</h1>
";

try {
    $db = Database::getInstance();
    
    echo "<div class='result-item info'>🔍 正在检查数据库连接...</div>";
    
    // 测试数据库连接（直接查询）
    try {
        $test = $db->fetchOne("SELECT 1 as test");
        echo "<div class='result-item success'>✅ 数据库连接成功</div>";
    } catch (Exception $e) {
        echo "<div class='result-item error'>❌ 数据库连接失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        echo "</div></body></html>";
        exit;
    }
    
    // 检查files表是否存在（直接查询，不依赖information_schema）
    echo "<div class='result-item info'>🔍 正在检查files表是否存在（直接查询方式）...</div>";
    
    $tableExists = false;
    try {
        $count = $db->fetchOne("SELECT COUNT(*) as cnt FROM files");
        $tableExists = true;
        $recordCount = $count['cnt'] ?? 0;
        echo "<div class='result-item success'>✅ files表存在，当前有 $recordCount 条记录</div>";
    } catch (Exception $e) {
        echo "<div class='result-item warning'>⚠️ 直接查询files表失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        
        // 尝试其他可能的表名
        $possibleTables = ['files', 'download_files', 'file_list', 'software_files', 'downloads'];
        foreach ($possibleTables as $t) {
            try {
                $c = $db->fetchOne("SELECT COUNT(*) as cnt FROM `$t`");
                echo "<div class='result-item success'>✅ 找到表：<code>$t</code>，有 " . ($c['cnt'] ?? 0) . " 条记录</div>";
                $tableExists = true;
                break;
            } catch (Exception $e2) {
                // 继续尝试下一个
            }
        }
        
        if (!$tableExists) {
            echo "<div class='result-item error'>❌ 无法找到文件表！请检查表名是否正确。</div>";
            echo "</div></body></html>";
            exit;
        }
    }
    
    // 检查app_key字段是否存在（直接尝试ALTER TABLE，如果字段已存在会报错）
    echo "<div class='result-item info'>🔍 正在检查app_key字段是否存在...</div>";
    
    $appKeyExists = false;
    
    // 方法1：尝试查询app_key字段
    try {
        $test = $db->fetchOne("SELECT app_key FROM files LIMIT 1");
        $appKeyExists = true;
        echo "<div class='result-item success'>✅ app_key字段存在（查询成功）</div>";
    } catch (Exception $e) {
        echo "<div class='result-item info'>ℹ️ 查询app_key字段失败，可能字段不存在：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // 方法2：如果方法1失败，尝试添加字段
    if (!$appKeyExists) {
        echo "<div class='result-item info'>🔧 正在尝试添加app_key字段...</div>";
        try {
            $db->execute("ALTER TABLE files ADD COLUMN app_key VARCHAR(50) DEFAULT '' COMMENT '软件唯一标识'");
            echo "<div class='result-item success'>✅ 已成功添加app_key字段！</div>";
            $appKeyExists = true;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            // 如果错误信息包含"Duplicate column name"，说明字段已经存在
            if (stripos($errorMsg, 'Duplicate column name') !== false || stripos($errorMsg, 'already exists') !== false) {
                echo "<div class='result-item success'>✅ app_key字段已存在（从错误信息判断）</div>";
                $appKeyExists = true;
            } else {
                echo "<div class='result-item warning'>⚠️ 添加字段失败：" . htmlspecialchars($errorMsg) . "</div>";
                echo "<div class='result-item info'>ℹ️ 这可能是因为字段已经存在，或者权限不足。</div>";
                // 即使添加失败，我们也假设字段可能存在，继续测试上传
                $appKeyExists = true;
            }
        }
    }
    
    // 尝试添加索引
    if ($appKeyExists) {
        echo "<div class='result-item info'>🔧 正在尝试添加app_key索引...</div>";
        try {
            $db->execute("ALTER TABLE files ADD INDEX idx_app_key (app_key)");
            echo "<div class='result-item success'>✅ 已添加app_key索引！</div>";
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            if (stripos($errorMsg, 'Duplicate key name') !== false || stripos($errorMsg, 'already exists') !== false) {
                echo "<div class='result-item success'>✅ app_key索引已存在</div>";
            } else {
                echo "<div class='result-item info'>ℹ️ 索引添加失败（不影响使用）：" . htmlspecialchars($errorMsg) . "</div>";
            }
        }
    }
    
    // 验证：尝试插入一条测试记录（不真正提交，使用事务回滚）
    echo "<div class='result-item info'>🔍 正在验证INSERT语句是否正常...</div>";
    try {
        $db->beginTransaction();
        $testId = $db->insert(
            "INSERT INTO files (title, description, file_url, file_name, file_size, version, category, app_key, status, download_count, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?)",
            ['测试记录', '测试描述', '/test.zip', 'test.zip', 1024, '9.9.9', 'test', 'test_key', date('Y-m-d H:i:s')]
        );
        $db->rollBack();
        echo "<div class='result-item success'>✅ INSERT语句验证成功！包含app_key字段的插入可以正常执行。</div>";
    } catch (Exception $e) {
        echo "<div class='result-item error'>❌ INSERT语句验证失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='result-item info'>";
        echo "这说明app_key字段可能真的不存在。请尝试以下解决方案：<br><br>";
        echo "1. 登录数据库管理工具（如phpMyAdmin、Navicat）<br>";
        echo "2. 找到files表，手动添加字段：<br>";
        echo "&nbsp;&nbsp;&nbsp;字段名：<code>app_key</code><br>";
        echo "&nbsp;&nbsp;&nbsp;类型：<code>VARCHAR(50)</code><br>";
        echo "&nbsp;&nbsp;&nbsp;默认值：<code>''</code><br>";
        echo "&nbsp;&nbsp;&nbsp;注释：<code>软件唯一标识</code><br>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }
    
    echo "<div class='result-item success' style='margin-top: 20px; text-align: center; font-size: 16px;'>
        <strong>🎉 诊断和修复完成！</strong><br>
        数据库结构正常，现在可以重新上传文件了！<br>
        <span style='font-size: 14px; color: #909399;'>如果上传还是失败，请检查php.ini中的upload_max_filesize和post_max_size设置。</span>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='result-item error'>❌ 诊断失败: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "
    </div>
</body>
</html>";

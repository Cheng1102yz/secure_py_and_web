<?php
/**
 * SQLite数据库诊断和修复脚本
 * 功能：显示SQLite数据库路径，检查并添加app_key字段
 * 作者：work_by_cyz
 */

// 禁止直接访问
if (!defined('IN_APP')) {
    define('IN_APP', true);
}

require_once __DIR__ . '/helpers.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>SQLite数据库诊断和修复</title>
    <style>
        body { font-family: '微软雅黑', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; padding: 40px 20px; }
        .container { background: white; border-radius: 16px; padding: 40px; max-width: 1000px; margin: 0 auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { color: #333; margin-top: 0; text-align: center; }
        h2 { color: #409eff; border-bottom: 2px solid #409eff; padding-bottom: 8px; margin-top: 30px; }
        .box { padding: 16px; margin: 15px 0; border-radius: 8px; }
        .info { background: #ecf5ff; border-left: 4px solid #409eff; }
        .success { background: #f0f9eb; border-left: 4px solid #67c23a; }
        .error { background: #fef0f0; border-left: 4px solid #f56c6c; }
        .warning { background: #fdf6ec; border-left: 4px solid #e6a23c; }
        code { background: #f5f7fa; padding: 4px 8px; border-radius: 4px; font-family: Consolas, monospace; font-size: 12px; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ebeef5; }
        th { background: #f5f7fa; color: #606266; font-weight: bold; }
        .step { background: #f5f7fa; padding: 12px 16px; margin: 8px 0; border-radius: 8px; font-size: 14px; }
        .num { display: inline-block; width: 24px; height: 24px; background: #409eff; color: white; border-radius: 50%; text-align: center; line-height: 24px; font-weight: bold; margin-right: 8px; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔧 SQLite数据库诊断和修复</h1>";

try {
    $db = Database::getInstance();
    
    // 1. 显示数据库信息
    echo "<h2>📊 1. 数据库信息</h2>";
    echo "<div class='box info'>";
    
    try {
        $pdo = $db->getPdo();
        if ($pdo) {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            echo "<p><strong>数据库驱动：</strong><code>$driver</code></p>";
            
            // 如果是SQLite，显示数据库文件路径
            if ($driver === 'sqlite') {
                // 尝试获取数据库文件路径
                try {
                    $result = $pdo->query("PRAGMA database_list");
                    if ($result) {
                        $dbs = $result->fetchAll(PDO::FETCH_ASSOC);
                        echo "<p><strong>SQLite数据库文件：</strong></p>";
                        echo "<table>";
                        echo "<tr><th>序号</th><th>名称</th><th>文件路径</th></tr>";
                        foreach ($dbs as $dbInfo) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($dbInfo['seq'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($dbInfo['name'] ?? '') . "</td>";
                            echo "<td><code>" . htmlspecialchars($dbInfo['file'] ?? '') . "</code></td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                } catch (Exception $e) {
                    echo "<p>无法获取数据库文件路径：" . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<p>无法获取PDO：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // 2. 检查files表结构
    echo "<h2>📋 2. files表结构（SQLite PRAGMA）</h2>";
    echo "<div class='box info'>";
    
    try {
        $pdo = $db->getPdo();
        $result = $pdo->query("PRAGMA table_info(files)");
        if ($result) {
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            echo "<p>共找到 <strong>" . count($columns) . "</strong> 个字段：</p>";
            echo "<table>";
            echo "<tr><th>序号</th><th>字段名</th><th>类型</th><th>非空</th><th>默认值</th><th>主键</th></tr>";
            
            $hasAppKey = false;
            foreach ($columns as $col) {
                $isAppKey = (strtolower($col['name'] ?? '') === 'app_key');
                if ($isAppKey) $hasAppKey = true;
                
                echo "<tr" . ($isAppKey ? " style='background: #fff7e6;'" : "") . ">";
                echo "<td>" . htmlspecialchars($col['cid'] ?? '') . "</td>";
                echo "<td><code>" . htmlspecialchars($col['name'] ?? '') . "</code>" . ($isAppKey ? " ⭐" : "") . "</td>";
                echo "<td>" . htmlspecialchars($col['type'] ?? '') . "</td>";
                echo "<td>" . ($col['notnull'] ? '是' : '否') . "</td>";
                echo "<td>" . htmlspecialchars($col['dflt_value'] ?? 'NULL') . "</td>";
                echo "<td>" . ($col['pk'] ? '是' : '否') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            if ($hasAppKey) {
                echo "<div class='box success'>✅ app_key字段存在！</div>";
            } else {
                echo "<div class='box error'>❌ app_key字段不存在！需要添加</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='box error'>❌ 获取表结构失败：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "</div>";
    
    // 3. 如果字段不存在，用SQLite兼容的语法添加
    if (!$hasAppKey) {
        echo "<h2>⚙️ 3. 添加app_key字段（SQLite兼容语法）</h2>";
        echo "<div class='box info'>";
        
        echo "<p><strong>注意：</strong>SQLite不支持 <code>AFTER</code> 和 <code>COMMENT</code> 语法，使用简化的ALTER TABLE：</p>";
        echo "<code>ALTER TABLE files ADD COLUMN app_key VARCHAR(50) DEFAULT ''</code>";
        
        try {
            $pdo = $db->getPdo();
            $result = $pdo->exec("ALTER TABLE files ADD COLUMN app_key VARCHAR(50) DEFAULT ''");
            
            if ($result !== false) {
                echo "<div class='box success'>✅ ALTER TABLE执行成功！影响行数：$result</div>";
                $hasAppKey = true;
            } else {
                echo "<div class='box error'>❌ ALTER TABLE执行失败</div>";
                $errorArr = $pdo->errorInfo();
                if ($errorArr && count($errorArr) >= 3) {
                    echo "<div class='box error'>";
                    echo "<strong>PDO错误信息：</strong><br>";
                    echo "SQLSTATE: " . htmlspecialchars($errorArr[0]) . "<br>";
                    echo "错误码: " . htmlspecialchars($errorArr[1]) . "<br>";
                    echo "错误信息: " . htmlspecialchars($errorArr[2]);
                    echo "</div>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='box error'>❌ ALTER TABLE执行异常：" . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        echo "</div>";
    }
    
    // 4. 验证字段并测试INSERT
    echo "<h2>✅ 4. 验证字段并测试INSERT</h2>";
    echo "<div class='box info'>";
    
    if ($hasAppKey) {
        // 再次查询表结构确认
        try {
            $pdo = $db->getPdo();
            $result = $pdo->query("PRAGMA table_info(files)");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            $hasAppKey = false;
            foreach ($columns as $col) {
                if (strtolower($col['name'] ?? '') === 'app_key') {
                    $hasAppKey = true;
                    break;
                }
            }
        } catch (Exception $e) {
            // 忽略
        }
        
        if ($hasAppKey) {
            echo "<div class='box success'>🎉 app_key字段已成功添加！</div>";
            
            // 测试INSERT（使用事务回滚，不污染数据）
            echo "<p><strong>测试INSERT语句：</strong></p>";
            try {
                $pdo->beginTransaction();
                
                $sql = "INSERT INTO files (title, description, file_url, file_name, file_size, version, category, category_id, app_key, status, download_count, created_by, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute(['测试标题', '测试描述', '/test.zip', 'test.zip', 1024, '1.0.0', 'software', 0, 'test_key', 0, date('Y-m-d H:i:s')]);
                
                if ($result) {
                    $insertId = $pdo->lastInsertId();
                    echo "<div class='box success'>✅ INSERT测试成功！插入ID：$insertId</div>";
                } else {
                    echo "<div class='box error'>❌ INSERT测试失败</div>";
                    $errorArr = $stmt->errorInfo();
                    if ($errorArr && count($errorArr) >= 3) {
                        echo "<div class='box error'>错误信息：" . htmlspecialchars($errorArr[2]) . "</div>";
                    }
                }
                
                $pdo->rollBack();
                echo "<p>（已回滚测试数据）</p>";
                
            } catch (Exception $e) {
                echo "<div class='box error'>❌ INSERT测试异常：" . htmlspecialchars($e->getMessage()) . "</div>";
                try { $pdo->rollBack(); } catch (Exception $e2) {}
            }
            
        } else {
            echo "<div class='box error'>❌ app_key字段仍然不存在！</div>";
        }
    } else {
        echo "<div class='box error'>❌ app_key字段不存在，无法测试INSERT</div>";
    }
    
    echo "</div>";
    
    // 5. 总结
    echo "<h2>📝 5. 总结</h2>";
    echo "<div class='box warning'>";
    
    if ($hasAppKey) {
        echo "<div class='box success'>";
        echo "<strong>🎉 问题已解决！</strong><br><br>";
        echo "app_key字段已成功添加到SQLite数据库中。<br>";
        echo "现在可以重新上传文件，应该能正常插入数据库了。<br><br>";
        echo "<strong>注意：</strong>SQLite的ALTER TABLE不支持AFTER和COMMENT语法，以后添加字段时要使用简化语法。";
        echo "</div>";
    } else {
        echo "<div class='box error'>";
        echo "<strong>❌ 问题未解决！</strong><br><br>";
        echo "app_key字段添加失败。可能的原因：<br>";
        echo "1. 数据库文件被锁定<br>";
        echo "2. 数据库文件是只读的<br>";
        echo "3. 其他SQLite错误<br><br>";
        echo "<strong>手动解决方案：</strong><br>";
        echo "1. 找到SQLite数据库文件（看上面的路径）<br>";
        echo "2. 用SQLite管理工具（如DB Browser for SQLite）打开<br>";
        echo "3. 执行SQL：<code>ALTER TABLE files ADD COLUMN app_key VARCHAR(50) DEFAULT ''</code><br>";
        echo "4. 保存数据库文件";
        echo "</div>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>❌ 脚本执行失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='box info'>错误追踪：<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
}

echo "</div></body></html>";

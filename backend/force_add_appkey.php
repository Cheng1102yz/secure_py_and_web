<?php
/**
 * 强制添加app_key字段脚本
 * 功能：直接在网站使用的数据库中给files表添加app_key字段
 * 使用方法：访问此文件，会自动执行ALTER TABLE并显示详细结果
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
    <title>强制添加app_key字段</title>
    <style>
        body { font-family: '微软雅黑', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; padding: 40px 20px; }
        .container { background: white; border-radius: 16px; padding: 40px; max-width: 900px; margin: 0 auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { color: #333; margin-top: 0; text-align: center; }
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
<h1>🔧 强制添加app_key字段</h1>";

try {
    $db = Database::getInstance();
    
    // 1. 显示当前数据库信息
    echo "<h2 style='color: #409eff; border-bottom: 2px solid #409eff; padding-bottom: 8px;'>📊 1. 当前数据库信息</h2>";
    echo "<div class='box info'>";
    
    try {
        $dbName = $db->fetchOne("SELECT DATABASE() as db_name");
        echo "<p><strong>当前数据库：</strong><code>" . htmlspecialchars($dbName['db_name'] ?? '未知') . "</code></p>";
    } catch (Exception $e) {
        echo "<p>无法获取数据库名：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 显示PDO连接信息
    try {
        $pdo = $db->getPdo();
        if ($pdo) {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            echo "<p><strong>数据库驱动：</strong><code>$driver</code></p>";
        }
    } catch (Exception $e) {
        // 忽略
    }
    
    echo "</div>";
    
    // 2. 检查files表是否存在
    echo "<h2 style='color: #409eff; border-bottom: 2px solid #409eff; padding-bottom: 8px;'>📋 2. 检查files表</h2>";
    echo "<div class='box info'>";
    
    try {
        $test = $db->fetchOne("SELECT COUNT(*) as cnt FROM files");
        echo "<div class='box success'>✅ files表存在，当前有 " . ($test['cnt'] ?? 0) . " 条记录</div>";
    } catch (Exception $e) {
        echo "<div class='box error'>❌ files表不存在或无法访问：" . htmlspecialchars($e->getMessage()) . "</div>";
        echo "</div></div></body></html>";
        exit;
    }
    
    echo "</div>";
    
    // 3. 检查app_key字段是否存在（直接查询，不依赖information_schema）
    echo "<h2 style='color: #409eff; border-bottom: 2px solid #409eff; padding-bottom: 8px;'>🔍 3. 检查app_key字段</h2>";
    echo "<div class='box info'>";
    
    $appKeyExists = false;
    try {
        $test = $db->fetchOne("SELECT app_key FROM files LIMIT 1");
        $appKeyExists = true;
        echo "<div class='box success'>✅ app_key字段存在（查询成功）</div>";
    } catch (Exception $e) {
        echo "<div class='box warning'>⚠️ app_key字段不存在！查询失败：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "</div>";
    
    // 4. 如果字段不存在，尝试添加
    if (!$appKeyExists) {
        echo "<h2 style='color: #409eff; border-bottom: 2px solid #409eff; padding-bottom: 8px;'>⚙️ 4. 尝试添加app_key字段</h2>";
        echo "<div class='box info'>";
        
        // 方法1：直接执行ALTER TABLE
        echo "<p><strong>方法1：直接执行ALTER TABLE</strong></p>";
        try {
            $result = $db->execute("ALTER TABLE files ADD COLUMN app_key VARCHAR(50) DEFAULT '' COMMENT '软件唯一标识'");
            if ($result) {
                echo "<div class='box success'>✅ ALTER TABLE执行成功！</div>";
                $appKeyExists = true;
            } else {
                echo "<div class='box error'>❌ ALTER TABLE执行失败，返回false</div>";
                
                // 获取PDO错误信息
                try {
                    $pdo = $db->getPdo();
                    if ($pdo) {
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
                } catch (Exception $e2) {
                    echo "<p>无法获取PDO错误信息：" . htmlspecialchars($e2->getMessage()) . "</p>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='box error'>❌ ALTER TABLE执行异常：" . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 方法2：如果方法1失败，尝试用PDO直接执行
        if (!$appKeyExists) {
            echo "<p style='margin-top: 20px;'><strong>方法2：用PDO直接执行（绕过Database类）</strong></p>";
            try {
                $pdo = $db->getPdo();
                if ($pdo) {
                    $result = $pdo->exec("ALTER TABLE files ADD COLUMN app_key VARCHAR(50) DEFAULT '' COMMENT '软件唯一标识'");
                    if ($result !== false) {
                        echo "<div class='box success'>✅ PDO直接执行成功！影响行数：$result</div>";
                        $appKeyExists = true;
                    } else {
                        echo "<div class='box error'>❌ PDO执行失败</div>";
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
                } else {
                    echo "<div class='box error'>❌ 无法获取PDO对象</div>";
                }
            } catch (Exception $e) {
                echo "<div class='box error'>❌ PDO执行异常：" . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        echo "</div>";
    }
    
    // 5. 验证字段是否添加成功
    echo "<h2 style='color: #409eff; border-bottom: 2px solid #409eff; padding-bottom: 8px;'>✅ 5. 验证字段</h2>";
    echo "<div class='box info'>";
    
    if ($appKeyExists) {
        try {
            $test = $db->fetchOne("SELECT app_key FROM files LIMIT 1");
            echo "<div class='box success'>🎉 app_key字段已成功添加！现在可以正常使用了。</div>";
            
            // 显示表结构
            echo "<p><strong>当前files表字段：</strong></p>";
            echo "<table>";
            echo "<tr><th>字段名</th><th>类型</th><th>默认值</th></tr>";
            try {
                $columns = $db->fetchAll("SHOW COLUMNS FROM files");
                foreach ($columns as $col) {
                    $isAppKey = ($col['Field'] === 'app_key');
                    echo "<tr" . ($isAppKey ? " style='background: #fff7e6;'" : "") . ">";
                    echo "<td><code>" . htmlspecialchars($col['Field']) . "</code>" . ($isAppKey ? " ⭐" : "") . "</td>";
                    echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
                    echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
                    echo "</tr>";
                }
            } catch (Exception $e) {
                echo "<tr><td colspan='3'>无法获取表结构：" . htmlspecialchars($e->getMessage()) . "</td></tr>";
            }
            echo "</table>";
            
        } catch (Exception $e) {
            echo "<div class='box error'>❌ 验证失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='box error'>❌ app_key字段添加失败！</div>";
        echo "<div class='box warning'>";
        echo "<strong>可能的原因：</strong><br>";
        echo "1. 数据库用户没有ALTER权限<br>";
        echo "2. 数据库是只读的<br>";
        echo "3. 表被锁定<br>";
        echo "4. 其他数据库错误<br><br>";
        echo "<strong>解决方案：</strong><br>";
        echo "1. 登录数据库管理工具（如phpMyAdmin、Navicat）<br>";
        echo "2. 找到files表，手动添加字段：<br>";
        echo "&nbsp;&nbsp;&nbsp;字段名：<code>app_key</code><br>";
        echo "&nbsp;&nbsp;&nbsp;类型：<code>VARCHAR(50)</code><br>";
        echo "&nbsp;&nbsp;&nbsp;默认值：<code>''</code><br>";
        echo "&nbsp;&nbsp;&nbsp;注释：<code>软件唯一标识</code><br>";
        echo "3. 添加完成后重新访问此页面验证";
        echo "</div>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>❌ 脚本执行失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='box info'>错误追踪：<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
}

echo "</div></body></html>";

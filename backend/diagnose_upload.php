<?php
/**
 * 文件上传诊断脚本
 * 功能：检查数据库中的文件记录，找出上传成功但不显示的原因
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
    <title>文件上传诊断</title>
    <style>
        body { font-family: '微软雅黑', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; padding: 40px 20px; }
        .container { background: white; border-radius: 16px; padding: 40px; max-width: 1100px; margin: 0 auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
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
        .bad { color: #f56c6c; font-weight: bold; }
        .good { color: #67c23a; font-weight: bold; }
        .step { background: #f5f7fa; padding: 12px 16px; margin: 8px 0; border-radius: 8px; font-size: 14px; }
        .num { display: inline-block; width: 24px; height: 24px; background: #409eff; color: white; border-radius: 50%; text-align: center; line-height: 24px; font-weight: bold; margin-right: 8px; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔍 文件上传诊断</h1>";

try {
    $db = Database::getInstance();
    
    // 1. 检查files表结构
    echo "<h2>📊 1. 检查files表结构</h2>";
    echo "<div class='box info'>";
    try {
        $columns = $db->fetchAll("SHOW COLUMNS FROM files");
        echo "<table>";
        echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>默认值</th></tr>";
        foreach ($columns as $col) {
            $isAppKey = ($col['Field'] === 'app_key');
            echo "<tr" . ($isAppKey ? " style='background: #fff7e6;'" : "") . ">";
            echo "<td><code>" . htmlspecialchars($col['Field']) . "</code>" . ($isAppKey ? " ⭐" : "") . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<div class='box error'>❌ 无法获取表结构：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";
    
    // 2. 查询所有文件记录（包括下架的）
    echo "<h2>📋 2. 查询所有文件记录（包括下架的）</h2>";
    echo "<div class='box info'>";
    try {
        $allFiles = $db->fetchAll("SELECT * FROM files ORDER BY id DESC LIMIT 20");
        echo "<p>共找到 <strong>" . count($allFiles) . "</strong> 条记录（最近20条）</p>";
        
        if (empty($allFiles)) {
            echo "<div class='box error'>❌ 数据库中没有任何文件记录！说明上传时数据库插入失败了。</div>";
        } else {
            echo "<table>";
            echo "<tr><th>ID</th><th>标题</th><th>版本</th><th>分类</th><th>app_key</th><th>状态</th><th>文件大小</th><th>创建时间</th></tr>";
            foreach ($allFiles as $file) {
                $status = (int)$file['status'];
                $statusText = $status == 1 ? "<span class='good'>上架</span>" : "<span class='bad'>下架</span>";
                $sizeMB = round($file['file_size'] / 1024 / 1024, 2);
                
                echo "<tr>";
                echo "<td>" . htmlspecialchars($file['id']) . "</td>";
                echo "<td>" . htmlspecialchars($file['title']) . "</td>";
                echo "<td>" . htmlspecialchars($file['version']) . "</td>";
                echo "<td>" . htmlspecialchars($file['category']) . "</td>";
                echo "<td><code>" . htmlspecialchars($file['app_key'] ?? 'NULL') . "</code></td>";
                echo "<td>$statusText</td>";
                echo "<td>" . $sizeMB . " MB</td>";
                echo "<td>" . htmlspecialchars($file['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<div class='box error'>❌ 查询失败：" . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";
    
    // 3. 检查上传目录
    echo "<h2>📁 3. 检查上传目录</h2>";
    echo "<div class='box info'>";
    $uploadDir = __DIR__ . '/../uploads/files/';
    if (is_dir($uploadDir)) {
        echo "<div class='box success'>✅ 上传目录存在：<code>$uploadDir</code></div>";
        
        $files = glob($uploadDir . '*');
        echo "<p>目录中有 <strong>" . count($files) . "</strong> 个文件</p>";
        
        if (!empty($files)) {
            echo "<table>";
            echo "<tr><th>文件名</th><th>大小</th><th>修改时间</th></tr>";
            foreach (array_slice($files, -10) as $f) {
                $name = basename($f);
                $size = round(filesize($f) / 1024 / 1024, 2);
                $time = date('Y-m-d H:i:s', filemtime($f));
                echo "<tr>";
                echo "<td><code>$name</code></td>";
                echo "<td>$size MB</td>";
                echo "<td>$time</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='box error'>❌ 上传目录不存在：<code>$uploadDir</code></div>";
    }
    echo "</div>";
    
    // 4. 诊断结果和解决方案
    echo "<h2>🎯 4. 诊断结果和解决方案</h2>";
    echo "<div class='box warning'>";
    
    $hasFilesInDB = !empty($allFiles);
    $hasFilesInDir = !empty($files);
    
    if (!$hasFilesInDB && $hasFilesInDir) {
        echo "<div class='box error'>";
        echo "<strong>❌ 问题找到了！</strong><br><br>";
        echo "文件上传成功（目录中有文件），但是<strong>数据库插入失败</strong>（数据库中没有记录）！<br><br>";
        echo "<strong>可能的原因：</strong><br>";
        echo "1. INSERT语句的字段数量和参数数量不匹配<br>";
        echo "2. app_key字段的问题导致插入失败<br>";
        echo "3. 数据库用户没有INSERT权限<br>";
        echo "4. 其他数据库错误";
        echo "</div>";
        
        echo "<div class='box info'>";
        echo "<strong>🔧 解决方案：</strong><br><br>";
        echo "<div class='step'><span class='num'>1</span> 检查后端代码handleFileUploadReal函数中的INSERT语句</div>";
        echo "<div class='step'><span class='num'>2</span> 确保字段数量和参数数量匹配</div>";
        echo "<div class='step'><span class='num'>3</span> 在INSERT后添加错误检查，看看具体的错误信息</div>";
        echo "<div class='step'><span class='num'>4</span> 或者临时去掉app_key字段，看看是否能正常插入</div>";
        echo "</div>";
        
    } elseif ($hasFilesInDB) {
        // 检查是否有status=0的记录
        $offlineCount = 0;
        foreach ($allFiles as $f) {
            if ((int)$f['status'] !== 1) $offlineCount++;
        }
        
        if ($offlineCount > 0) {
            echo "<div class='box warning'>";
            echo "<strong>⚠️ 发现问题！</strong><br><br>";
            echo "数据库中有 <strong>$offlineCount</strong> 条记录是<strong>下架状态</strong>（status≠1），所以前台不显示！<br><br>";
            echo "<strong>解决方案：</strong>在后台文件管理中把这些文件上架，或者修改上传代码，确保插入时status=1。";
            echo "</div>";
        } else {
            echo "<div class='box success'>";
            echo "<strong>✅ 数据库记录正常！</strong><br><br>";
            echo "数据库中有文件记录，且都是上架状态。<br>";
            echo "如果前台还是不显示，可能是前端查询接口的问题。";
            echo "</div>";
        }
    } else {
        echo "<div class='box error'>";
        echo "<strong>❌ 数据库和目录中都没有文件！</strong><br><br>";
        echo "说明上传完全失败了，可能是上传大小限制、权限问题或其他错误。";
        echo "</div>";
    }
    
    echo "</div>";
    
    // 5. 快速修复：把所有文件设为上架
    echo "<h2>⚡ 5. 快速修复工具</h2>";
    echo "<div class='box info'>";
    echo "<p>如果数据库中有文件但是status=0，可以点击下面的按钮一键上架所有文件：</p>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='action' value='publish_all'>";
    echo "<button type='submit' style='padding: 12px 24px; background: #67c23a; color: white; border: none; border-radius: 8px; font-size: 14px; cursor: pointer;'>🚀 一键上架所有文件</button>";
    echo "</form>";
    
    if (isset($_POST['action']) && $_POST['action'] === 'publish_all') {
        try {
            $db->execute("UPDATE files SET status = 1 WHERE status != 1");
            echo "<div class='box success'>✅ 已成功上架所有文件！请刷新前台页面查看。</div>";
        } catch (Exception $e) {
            echo "<div class='box error'>❌ 上架失败：" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>❌ 诊断失败: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";

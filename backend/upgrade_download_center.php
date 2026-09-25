<?php
/**
 * ============================================================
 * SecureVault 下载中心数据库升级脚本
 * ============================================================
 * 功能：
 *   1. 给 files 表添加 app_key 字段（软件唯一标识，用于历史版本归类）
 *   2. 给 files 表添加 image_url 字段（软件图片标识）
 *   3. 创建软件图片上传目录
 *   4. 显示升级结果
 * 
 * 使用方法：
 *   1. 将此文件放到 backend 目录下
 *   2. 在浏览器中访问：http://你的域名/backend/upgrade_download_center.php
 *   3. 查看升级结果
 *   4. 升级完成后建议删除此文件
 * ============================================================
 */

require_once __DIR__ . '/db.php';

$db = Database::getInstance();
$results = [];

echo "<!DOCTYPE html>";
echo "<html lang='zh-CN'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>下载中心数据库升级</title>";
echo "<style>";
echo "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f7fa; }";
echo ".container { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }";
echo "h1 { color: #303133; font-size: 24px; margin-bottom: 20px; }";
echo ".step { margin-bottom: 20px; padding: 16px; border-radius: 8px; border-left: 4px solid #409eff; background: #f0f7ff; }";
echo ".step.success { border-left-color: #67c23a; background: #f0f9eb; }";
echo ".step.error { border-left-color: #f56c6c; background: #fef0f0; }";
echo ".step.warning { border-left-color: #e6a23c; background: #fdf6ec; }";
echo ".step-title { font-weight: 600; color: #303133; margin-bottom: 8px; }";
echo ".step-content { color: #606266; font-size: 14px; line-height: 1.6; }";
echo ".success-text { color: #67c23a; }";
echo ".error-text { color: #f56c6c; }";
echo ".warning-text { color: #e6a23c; }";
echo "code { background: #f5f7fa; padding: 2px 6px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; }";
echo ".file-list { background: #f5f7fa; padding: 16px; border-radius: 8px; margin-top: 10px; }";
echo ".file-list li { margin-bottom: 6px; color: #606266; }";
echo ".file-list .modified { color: #67c23a; }";
echo ".file-list .need-modify { color: #e6a23c; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<h1>📦 下载中心数据库升级</h1>";

// ============================================================
// 步骤1：检查并添加 app_key 字段
// ============================================================
echo "<div class='step'>";
echo "<div class='step-title'>步骤 1：检查并添加 app_key 字段</div>";
echo "<div class='step-content'>";

try {
    $columns = $db->fetchAll("PRAGMA table_info(files)");
    $hasAppKey = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'app_key') {
            $hasAppKey = true;
            break;
        }
    }
    
    if ($hasAppKey) {
        echo "<span class='success-text'>✅ app_key 字段已存在，跳过添加</span>";
        $results[] = ['step' => 'app_key', 'status' => 'exists'];
    } else {
        $db->execute("ALTER TABLE files ADD COLUMN app_key TEXT DEFAULT ''");
        echo "<span class='success-text'>✅ 成功添加 app_key 字段</span>";
        $results[] = ['step' => 'app_key', 'status' => 'added'];
    }
} catch (Exception $e) {
    echo "<span class='error-text'>❌ 添加 app_key 字段失败：" . htmlspecialchars($e->getMessage()) . "</span>";
    $results[] = ['step' => 'app_key', 'status' => 'error', 'msg' => $e->getMessage()];
}

echo "</div></div>";

// ============================================================
// 步骤2：检查并添加 image_url 字段
// ============================================================
echo "<div class='step'>";
echo "<div class='step-title'>步骤 2：检查并添加 image_url 字段</div>";
echo "<div class='step-content'>";

try {
    $columns = $db->fetchAll("PRAGMA table_info(files)");
    $hasImageUrl = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'image_url') {
            $hasImageUrl = true;
            break;
        }
    }
    
    if ($hasImageUrl) {
        echo "<span class='success-text'>✅ image_url 字段已存在，跳过添加</span>";
        $results[] = ['step' => 'image_url', 'status' => 'exists'];
    } else {
        $db->execute("ALTER TABLE files ADD COLUMN image_url TEXT DEFAULT ''");
        echo "<span class='success-text'>✅ 成功添加 image_url 字段</span>";
        $results[] = ['step' => 'image_url', 'status' => 'added'];
    }
} catch (Exception $e) {
    echo "<span class='error-text'>❌ 添加 image_url 字段失败：" . htmlspecialchars($e->getMessage()) . "</span>";
    $results[] = ['step' => 'image_url', 'status' => 'error', 'msg' => $e->getMessage()];
}

echo "</div></div>";

// ============================================================
// 步骤3：创建软件图片上传目录
// ============================================================
echo "<div class='step'>";
echo "<div class='step-title'>步骤 3：创建软件图片上传目录</div>";
echo "<div class='step-content'>";

$uploadDir = __DIR__ . '/../uploads/software_images/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "<span class='success-text'>✅ 成功创建目录：<code>uploads/software_images/</code></span>";
        $results[] = ['step' => 'upload_dir', 'status' => 'created'];
    } else {
        echo "<span class='error-text'>❌ 创建目录失败，请检查权限</span>";
        $results[] = ['step' => 'upload_dir', 'status' => 'error'];
    }
} else {
    echo "<span class='success-text'>✅ 目录已存在：<code>uploads/software_images/</code></span>";
    $results[] = ['step' => 'upload_dir', 'status' => 'exists'];
}

echo "</div></div>";

// ============================================================
// 步骤4：验证数据库字段
// ============================================================
echo "<div class='step'>";
echo "<div class='step-title'>步骤 4：验证数据库字段</div>";
echo "<div class='step-content'>";

try {
    $columns = $db->fetchAll("PRAGMA table_info(files)");
    echo "<p>files 表现有字段：</p>";
    echo "<ul style='margin: 0; padding-left: 20px;'>";
    foreach ($columns as $col) {
        $highlight = '';
        if ($col['name'] === 'app_key' || $col['name'] === 'image_url') {
            $highlight = "style='color: #67c23a; font-weight: 600;'";
        }
        echo "<li $highlight>" . htmlspecialchars($col['name']) . " (" . htmlspecialchars($col['type']) . ")</li>";
    }
    echo "</ul>";
    $results[] = ['step' => 'verify', 'status' => 'success'];
} catch (Exception $e) {
    echo "<span class='error-text'>❌ 验证失败：" . htmlspecialchars($e->getMessage()) . "</span>";
    $results[] = ['step' => 'verify', 'status' => 'error'];
}

echo "</div></div>";

// ============================================================
// 需要修改的文件清单
// ============================================================
echo "<div class='step warning'>";
echo "<div class='step-title'>📋 需要修改的文件清单</div>";
echo "<div class='step-content'>";
echo "<p>以下文件需要同步修改以支持新功能：</p>";
echo "<div class='file-list'>";
echo "<ul>";
echo "<li class='modified'>✅ <code>backend/file.php</code> - 后端API（版本历史按app_key归类、支持image_url字段、支持图片上传）</li>";
echo "<li class='modified'>✅ <code>frontend/downloads.html</code> - 前端下载中心（显示软件图片、版本历史按app_key调用）</li>";
echo "<li class='modified'>✅ <code>frontend/admin.html</code> - 管理员后台（上传表单添加图片上传字段）</li>";
echo "<li class='modified'>✅ <code>database/securevault.db</code> - 数据库（files表添加app_key和image_url字段）</li>";
echo "</ul>";
echo "</div>";
echo "<p style='margin-top: 12px;'><strong>核心变化：</strong></p>";
echo "<ul style='margin: 0; padding-left: 20px;'>";
echo "<li>历史版本归类方式：从按 <code>title</code>（软件名字）改成按 <code>app_key</code>（软件唯一标识）</li>";
echo "<li>软件名字只作为显示用途，不再作为判断标准</li>";
echo "<li>新增软件图片标识功能，支持上传和显示软件图片</li>";
echo "</ul>";
echo "</div></div>";

// ============================================================
// 升级完成
// ============================================================
$allSuccess = true;
foreach ($results as $r) {
    if ($r['status'] === 'error') {
        $allSuccess = false;
        break;
    }
}

if ($allSuccess) {
    echo "<div class='step success'>";
    echo "<div class='step-title'>🎉 升级完成！</div>";
    echo "<div class='step-content'>";
    echo "<p>数据库升级已成功完成！</p>";
    echo "<p><strong>建议：</strong>升级完成后请删除此脚本文件，以确保安全。</p>";
    echo "<p>现在可以刷新网页，测试下载中心的新功能了！</p>";
    echo "</div></div>";
} else {
    echo "<div class='step error'>";
    echo "<div class='step-title'>⚠️ 升级过程中出现错误</div>";
    echo "<div class='step-content'>";
    echo "<p>请查看上面的错误信息，解决后重新运行此脚本。</p>";
    echo "</div></div>";
}

echo "</div>";
echo "</body>";
echo "</html>";

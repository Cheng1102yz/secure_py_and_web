<?php
/**
 * PHP上传配置深度诊断脚本
 * 功能：找出配置不生效的真正原因
 * 作者：work_by_cyz
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>PHP上传配置深度诊断</title>
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
        code { background: #f5f7fa; padding: 4px 8px; border-radius: 4px; font-family: Consolas, monospace; font-size: 13px; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ebeef5; font-size: 14px; }
        th { background: #f5f7fa; color: #606266; font-weight: bold; }
        .bad { color: #f56c6c; font-weight: bold; }
        .good { color: #67c23a; font-weight: bold; }
        .step { background: #f5f7fa; padding: 12px 16px; margin: 8px 0; border-radius: 8px; font-size: 14px; }
        .num { display: inline-block; width: 24px; height: 24px; background: #409eff; color: white; border-radius: 50%; text-align: center; line-height: 24px; font-weight: bold; margin-right: 8px; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔬 PHP上传配置深度诊断</h1>";

// 1. 基本信息
echo "<h2>📊 1. 基本信息</h2>";
echo "<div class='box info'>";
echo "<table>";
echo "<tr><th>项目</th><th>值</th></tr>";
echo "<tr><td>PHP版本</td><td><code>" . phpversion() . "</code></td></tr>";
echo "<tr><td>运行方式</td><td><code>" . (php_sapi_name()) . "</code></td></tr>";
echo "<tr><td>Web服务器</td><td><code>" . ($_SERVER['SERVER_SOFTWARE'] ?? '未知') . "</code></td></tr>";
echo "<tr><td>已加载php.ini</td><td><code>" . (php_ini_loaded_file() ?: '无') . "</code></td></tr>";
echo "<tr><td>扫描的ini目录</td><td><code>" . (get_cfg_var('cfg_file_path') ?: '无') . "</code></td></tr>";
echo "</table>";
echo "</div>";

// 2. 上传配置对比（本地值 vs 全局值）
echo "<h2>⚙️ 2. 上传配置对比（本地值 vs 全局值）</h2>";
echo "<div class='box info'>";
echo "<table>";
echo "<tr><th>配置项</th><th>本地值(当前生效)</th><th>全局值(php.ini)</th><th>可修改范围</th><th>状态</th></tr>";

$configs = [
    'upload_max_filesize' => '200M',
    'post_max_size' => '200M',
    'max_execution_time' => '300',
    'max_input_time' => '300',
    'memory_limit' => '128M',
    'file_uploads' => 'On',
    'max_file_uploads' => '20',
];

foreach ($configs as $key => $suggest) {
    $local = ini_get($key);
    $global = get_cfg_var($key);
    $access = ini_get_all($key)['access'] ?? '未知';
    
    $status = '';
    if ($key === 'upload_max_filesize' || $key === 'post_max_size') {
        $localBytes = return_bytes($local);
        if ($localBytes < 70 * 1024 * 1024) {
            $status = "<span class='bad'>❌ 太小</span>";
        } else {
            $status = "<span class='good'>✅ 足够</span>";
        }
    }
    
    echo "<tr>";
    echo "<td><code>$key</code></td>";
    echo "<td class='bad'>$local</td>";
    echo "<td>" . ($global ?: '未设置') . "</td>";
    echo "<td><code>$access</code></td>";
    echo "<td>$status</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 3. 检查配置不生效的原因
echo "<h2>🔍 3. 配置不生效原因排查</h2>";

$issues = [];

// 检查1：是否有.htaccess覆盖
echo "<div class='box info'>";
echo "<strong>检查1：是否有.htaccess文件覆盖配置</strong><br><br>";
$htaccessPaths = [
    __DIR__ . '/.htaccess',
    __DIR__ . '/../.htaccess',
    __DIR__ . '/../../.htaccess',
];
$foundHtaccess = false;
foreach ($htaccessPaths as $path) {
    if (file_exists($path)) {
        $foundHtaccess = true;
        echo "<div class='box warning'>⚠️ 找到.htaccess：<code>$path</code><br>";
        $content = file_get_contents($path);
        if (stripos($content, 'php_value') !== false || stripos($content, 'php_admin_value') !== false) {
            echo "<span class='bad'>❌ 该文件包含php_value配置，可能覆盖php.ini！</span><br>";
            echo "<pre style='background: white; padding: 10px; border-radius: 4px; font-size: 12px;'>" . htmlspecialchars($content) . "</pre>";
        } else {
            echo "<span class='good'>✅ 该文件不包含php_value配置</span>";
        }
        echo "</div>";
    }
}
if (!$foundHtaccess) {
    echo "<span class='good'>✅ 未找到.htaccess文件</span>";
}
echo "</div>";

// 检查2：Apache配置中的php_value
echo "<div class='box info'>";
echo "<strong>检查2：Apache配置中是否有php_value（需要查看httpd.conf）</strong><br><br>";
echo "<span class='warning'>⚠️ 无法自动检测，请手动检查Apache的httpd.conf或vhosts配置中是否有：</span><br>";
echo "<code>php_value upload_max_filesize 2M</code><br>";
echo "<code>php_value post_max_size 8M</code><br>";
echo "如果有，需要修改或删除这些配置。";
echo "</div>";

// 检查3：php.ini中是否有多个相同配置
echo "<div class='box info'>";
echo "<strong>检查3：php.ini中是否有多个相同配置项</strong><br><br>";
$iniPath = php_ini_loaded_file();
if ($iniPath && file_exists($iniPath)) {
    $content = file_get_contents($iniPath);
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $count = preg_match_all('/^\s*' . $key . '\s*=/m', $content);
        if ($count > 1) {
            echo "<div class='box error'>❌ <code>$key</code> 在php.ini中出现了 <strong>$count</strong> 次！后面的会覆盖前面的，请只保留一个。</div>";
        } else {
            echo "<div class='box success'>✅ <code>$key</code> 在php.ini中只出现了 $count 次</div>";
        }
    }
} else {
    echo "<span class='warning'>⚠️ 无法读取php.ini文件</span>";
}
echo "</div>";

// 检查4：配置项是否被注释
echo "<div class='box info'>";
echo "<strong>检查4：配置项是否被分号注释</strong><br><br>";
if ($iniPath && file_exists($iniPath)) {
    $content = file_get_contents($iniPath);
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        if (preg_match('/^\s*;\s*' . $key . '\s*=/m', $content)) {
            echo "<div class='box error'>❌ <code>$key</code> 被分号（;）注释了！请去掉分号。</div>";
        } else {
            echo "<div class='box success'>✅ <code>$key</code> 没有被注释</div>";
        }
    }
}
echo "</div>";

// 4. 终极解决方案
echo "<h2>🚀 4. 终极解决方案（推荐）</h2>";
echo "<div class='box warning'>";
echo "<strong>如果以上检查都没问题，但配置还是不生效，试试以下方法：</strong><br><br>";

echo "<div class='step'><span class='num'>1</span> <strong>完全停止Apache，再启动（不是重启）</strong><br>";
echo "Win+R → services.msc → 找到Apache → 右键 → 停止 → 等待5秒 → 右键 → 启动</div>";

echo "<div class='step'><span class='num'>2</span> <strong>确认修改的是正确的php.ini</strong><br>";
echo "以本页面顶部显示的「已加载php.ini」路径为准，不要修改其他位置的php.ini</div>";

echo "<div class='step'><span class='num'>3</span> <strong>在php.ini末尾添加配置（确保不被覆盖）</strong><br>";
echo "打开php.ini，拉到文件最末尾，添加：<br>";
echo "<div style='background: white; padding: 10px; border-radius: 4px; font-family: Consolas, monospace; font-size: 13px; margin: 8px 0;'>";
echo "[Custom]<br>";
echo "upload_max_filesize = 200M<br>";
echo "post_max_size = 200M<br>";
echo "max_execution_time = 300<br>";
echo "max_input_time = 300";
echo "</div></div>";

echo "<div class='step'><span class='num'>4</span> <strong>检查Apache的httpd.conf中是否指定了PHPIniDir</strong><br>";
echo "打开Apache的conf/httpd.conf，查找是否有：<br>";
echo "<code>PHPIniDir \"C:/path/to/php\"</code><br>";
echo "如果有，确认路径指向正确的PHP目录</div>";

echo "<div class='step'><span class='num'>5</span> <strong>临时方案：用.htaccess覆盖（如果允许）</strong><br>";
echo "在网站根目录创建.htaccess文件，添加：<br>";
echo "<div style='background: white; padding: 10px; border-radius: 4px; font-family: Consolas, monospace; font-size: 13px; margin: 8px 0;'>";
echo "php_value upload_max_filesize 200M<br>";
echo "php_value post_max_size 200M<br>";
echo "php_value max_execution_time 300<br>";
echo "php_value max_input_time 300";
echo "</div>";
echo "<span class='warning'>注意：只有PHP作为Apache模块运行时才有效</span></div>";

echo "</div>";

// 5. 快速验证
echo "<h2>✅ 5. 快速验证</h2>";
echo "<div class='box info'>";
echo "修改完成后，刷新本页面，查看上面的「上传配置对比」表格中：<br>";
echo "- <code>upload_max_filesize</code> 的本地值是否变成了 <strong>200M</strong><br>";
echo "- <code>post_max_size</code> 的本地值是否变成了 <strong>200M</strong><br>";
echo "如果变成了200M，说明配置生效了！";
echo "</div>";

echo "</div></body></html>";

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

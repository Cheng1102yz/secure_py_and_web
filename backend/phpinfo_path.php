<?php
/**
 * PHP信息查看脚本 - 找到正确的php.ini路径
 * 使用方法：访问此文件，查看Loaded Configuration File
 * 作者：work_by_cyz
 */

// 设置响应头
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>PHP信息 - 找到正确的php.ini</title>
    <style>
        body {
            font-family: '微软雅黑', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-top: 0;
            text-align: center;
        }
        .info-box {
            background: #ecf5ff;
            border-left: 4px solid #409eff;
            padding: 16px;
            margin: 15px 0;
            border-radius: 8px;
        }
        .success-box {
            background: #f0f9eb;
            border-left: 4px solid #67c23a;
            padding: 16px;
            margin: 15px 0;
            border-radius: 8px;
        }
        .warning-box {
            background: #fdf6ec;
            border-left: 4px solid #e6a23c;
            padding: 16px;
            margin: 15px 0;
            border-radius: 8px;
        }
        code {
            background: #f5f7fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: Consolas, monospace;
            font-size: 13px;
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
            font-size: 14px;
        }
        th {
            background: #f5f7fa;
            color: #606266;
            font-weight: bold;
        }
        .step {
            background: #f5f7fa;
            padding: 12px 16px;
            margin: 8px 0;
            border-radius: 8px;
            font-size: 14px;
        }
        .step-number {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: #409eff;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 PHP信息 - 找到正确的php.ini</h1>
";

// 获取PHP信息
$phpVersion = phpversion();
$phpIniPath = php_ini_loaded_file();
$phpIniDir = php_ini_scanned_files();
$extensionDir = ini_get('extension_dir');
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '未知';
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '未知';

echo "<div class='info-box'>";
echo "<strong>📊 基本信息：</strong><br><br>";
echo "<table>";
echo "<tr><th>项目</th><th>值</th></tr>";
echo "<tr><td>PHP版本</td><td><code>$phpVersion</code></td></tr>";
echo "<tr><td>Web服务器</td><td><code>$serverSoftware</code></td></tr>";
echo "<tr><td>网站根目录</td><td><code>$documentRoot</code></td></tr>";
echo "<tr><td>扩展目录</td><td><code>$extensionDir</code></td></tr>";
echo "</table>";
echo "</div>";

echo "<div class='success-box'>";
echo "<strong>📂 关键信息 - php.ini路径：</strong><br><br>";
if ($phpIniPath) {
    echo "<div style='font-size: 16px; color: #67c23a; font-weight: bold;'>";
    echo "✅ 已加载的php.ini：<br>";
    echo "<code style='font-size: 14px;'>$phpIniPath</code>";
    echo "</div>";
} else {
    echo "<div style='color: #f56c6c; font-weight: bold;'>";
    echo "❌ 没有加载php.ini文件！使用默认配置。";
    echo "</div>";
}
echo "</div>";

if ($phpIniDir) {
    echo "<div class='info-box'>";
    echo "<strong>📁 扫描的额外ini文件：</strong><br>";
    echo "<code>$phpIniDir</code>";
    echo "</div>";
}

// 当前上传配置
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');

echo "<div class='warning-box'>";
echo "<strong>⚠️ 当前上传配置（需要修改）：</strong><br><br>";
echo "<table>";
echo "<tr><th>配置项</th><th>当前值</th><th>建议值</th></tr>";
echo "<tr><td><code>upload_max_filesize</code></td><td style='color: #f56c6c; font-weight: bold;'>$uploadMaxFilesize</td><td>200M</td></tr>";
echo "<tr><td><code>post_max_size</code></td><td style='color: #f56c6c; font-weight: bold;'>$postMaxSize</td><td>200M</td></tr>";
echo "<tr><td><code>max_execution_time</code></td><td>" . ini_get('max_execution_time') . "秒</td><td>300秒</td></tr>";
echo "<tr><td><code>max_input_time</code></td><td>" . ini_get('max_input_time') . "秒</td><td>300秒</td></tr>";
echo "</table>";
echo "</div>";

// 修改步骤
echo "<div class='info-box'>";
echo "<strong>📝 修改步骤：</strong><br><br>";

if ($phpIniPath) {
    echo "<div class='step'><span class='step-number'>1</span> 用文本编辑器（如记事本、VS Code）打开上面的php.ini文件</div>";
    echo "<div class='step'><span class='step-number'>2</span> 找到以下配置项，修改为建议值：</div>";
    echo "<div style='background: white; padding: 12px; border-radius: 8px; margin: 8px 0; font-family: Consolas, monospace; font-size: 13px;'>";
    echo "upload_max_filesize = 200M<br>";
    echo "post_max_size = 200M<br>";
    echo "max_execution_time = 300<br>";
    echo "max_input_time = 300";
    echo "</div>";
    echo "<div class='step'><span class='step-number'>3</span> 保存文件</div>";
    echo "<div class='step'><span class='step-number'>4</span> 重启Apache服务（Win+R → services.msc → 找到Apache → 右键 → 重新启动）</div>";
    echo "<div class='step'><span class='step-number'>5</span> 重新访问此页面，确认配置已生效</div>";
} else {
    echo "<div class='step'><span class='step-number'>1</span> 在PHP目录下找到 <code>php.ini-development</code> 或 <code>php.ini-production</code></div>";
    echo "<div class='step'><span class='step-number'>2</span> 复制一份，重命名为 <code>php.ini</code></div>";
    echo "<div class='step'><span class='step-number'>3</span> 修改上传配置为建议值</div>";
    echo "<div class='step'><span class='step-number'>4</span> 重启Apache</div>";
}

echo "</div>";

// 常见问题
echo "<div class='warning-box'>";
echo "<strong>❓ 常见问题：</strong><br><br>";
echo "<strong>Q: 修改后重启Apache还是不生效？</strong><br>";
echo "A: 可能原因：<br>";
echo "1. 修改的php.ini不是Apache使用的那个（以本页面显示的路径为准）<br>";
echo "2. 配置项前面有分号（;）注释，需要去掉分号<br>";
echo "3. 有多个相同的配置项，后面的会覆盖前面的<br>";
echo "4. Apache没有真正重启，尝试在服务管理器中先停止再启动<br><br>";

echo "<strong>Q: 找不到php.ini文件？</strong><br>";
echo "A: 本页面已经显示了正确的路径，直接复制路径到文件管理器地址栏打开即可。";
echo "</div>";

echo "
    </div>
</body>
</html>";

<?php
/**
 * PHP配置查看脚本 - 检查上传相关配置
 * 使用方法：访问此文件，查看上传相关配置
 * 作者：work_by_cyz
 */

// 设置响应头
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>PHP配置检查 - 上传相关设置</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ebeef5;
            font-size: 14px;
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
        .status-ok {
            color: #67c23a;
            font-weight: bold;
        }
        .status-bad {
            color: #f56c6c;
            font-weight: bold;
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
    </style>
</head>
<body>
    <div class='container'>
        <h1>⚙️ PHP配置检查 - 上传相关设置</h1>
";

// 获取上传相关配置
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$maxExecutionTime = ini_get('max_execution_time');
$maxInputTime = ini_get('max_input_time');
$memoryLimit = ini_get('memory_limit');
$fileUploads = ini_get('file_uploads');
$maxFileUploads = ini_get('max_file_uploads');
$tmpDir = ini_get('upload_tmp_dir');

// 转换为字节
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

$uploadMaxBytes = return_bytes($uploadMaxFilesize);
$postMaxBytes = return_bytes($postMaxSize);

// 你要上传的文件大小（71.61MB）
$targetFileSize = 71.61 * 1024 * 1024;

echo "<div class='result-item info'>📊 当前PHP上传相关配置：</div>";

echo "<table>";
echo "<tr><th>配置项</th><th>当前值</th><th>状态</th><th>建议</th></tr>";

// file_uploads
echo "<tr>";
echo "<td><code>file_uploads</code></td>";
echo "<td>$fileUploads</td>";
if ($fileUploads && strtolower($fileUploads) !== 'off') {
    echo "<td class='status-ok'>✅ 已开启</td>";
    echo "<td>无需修改</td>";
} else {
    echo "<td class='status-bad'>❌ 已关闭</td>";
    echo "<td>需要开启：<code>file_uploads = On</code></td>";
}
echo "</tr>";

// upload_max_filesize
echo "<tr>";
echo "<td><code>upload_max_filesize</code></td>";
echo "<td>$uploadMaxFilesize</td>";
if ($uploadMaxBytes >= $targetFileSize) {
    echo "<td class='status-ok'>✅ 足够</td>";
    echo "<td>无需修改</td>";
} else {
    echo "<td class='status-bad'>❌ 太小</td>";
    echo "<td>建议修改为：<code>upload_max_filesize = 200M</code></td>";
}
echo "</tr>";

// post_max_size
echo "<tr>";
echo "<td><code>post_max_size</code></td>";
echo "<td>$postMaxSize</td>";
if ($postMaxBytes >= $targetFileSize) {
    echo "<td class='status-ok'>✅ 足够</td>";
    echo "<td>无需修改</td>";
} else {
    echo "<td class='status-bad'>❌ 太小</td>";
    echo "<td>建议修改为：<code>post_max_size = 200M</code></td>";
}
echo "</tr>";

// max_execution_time
echo "<tr>";
echo "<td><code>max_execution_time</code></td>";
echo "<td>$maxExecutionTime 秒</td>";
if ((int)$maxExecutionTime >= 300 || $maxExecutionTime == '0') {
    echo "<td class='status-ok'>✅ 足够</td>";
    echo "<td>无需修改</td>";
} else {
    echo "<td class='status-bad'>⚠️ 可能不足</td>";
    echo "<td>建议修改为：<code>max_execution_time = 300</code></td>";
}
echo "</tr>";

// max_input_time
echo "<tr>";
echo "<td><code>max_input_time</code></td>";
echo "<td>$maxInputTime 秒</td>";
if ((int)$maxInputTime >= 300 || $maxInputTime == '-1' || $maxInputTime == '0') {
    echo "<td class='status-ok'>✅ 足够</td>";
    echo "<td>无需修改</td>";
} else {
    echo "<td class='status-bad'>⚠️ 可能不足</td>";
    echo "<td>建议修改为：<code>max_input_time = 300</code></td>";
}
echo "</tr>";

// memory_limit
echo "<tr>";
echo "<td><code>memory_limit</code></td>";
echo "<td>$memoryLimit</td>";
echo "<td class='status-ok'>✅ -</td>";
echo "<td>一般无需修改</td>";
echo "</tr>";

// max_file_uploads
echo "<tr>";
echo "<td><code>max_file_uploads</code></td>";
echo "<td>$maxFileUploads</td>";
echo "<td class='status-ok'>✅ -</td>";
echo "<td>一般无需修改</td>";
echo "</tr>";

// upload_tmp_dir
echo "<tr>";
echo "<td><code>upload_tmp_dir</code></td>";
echo "<td>" . ($tmpDir ? $tmpDir : '系统默认') . "</td>";
echo "<td class='status-ok'>✅ -</td>";
echo "<td>确保目录有写入权限</td>";
echo "</tr>";

echo "</table>";

// 检查上传目录权限
echo "<div class='result-item info'>📂 检查上传目录权限：</div>";
$uploadDir = __DIR__ . '/../uploads/files/';
if (is_dir($uploadDir)) {
    if (is_writable($uploadDir)) {
        echo "<div class='result-item success'>✅ 上传目录存在且可写：<code>$uploadDir</code></div>";
    } else {
        echo "<div class='result-item error'>❌ 上传目录存在但不可写：<code>$uploadDir</code></div>";
        echo "<div class='result-item info'>请修改目录权限为755或777</div>";
    }
} else {
    echo "<div class='result-item warning'>⚠️ 上传目录不存在：<code>$uploadDir</code></div>";
    echo "<div class='result-item info'>脚本会自动创建该目录，如果创建失败请手动创建并设置权限</div>";
}

// 总结
echo "<div class='result-item info'>📋 总结和建议：</div>";

$hasProblem = false;
if ($uploadMaxBytes < $targetFileSize) {
    echo "<div class='result-item error'>❌ <code>upload_max_filesize</code> 太小（当前 $uploadMaxFilesize），无法上传71.61MB的文件！</div>";
    $hasProblem = true;
}
if ($postMaxBytes < $targetFileSize) {
    echo "<div class='result-item error'>❌ <code>post_max_size</code> 太小（当前 $postMaxSize），无法上传71.61MB的文件！</div>";
    $hasProblem = true;
}

if ($hasProblem) {
    echo "<div class='result-item warning'>";
    echo "<strong>🔧 解决方案：</strong><br><br>";
    echo "方法1：修改php.ini配置文件（推荐）<br>";
    echo "&nbsp;&nbsp;&nbsp;找到php.ini，修改以下配置：<br>";
    echo "&nbsp;&nbsp;&nbsp;<code>upload_max_filesize = 200M</code><br>";
    echo "&nbsp;&nbsp;&nbsp;<code>post_max_size = 200M</code><br>";
    echo "&nbsp;&nbsp;&nbsp;<code>max_execution_time = 300</code><br>";
    echo "&nbsp;&nbsp;&nbsp;修改后重启PHP或Web服务器<br><br>";
    echo "方法2：使用宝塔面板修改<br>";
    echo "&nbsp;&nbsp;&nbsp;宝塔面板 → 软件商店 → 已安装 → PHP → 设置 → 上传配置<br>";
    echo "&nbsp;&nbsp;&nbsp;修改上传大小限制为200MB<br><br>";
    echo "方法3：压缩文件<br>";
    echo "&nbsp;&nbsp;&nbsp;把文件压缩到当前限制以内（不推荐，因为软件更新包可能很大）";
    echo "</div>";
} else {
    echo "<div class='result-item success'>✅ PHP配置足够大，可以上传71.61MB的文件！</div>";
    echo "<div class='result-item info'>";
    echo "如果上传还是失败，可能是其他原因：<br>";
    echo "1. Nginx的 <code>client_max_body_size</code> 限制<br>";
    echo "2. Apache的 <code>LimitRequestBody</code> 限制<br>";
    echo "3. CDN或反向代理的限制<br>";
    echo "4. 后端代码的其他验证问题";
    echo "</div>";
}

echo "<div class='result-item info' style='margin-top: 20px;'>";
echo "<strong>💡 快速测试：</strong>你可以先上传一个小文件（比如1MB以内），看看是否能成功。如果小文件能上传，大文件不能上传，那就肯定是大小限制的问题。";
echo "</div>";

echo "
    </div>
</body>
</html>";

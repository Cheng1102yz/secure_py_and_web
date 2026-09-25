<?php
/**
 * ============================================================
 * SecureVault - 初始化公告表
 * ============================================================
 * 功能：创建announcements公告表
 * 使用方法：访问 backend/init_announcements.php?password=cyznb666
 */

require_once __DIR__ . '/helpers.php';

// 设置CORS头
setCorsHeaders();

// 密码验证
$password = getParam('password', '');
if ($password !== 'cyznb666') {
    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初始化公告表 - SecureVault</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #303133;
            margin: 0 0 8px;
            font-size: 24px;
        }
        .desc {
            color: #909399;
            margin: 0 0 24px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #606266;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #dcdfe6;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        button:hover {
            opacity: 0.9;
        }
        .error {
            background: #fef0f0;
            color: #f56c6c;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .tip {
            margin-top: 16px;
            padding: 12px;
            background: #f4f4f5;
            border-radius: 8px;
            font-size: 12px;
            color: #909399;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📢 初始化公告表</h1>
        <p class="desc">请输入初始化密码以创建公告表</p>
        <form method="GET">
            <div class="form-group">
                <label>初始化密码</label>
                <input type="password" name="password" placeholder="请输入初始化密码" required>
            </div>
            <button type="submit">创建公告表</button>
        </form>
        <div class="tip">
            💡 提示：初始化密码为 cyznb666<br>
            此操作将创建 announcements 表，用于存储系统公告。
        </div>
    </div>
</body>
</html>';
    exit;
}

// 连接数据库
$db = Database::getInstance();

// 创建公告表
$sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    type TEXT DEFAULT 'normal',
    status INTEGER DEFAULT 1,
    admin_id INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)";

try {
    $db->execute($sql);
    
    // 验证表是否创建成功
    $check = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='announcements'");
    
    if ($check) {
        // 获取表结构
        $columns = $db->fetchAll("PRAGMA table_info(announcements)");
        
        echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初始化公告表成功 - SecureVault</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #67c23a 0%, #85ce61 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 40px;
        }
        h1 {
            color: #303133;
            margin: 0 0 8px;
            font-size: 24px;
            text-align: center;
        }
        .desc {
            color: #909399;
            margin: 0 0 24px;
            font-size: 14px;
            text-align: center;
        }
        .table-info {
            background: #f5f7fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .table-title {
            font-size: 16px;
            font-weight: 600;
            color: #303133;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #ebeef5;
            font-size: 13px;
        }
        th {
            color: #909399;
            font-weight: 500;
            background: #fafafa;
        }
        td {
            color: #606266;
        }
        .btn-group {
            display: flex;
            gap: 12px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: opacity 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .btn-default {
            background: #f4f4f5;
            color: #606266;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>公告表创建成功！</h1>
        <p class="desc">announcements 表已成功创建，可以开始发布公告了</p>
        
        <div class="table-info">
            <div class="table-title">📋 表结构信息</div>
            <table>
                <thead>
                    <tr>
                        <th>字段名</th>
                        <th>类型</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>id</td><td>INTEGER</td><td>主键，自增</td></tr>
                    <tr><td>title</td><td>TEXT</td><td>公告标题</td></tr>
                    <tr><td>content</td><td>TEXT</td><td>公告内容（支持HTML）</td></tr>
                    <tr><td>type</td><td>TEXT</td><td>类型：normal/important/urgent</td></tr>
                    <tr><td>status</td><td>INTEGER</td><td>状态：0=草稿，1=已发布</td></tr>
                    <tr><td>admin_id</td><td>INTEGER</td><td>发布管理员ID</td></tr>
                    <tr><td>created_at</td><td>TEXT</td><td>创建时间</td></tr>
                    <tr><td>updated_at</td><td>TEXT</td><td>更新时间</td></tr>
                </tbody>
            </table>
        </div>
        
        <div class="btn-group">
            <a href="../frontend/admin.html" class="btn btn-primary">前往管理后台发布公告</a>
            <a href="../frontend/index.html" class="btn btn-default">返回社区首页</a>
        </div>
    </div>
</body>
</html>';
    } else {
        echo '<div style="text-align:center; padding:40px; color:#f56c6c;">
            <h2>❌ 表创建失败</h2>
            <p>请检查数据库权限和路径配置</p>
        </div>';
    }
} catch (Exception $e) {
    echo '<div style="text-align:center; padding:40px; color:#f56c6c;">
        <h2>❌ 创建失败</h2>
        <p>错误信息：' . htmlspecialchars($e->getMessage()) . '</p>
    </div>';
}

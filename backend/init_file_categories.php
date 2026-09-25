<?php
/**
 * ============================================================
 * SecureVault 文件分类初始化脚本
 * ============================================================
 * 安全说明：
 * 1. 此脚本需要密码验证才能执行
 * 2. 执行完成后请立即删除此文件
 * 3. 密码在下方 INIT_PASSWORD 常量中设置
 * ============================================================
 */

// ========== 安全配置 ==========
// 初始化密码（请修改为你自己的密码）
define('INIT_PASSWORD', 'cyznb666');

// ========== 安全检查 ==========
// 检查是否通过POST提交密码
$inputPassword = $_POST['password'] ?? $_GET['password'] ?? '';

if ($inputPassword !== INIT_PASSWORD) {
    // 显示密码输入表单
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>安全验证 - SecureVault</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); width: 320px; }
            h2 { color: #303133; margin-top: 0; text-align: center; }
            .form-group { margin-bottom: 16px; }
            label { display: block; margin-bottom: 6px; color: #606266; font-size: 14px; }
            input[type="password"] { width: 100%; padding: 10px; border: 1px solid #dcdfe6; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
            button { width: 100%; padding: 10px; background: #409eff; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
            button:hover { background: #66b1ff; }
            .warning { background: #fdf6ec; color: #e6a23c; padding: 10px; border-radius: 4px; margin-bottom: 16px; font-size: 12px; }
            .info { background: #ecf5ff; color: #409eff; padding: 10px; border-radius: 4px; margin-bottom: 16px; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔐 安全验证</h2>
            <div class="warning">
                ⚠️ 此脚本用于初始化文件分类表，执行后请立即删除！
            </div>
            <div class="info">
                💡 也可以通过命令行执行：<br>
                <code>php init_file_categories.php password=*********</code>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>请输入初始化密码</label>
                    <input type="password" name="password" placeholder="请输入密码" required>
                </div>
                <button type="submit">确认执行</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ========== 密码验证通过，执行初始化 ==========
$dbPath = __DIR__ . '/../database/securevault.db';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>文件分类初始化</title>";
echo "<style>body{font-family:Arial,sans-serif;background:#f5f7fa;padding:20px;}";
echo ".container{max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.1);}";
echo "h2{color:#303133;margin-top:0;}.success{color:#67c23a;}.error{color:#f56c6c;}";
echo ".warning{background:#fdf6ec;color:#e6a23c;padding:10px;border-radius:4px;margin:16px 0;}";
echo "pre{background:#f5f7fa;padding:15px;border-radius:4px;overflow-x:auto;}</style></head><body>";
echo "<div class='container'><h2>📦 文件分类初始化</h2>";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<pre>";
    
    // 检查file_categories表是否存在
    $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='file_categories'")->fetch();
    
    if (!$tableExists) {
        // 创建文件分类表
        $pdo->exec("
            CREATE TABLE file_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL,
                slug VARCHAR(50) NOT NULL UNIQUE,
                icon VARCHAR(20) DEFAULT '📦',
                sort_order INTEGER DEFAULT 0,
                status INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<span class='success'>✓ 创建 file_categories 表</span>\n";
        
        // 插入默认分类
        $defaultCategories = [
            ['软件', 'software', '💻', 1],
            ['工具', 'tool', '🔧', 2],
            ['文档', 'document', '📄', 3],
            ['其他', 'other', '📦', 4],
        ];
        
        foreach ($defaultCategories as $cat) {
            $pdo->exec("
                INSERT INTO file_categories (name, slug, icon, sort_order, status) 
                VALUES ('{$cat[0]}', '{$cat[1]}', '{$cat[2]}', {$cat[3]}, 1)
            ");
        }
        echo "<span class='success'>✓ 插入默认分类（软件、工具、文档、其他）</span>\n";
    } else {
        echo "<span class='success'>✓ file_categories 表已存在，跳过创建</span>\n";
    }
    
    // 检查files表是否有category_id字段
    $columns = $pdo->query('PRAGMA table_info(files)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('category_id', $columns)) {
        $pdo->exec('ALTER TABLE files ADD COLUMN category_id INTEGER DEFAULT 0');
        echo "<span class='success'>✓ 给 files 表添加 category_id 字段</span>\n";
    } else {
        echo "<span class='success'>✓ files 表已有 category_id 字段</span>\n";
    }
    
    echo "</pre>";
    echo "<div class='warning'>⚠️ 初始化完成！为了安全，请立即删除此文件：<code>backend/init_file_categories.php</code></div>";
    echo "<p><a href='../frontend/admin.html'>返回管理后台</a></p>";
    
} catch (Exception $e) {
    echo "<div class='error'>错误: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";

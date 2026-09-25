<?php
/**
 * ============================================================
 * SecureVault 分类和热门话题初始化脚本
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
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔐 安全验证</h2>
            <div class="warning">
                ⚠️ 此脚本用于初始化数据库，执行后请立即删除！
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

// ========== 开始初始化 ==========
$dbPath = __DIR__ . '/../database/securevault.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查是否已经初始化过
    $check = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($check > 0) {
        echo "<h2>⚠️ 数据库已经初始化过了！</h2>";
        echo "<p>categories表已有 {$check} 条数据。</p>";
        echo "<p>如果需要重新初始化，请先手动清空数据库表。</p>";
        echo "<p style='color:red;'><strong>执行完成后请立即删除此文件！</strong></p>";
        exit;
    }
    
    // 创建分类表
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        icon TEXT DEFAULT '',
        slug TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 0,
        status INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 创建热门话题表
    $pdo->exec("CREATE TABLE IF NOT EXISTS hot_topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        status INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "<h2>✅ 表创建成功</h2>";
    
    // 插入默认分类
    $categories = [
        ['name' => '全部', 'icon' => '🔥', 'slug' => '', 'sort_order' => 1],
        ['name' => '技术交流', 'icon' => '💻', 'slug' => 'tech', 'sort_order' => 2],
        ['name' => '资源分享', 'icon' => '📦', 'slug' => 'share', 'sort_order' => 3],
        ['name' => '问答求助', 'icon' => '❓', 'slug' => 'qa', 'sort_order' => 4],
        ['name' => '生活日常', 'icon' => '🌈', 'slug' => 'life', 'sort_order' => 5],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO categories (name, icon, slug, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $stmt->execute([$cat['name'], $cat['icon'], $cat['slug'], $cat['sort_order']]);
    }
    echo "<p>✅ 已插入 " . count($categories) . " 个默认分类</p>";
    
    // 插入默认热门话题
    $topics = [
        ['title' => '#加密技术交流#', 'sort_order' => 1],
        ['title' => '#SecureVault使用技巧#', 'sort_order' => 2],
        ['title' => '#编程学习分享#', 'sort_order' => 3],
        ['title' => '#资源分享#', 'sort_order' => 4],
        ['title' => '#问答求助#', 'sort_order' => 5],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO hot_topics (title, sort_order) VALUES (?, ?)");
    foreach ($topics as $topic) {
        $stmt->execute([$topic['title'], $topic['sort_order']]);
    }
    echo "<p>✅ 已插入 " . count($topics) . " 个默认热门话题</p>";
    
    echo "<h3>🎉 初始化完成！</h3>";
    echo "<p style='color:red; font-size:18px;'><strong>⚠️ 请立即删除此文件 init_categories.php，否则会有安全风险！</strong></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ 错误</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

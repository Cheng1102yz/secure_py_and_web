<?php
/**
 * ============================================================
 * SecureVault 用户系统 - 配置文件
 * ============================================================
 * 包含数据库配置、安全配置、API配置等
 * 修改此文件后请确保权限正确，不要将此文件提交到公开仓库
 * ============================================================
 */

// 安全保护：禁止直接访问此文件（只能被其他PHP文件include）
if (basename($_SERVER['PHP_SELF'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('禁止直接访问此文件');
}

// ========== 时区设置 ==========
// 强制使用中国标准时间（Asia/Shanghai，UTC+8）
// 这样不管服务器时区是什么，代码中的时间都会正确显示为中国时间
date_default_timezone_set('Asia/Shanghai');

// ========== 数据库配置 ==========
// 数据库类型：sqlite 或 mysql
// 本地测试建议用 sqlite（无需额外安装）
// 生产环境建议用 mysql
define('DB_TYPE', 'sqlite');

// SQLite 数据库文件路径（当 DB_TYPE=sqlite 时生效）
define('SQLITE_PATH', __DIR__ . '/../database/securevault.db');

// MySQL 配置（当 DB_TYPE=mysql 时生效）
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', 3306);
define('MYSQL_DBNAME', 'securevault');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', '');
define('MYSQL_CHARSET', 'utf8mb4');

// ========== 安全配置 ==========
// JWT/会话密钥（用于加密令牌，请修改为随机字符串）
define('SECRET_KEY', 'SecureVault_Secret_Key_2026_Change_Me_Please!@#$%');

// 密码哈希算法：bcrypt 或 argon2
define('PASSWORD_ALGO', 'bcrypt');

// bcrypt 成本因子（4-31，越高越安全但越慢）
define('BCRYPT_COST', 10);

// 会话有效期（秒）
// 默认7天：7 * 24 * 60 * 60 = 604800
define('SESSION_EXPIRE', 604800);

// 登录失败锁定配置
define('MAX_LOGIN_ATTEMPTS', 5);      // 最大失败次数
define('LOGIN_LOCK_TIME', 300);        // 锁定时间（秒），5分钟

// ========== API配置 ==========
// API 允许的来源（CORS），* 表示允许所有
define('API_ALLOW_ORIGIN', '*');

// API 响应格式
define('API_CONTENT_TYPE', 'application/json; charset=utf-8');

// ========== 软件配置 ==========
// 软件名称和版本（用于API返回和管理后台显示）
define('SOFTWARE_NAME', 'SecureVault 专业加密工具');
define('SOFTWARE_VERSION', '2.0.0');

// ========== 管理员配置 ==========
// 默认管理员用户名（用于初始化检查）
define('DEFAULT_ADMIN_USERNAME', 'admin');
define('DEFAULT_ADMIN_PASSWORD', 'Admin@123456');

// ========== 调试配置 ==========
// 是否显示错误详情（生产环境请设为 false）
define('DEBUG_MODE', true);

// 错误日志文件路径
define('ERROR_LOG_PATH', __DIR__ . '/../logs/error.log');

// ============================================================
// 自动创建必要的目录
// ============================================================
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$dbDir = __DIR__ . '/../database';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

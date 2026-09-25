<?php
/**
 * ============================================================
 * SecureVault 用户系统 - API 通用工具函数
 * ============================================================
 * 包含：统一响应格式、CORS处理、输入验证、令牌生成等
 * 所有API接口都应使用本文件提供的函数
 * ============================================================
 */

// 安全保护：禁止直接访问此文件
if (basename($_SERVER['PHP_SELF'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('禁止直接访问此文件');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ========== 时区设置 ==========
// 强制使用中国标准时间（Asia/Shanghai，UTC+8）
// 这样不管服务器时区是什么，代码中的时间都会正确显示为中国时间
date_default_timezone_set('Asia/Shanghai');

// ========== CORS 跨域处理 ==========
/**
 * 设置CORS跨域头
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: ' . API_ALLOW_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    
    // 处理OPTIONS预检请求
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ========== 统一响应格式 ==========
/**
 * 成功响应
 * @param mixed $data 响应数据
 * @param string $message 提示信息
 * @param int $code 状态码
 */
function jsonSuccess($data = null, $message = '操作成功', $code = 200) {
    header('Content-Type: ' . API_CONTENT_TYPE);
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 错误响应
 * @param string $message 错误信息
 * @param int $code 错误码
 * @param mixed $data 附加数据
 */
function jsonError($message = '操作失败', $code = 400, $data = null) {
    header('Content-Type: ' . API_CONTENT_TYPE);
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 输入获取与验证 ==========
/**
 * 获取JSON请求体数据
 * 只在请求内容类型是application/json时才读取请求体，避免大文件上传时内存耗尽
 * @return array
 */
function getJsonInput() {
    // 检查请求内容类型，只处理JSON请求
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($contentType, 'application/json') === false) {
        return [];
    }
    
    $input = file_get_contents('php://input');
    if (!$input) {
        return [];
    }
    
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * 获取请求参数（支持GET、POST、JSON）
 * @param string $key 参数名
 * @param mixed $default 默认值
 * @return mixed
 */
function getParam($key, $default = null) {
    // 先检查JSON body
    $jsonData = getJsonInput();
    if (isset($jsonData[$key])) {
        return $jsonData[$key];
    }
    // 再检查POST
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    // 最后检查GET
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

/**
 * 验证用户名格式
 * @param string $username 用户名
 * @return bool|string 验证通过返回true，失败返回错误信息
 */
function validateUsername($username) {
    if (empty($username)) {
        return '用户名不能为空';
    }
    if (strlen($username) < 2 || strlen($username) > 20) {
        return '用户名长度应为2-20个字符';
    }
    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
        return '用户名只能包含字母、数字、下划线和中文';
    }
    return true;
}

/**
 * 验证密码强度
 * @param string $password 密码
 * @return bool|string 验证通过返回true，失败返回错误信息
 */
function validatePassword($password) {
    if (empty($password)) {
        return '密码不能为空';
    }
    if (strlen($password) < 6) {
        return '密码长度至少6位';
    }
    if (strlen($password) > 50) {
        return '密码长度不能超过50位';
    }
    return true;
}

/**
 * 验证邮箱格式
 * @param string $email 邮箱
 * @return bool
 */
function validateEmail($email) {
    if (empty($email)) {
        return true; // 邮箱可选
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ========== 安全函数 ==========
/**
 * 生成随机令牌
 * @param int $length 令牌长度
 * @return string
 */
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * 哈希密码
 * @param string $password 明文密码
 * @return string 哈希后的密码
 */
function hashPassword($password) {
    if (PASSWORD_ALGO === 'argon2' && defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    $options = ['cost' => BCRYPT_COST];
    return password_hash($password, PASSWORD_BCRYPT, $options);
}

/**
 * 验证密码
 * @param string $password 明文密码
 * @param string $hash 哈希值
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 获取客户端IP地址
 * @return string
 */
function getClientIp() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // 处理多个IP的情况（取第一个）
    if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
    }
    return $ip ?: '0.0.0.0';
}

/**
 * 获取客户端设备信息（User-Agent）
 * @return string
 */
function getDeviceInfo() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    // 截断过长的UA
    if (strlen($ua) > 200) {
        $ua = substr($ua, 0, 200);
    }
    return $ua;
}

// ========== 认证相关 ==========
/**
 * 从请求中获取认证令牌
 * @return string|null
 */
function getAuthToken() {
    // 从Authorization头获取
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (strpos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }
        return $auth;
    }
    // 从POST/GET参数获取
    $token = getParam('token', null);
    if ($token) {
        return $token;
    }
    return null;
}

/**
 * 验证令牌并返回用户信息
 * @return array|null 验证成功返回用户信息，失败返回null
 */
function authenticate() {
    $token = getAuthToken();
    if (!$token) {
        return null;
    }
    
    $db = Database::getInstance();
    
    // 查询有效的会话
    $session = $db->fetchOne(
        "SELECT s.*, u.username, u.nickname, u.email, u.status, u.is_admin 
         FROM sessions s 
         JOIN users u ON s.user_id = u.id 
         WHERE s.token = ? AND s.is_active = 1",
        [$token]
    );
    
    if (!$session) {
        return null;
    }
    
    // 检查是否过期
    if (strtotime($session['expires_at']) < time()) {
        // 标记会话为失效
        $db->execute("UPDATE sessions SET is_active = 0 WHERE id = ?", [$session['id']]);
        return null;
    }
    
    // 检查用户状态
    if ($session['status'] != 1) {
        return null;
    }
    
    return $session;
}

/**
 * 验证管理员权限（支持副管理员和主管理员）
 * is_admin: 0=普通用户, 1=副管理员, 2=主管理员
 * 副管理员和主管理员都可以访问后台，但权限不同
 * @return array|null 验证成功返回用户信息，失败返回null
 */
function authenticateAdmin() {
    $user = authenticate();
    if (!$user || (int)$user['is_admin'] < 1) {
        return null;
    }
    return $user;
}

/**
 * 判断是否是主管理员
 * @param array $user 用户信息
 * @return bool 是否是主管理员
 */
function isSuperAdmin($user) {
    return $user && (int)$user['is_admin'] === 2;
}

/**
 * 判断是否是副管理员
 * @param array $user 用户信息
 * @return bool 是否是副管理员
 */
function isModerator($user) {
    return $user && (int)$user['is_admin'] === 1;
}

/**
 * 获取用户身份名称
 * @param int $isAdmin 身份值
 * @return string 身份名称
 */
function getRoleName($isAdmin) {
    return match((int)$isAdmin) {
        0 => '普通用户',
        1 => '副管理员',
        2 => '主管理员',
        default => '未知'
    };
}

/**
 * 记录登录日志
 * @param int|null $userId 用户ID
 * @param string $username 用户名
 * @param int $status 状态（0=失败，1=成功）
 * @param string|null $failReason 失败原因
 */
function logLogin($userId, $username, $status, $failReason = null) {
    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO login_logs (user_id, username, ip_address, device_info, status, fail_reason) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$userId, $username, getClientIp(), getDeviceInfo(), $status, $failReason]
    );
}

/**
 * 记录管理员操作日志
 * @param int $adminId 管理员ID
 * @param string $action 操作类型
 * @param string|null $target 操作对象
 * @param string|null $detail 操作详情
 */
function logAdminAction($adminId, $action, $target = null, $detail = null) {
    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO admin_logs (admin_id, action, target, detail, ip_address) 
         VALUES (?, ?, ?, ?, ?)",
        [$adminId, $action, $target, $detail, getClientIp()]
    );
}

/**
 * 发送系统通知
 * @param int $userId 接收通知的用户ID
 * @param string $type 通知类型（post_pending/post_approved/post_rejected/post_liked/post_comment/new_follower/friend_request/friend_accepted/system）
 * @param string $title 通知标题
 * @param string $content 通知内容
 * @param int|null $relatedId 关联ID（帖子ID/用户ID等）
 * @return bool 是否发送成功
 */
function sendNotification($userId, $type, $title, $content, $relatedId = null) {
    try {
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO notifications (user_id, type, title, content, related_id, is_read, created_at) 
             VALUES (?, ?, ?, ?, ?, 0, ?)",
            [$userId, $type, $title, $content, $relatedId, date('Y-m-d H:i:s')]
        );
        return true;
    } catch (Exception $e) {
        // 记录错误但不中断主流程
        error_log("sendNotification error: " . $e->getMessage());
        return false;
    }
}

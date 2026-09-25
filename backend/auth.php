<?php
/**
 * ============================================================
 * SecureVault 用户系统 - 认证 API
 * ============================================================
 * 接口列表：
 *   POST /auth.php?action=register    用户注册
 *   POST /auth.php?action=login       用户登录
 *   POST /auth.php?action=logout      用户登出
 *   GET  /auth.php?action=status      检查登录状态
 *   POST /auth.php?action=change_pwd  修改密码
 * 
 * 所有接口返回统一JSON格式：
 *   { success, code, message, data, timestamp }
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 设置CORS头
setCorsHeaders();

// 获取操作类型
$action = getParam('action', '');

// 根据操作类型分发
switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'status':
        handleStatus();
        break;
    case 'change_pwd':
    case 'change_password':
        handleChangePassword();
        break;
    case 'forgot_password':
        handleForgotPassword();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 用户注册
// ============================================================
function handleRegister() {
    $db = Database::getInstance();
    
    // 获取参数
    $username = trim(getParam('username', ''));
    $password = getParam('password', '');
    $email = trim(getParam('email', ''));
    $nickname = trim(getParam('nickname', ''));
    
    // 【安全防护】检查滑块验证（前后端交叉验证）
    // 允许通过 ?skip_captcha=1 跳过（用于软件端注册）
    $skipCaptcha = getParam('skip_captcha', 0);
    if (!$skipCaptcha) {
        if (empty($_COOKIE['slider_verified'])) {
            jsonError('请先完成滑块验证', 400);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['slider_verified']) || 
            $_SESSION['slider_verified'] !== $_COOKIE['slider_verified'] ||
            (time() - $_SESSION['slider_verified_at']) > 300) {
            session_write_close();
            jsonError('滑块验证已失效，请重新验证', 400);
        }
        session_write_close();
        
        // 验证通过后清除（一次性使用）
        setcookie('slider_verified', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['slider_verified']);
        unset($_SESSION['slider_verified_at']);
        session_write_close();
    }
    
    // 验证用户名
    $usernameCheck = validateUsername($username);
    if ($usernameCheck !== true) {
        jsonError($usernameCheck, 400);
    }
    
    // 验证密码
    $passwordCheck = validatePassword($password);
    if ($passwordCheck !== true) {
        jsonError($passwordCheck, 400);
    }
    
    // 验证邮箱
    if (!validateEmail($email)) {
        jsonError('邮箱格式不正确', 400);
    }
    
    // 检查用户名是否已存在
    $existingUser = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existingUser) {
        jsonError('用户名已存在，请换一个', 409);
    }
    
    // 哈希密码
    $passwordHash = hashPassword($password);
    
    // 插入用户
    $userId = $db->insert(
        "INSERT INTO users (username, password_hash, email, nickname, status, is_admin) 
         VALUES (?, ?, ?, ?, 1, 0)",
        [$username, $passwordHash, $email ?: null, $nickname ?: null]
    );
    
    if (!$userId) {
        jsonError('注册失败，请稍后重试', 500);
    }
    
    // 自动登录：创建会话
    $token = generateToken(64);
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_EXPIRE);
    $db->insert(
        "INSERT INTO sessions (user_id, token, device_info, ip_address, expires_at, is_active) 
         VALUES (?, ?, ?, ?, ?, 1)",
        [$userId, $token, getDeviceInfo(), getClientIp(), $expiresAt]
    );
    
    // 更新最后登录时间（用PHP生成时间，兼容MySQL和SQLite）
    $now = date('Y-m-d H:i:s');
    $db->execute(
        "UPDATE users SET last_login = ?, last_login_ip = ? WHERE id = ?",
        [$now, getClientIp(), $userId]
    );
    
    // 记录登录日志
    logLogin($userId, $username, 1);
    
    // 返回成功
    jsonSuccess([
        'user_id' => (int)$userId,
        'username' => $username,
        'nickname' => $nickname ?: $username,
        'token' => $token,
        'expires_at' => $expiresAt
    ], '注册成功，已自动登录', 201);
}

// ============================================================
// 用户登录
// ============================================================
function handleLogin() {
    $db = Database::getInstance();
    
    // 获取参数
    $username = trim(getParam('username', ''));
    $password = getParam('password', '');
    $ip = getClientIp();
    
    // 参数验证
    if (empty($username)) {
        jsonError('用户名不能为空', 400);
    }
    if (empty($password)) {
        jsonError('密码不能为空', 400);
    }
    
    // 【安全防护】检查滑块验证（前后端交叉验证）
    // 允许通过 ?skip_captcha=1 跳过（用于软件端登录）
    $skipCaptcha = getParam('skip_captcha', 0);
    if (!$skipCaptcha) {
        // 检查cookie
        if (empty($_COOKIE['slider_verified'])) {
            jsonError('请先完成滑块验证', 400);
        }
        
        // 检查session（双重验证）
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['slider_verified']) || 
            $_SESSION['slider_verified'] !== $_COOKIE['slider_verified'] ||
            (time() - $_SESSION['slider_verified_at']) > 300) {
            session_write_close();
            jsonError('滑块验证已失效，请重新验证', 400);
        }
        session_write_close();
        
        // 验证通过后清除（一次性使用，防止重放攻击）
        setcookie('slider_verified', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['slider_verified']);
        unset($_SESSION['slider_verified_at']);
        session_write_close();
    }
    
    // 【安全防护】检查登录失败次数（5次失败锁定1分钟）
    $maxAttempts = 5;
    $lockoutTime = 1 * 60; // 1分钟
    
    // 确保login_attempts表存在
    $db->execute("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL,
        username TEXT,
        attempts INTEGER DEFAULT 0,
        last_attempt DATETIME,
        locked_until DATETIME,
        UNIQUE(ip_address, username)
    )");
    
    // 检查当前IP+用户名的失败记录
    $attempt = $db->fetchOne(
        "SELECT * FROM login_attempts WHERE ip_address = ? AND username = ?",
        [$ip, $username]
    );
    
    // 如果被锁定，检查是否过期
    if ($attempt && $attempt['locked_until']) {
        $lockUntil = strtotime($attempt['locked_until']);
        if ($lockUntil > time()) {
            $remaining = ceil(($lockUntil - time()) / 60);
            jsonError("登录失败次数过多，请{$remaining}分钟后再试", 429);
        }
        // 锁定已过期，重置记录
        $db->execute(
            "DELETE FROM login_attempts WHERE ip_address = ? AND username = ?",
            [$ip, $username]
        );
        $attempt = null;
    }
    
    // 查询用户
    $user = $db->fetchOne("SELECT * FROM users WHERE username = ?", [$username]);
    
    if (!$user) {
        logLogin(null, $username, 0, '用户不存在');
        // 记录失败尝试
        recordLoginAttempt($db, $ip, $username, $maxAttempts, $lockoutTime);
        jsonError('用户名或密码错误', 401);
    }
    
    // 检查账号状态
    if ($user['status'] == 0) {
        logLogin($user['id'], $username, 0, '账号已被禁用');
        jsonError('账号已被禁用，请联系管理员', 403);
    }
    
    // 验证密码
    if (!verifyPassword($password, $user['password_hash'])) {
        logLogin($user['id'], $username, 0, '密码错误');
        // 记录失败尝试
        recordLoginAttempt($db, $ip, $username, $maxAttempts, $lockoutTime);
        jsonError('用户名或密码错误', 401);
    }
    
    // 密码验证成功，清除失败记录
    $db->execute(
        "DELETE FROM login_attempts WHERE ip_address = ? AND username = ?",
        [$ip, $username]
    );
    
    // 密码验证成功，创建新会话
    $token = generateToken(64);
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_EXPIRE);
    $db->insert(
        "INSERT INTO sessions (user_id, token, device_info, ip_address, expires_at, is_active) 
         VALUES (?, ?, ?, ?, ?, 1)",
        [$user['id'], $token, getDeviceInfo(), getClientIp(), $expiresAt]
    );
    
    // 更新最后登录时间（用PHP生成时间，兼容MySQL和SQLite）
    $now = date('Y-m-d H:i:s');
    $db->execute(
        "UPDATE users SET last_login = ?, last_login_ip = ? WHERE id = ?",
        [$now, getClientIp(), $user['id']]
    );
    
    // 记录登录日志
    logLogin($user['id'], $username, 1);
    
    // 返回成功
    jsonSuccess([
        'user_id' => (int)$user['id'],
        'username' => $user['username'],
        'nickname' => $user['nickname'] ?: $user['username'],
        'email' => $user['email'],
        'avatar' => $user['avatar'] ?: '',
        'unique_id' => $user['unique_id'] ?: '',
        'is_admin' => (int)$user['is_admin'],
        'token' => $token,
        'expires_at' => $expiresAt
    ], '登录成功');
}

// ============================================================
// 记录登录失败尝试
// ============================================================
function recordLoginAttempt($db, $ip, $username, $maxAttempts, $lockoutTime) {
    $now = date('Y-m-d H:i:s');
    
    // 检查是否已有记录
    $attempt = $db->fetchOne(
        "SELECT * FROM login_attempts WHERE ip_address = ? AND username = ?",
        [$ip, $username]
    );
    
    if ($attempt) {
        $newAttempts = (int)$attempt['attempts'] + 1;
        
        // 如果达到最大尝试次数，锁定账户
        if ($newAttempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutTime);
            $db->execute(
                "UPDATE login_attempts SET attempts = ?, last_attempt = ?, locked_until = ? WHERE id = ?",
                [$newAttempts, $now, $lockedUntil, $attempt['id']]
            );
        } else {
            $db->execute(
                "UPDATE login_attempts SET attempts = ?, last_attempt = ? WHERE id = ?",
                [$newAttempts, $now, $attempt['id']]
            );
        }
    } else {
        // 新建记录
        $db->execute(
            "INSERT INTO login_attempts (ip_address, username, attempts, last_attempt) VALUES (?, ?, 1, ?)",
            [$ip, $username, $now]
        );
    }
}

// ============================================================
// 用户登出
// ============================================================
function handleLogout() {
    $db = Database::getInstance();
    $token = getAuthToken();
    
    if (!$token) {
        jsonError('未提供登录令牌', 401);
    }
    
    // 使会话失效
    $result = $db->execute(
        "UPDATE sessions SET is_active = 0 WHERE token = ? AND is_active = 1",
        [$token]
    );
    
    if ($result > 0) {
        jsonSuccess(null, '登出成功');
    } else {
        jsonError('会话不存在或已失效', 400);
    }
}

// ============================================================
// 检查登录状态
// ============================================================
function handleStatus() {
    $user = authenticate();
    
    if (!$user) {
        jsonSuccess([
            'logged_in' => false,
            'user' => null
        ], '未登录');
    }
    
    // 返回用户信息（不包含敏感字段）
    jsonSuccess([
        'logged_in' => true,
        'user' => [
            'user_id' => (int)$user['user_id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'] ?: $user['username'],
            'email' => $user['email'],
            'is_admin' => (int)$user['is_admin'],
            'login_time' => $user['created_at'],
            'expires_at' => $user['expires_at'],
            'ip_address' => $user['ip_address']
        ]
    ], '已登录');
}

// ============================================================
// 修改密码
// ============================================================
function handleChangePassword() {
    $db = Database::getInstance();
    $user = authenticate();
    
    if (!$user) {
        jsonError('请先登录', 401);
    }
    
    // 获取参数
    $oldPassword = getParam('old_password', '');
    $newPassword = getParam('new_password', '');
    
    if (empty($oldPassword) || empty($newPassword)) {
        jsonError('旧密码和新密码都不能为空', 400);
    }
    
    // 验证新密码
    $passwordCheck = validatePassword($newPassword);
    if ($passwordCheck !== true) {
        jsonError($passwordCheck, 400);
    }
    
    // 查询用户当前密码
    $currentUser = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$user['user_id']]);
    
    // 验证旧密码
    if (!verifyPassword($oldPassword, $currentUser['password_hash'])) {
        jsonError('旧密码错误', 401);
    }
    
    // 更新密码
    $newHash = hashPassword($newPassword);
    $result = $db->execute(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [$newHash, $user['user_id']]
    );
    
    if ($result > 0) {
        // 使所有现有会话失效（安全考虑）
        $db->execute("UPDATE sessions SET is_active = 0 WHERE user_id = ?", [$user['user_id']]);
        
        // 记录管理员操作
        logAdminAction($user['user_id'], 'change_password', 'user:' . $user['user_id'], '用户修改密码');
        
        jsonSuccess(null, '密码修改成功，请重新登录');
    } else {
        jsonError('密码修改失败', 500);
    }
}

// ============================================================
// 忘记密码 - 发送重置验证码
// ============================================================
function handleForgotPassword() {
    $db = Database::getInstance();
    
    // 获取参数
    $username = trim(getParam('username', ''));
    $email = trim(getParam('email', ''));
    
    // 验证参数
    if (!$username) {
        jsonError('请输入用户名', 400);
    }
    if (!$email) {
        jsonError('请输入注册邮箱', 400);
    }
    
    // 查询用户
    $user = $db->fetchOne("SELECT id, username, email, nickname FROM users WHERE username = ?", [$username]);
    if (!$user) {
        jsonError('用户不存在', 404);
    }
    
    // 验证邮箱
    if ($user['email'] !== $email) {
        jsonError('邮箱与注册邮箱不匹配', 400);
    }
    
    // 生成6位数字重置验证码（基于用户ID和时间的HMAC签名，有效期10分钟）
    $timestamp = time();
    $secret = 'securevault_reset_secret_2024';
    $rawToken = $user['id'] . '|' . $timestamp;
    $hash = hash_hmac('sha256', $rawToken, $secret);
    // 取hash的前6位数字作为验证码
    $code = '';
    for ($i = 0; $i < strlen($hash) && strlen($code) < 6; $i++) {
        if (ctype_digit($hash[$i])) {
            $code .= $hash[$i];
        }
    }
    // 如果不足6位，用时间戳补充
    while (strlen($code) < 6) {
        $code .= substr((string)$timestamp, -1);
        $timestamp = floor($timestamp / 10);
    }
    
    // 记录重置请求到日志（安全审计）
    logAdminAction($user['id'], 'forgot_password', 'user:' . $user['id'], '用户请求重置密码');
    
    // 返回验证码（由于本地部署没有邮件服务器，直接返回验证码）
    // 生产环境应该通过邮件发送验证码，而不是直接返回
    jsonSuccess([
        'reset_code' => $code,
        'timestamp' => $timestamp,
        'user_id' => $user['id'],
        'expires_in' => 600 // 10分钟
    ], '重置验证码已生成，请在10分钟内使用');
}

// ============================================================
// 重置密码
// ============================================================
function handleResetPassword() {
    $db = Database::getInstance();
    
    // 获取参数
    $username = trim(getParam('username', ''));
    $email = trim(getParam('email', ''));
    $code = trim(getParam('code', ''));
    $newPassword = getParam('new_password', '');
    $confirmPassword = getParam('confirm_password', '');
    
    // 验证参数
    if (!$username) {
        jsonError('请输入用户名', 400);
    }
    if (!$email) {
        jsonError('请输入注册邮箱', 400);
    }
    if (!$code) {
        jsonError('请输入重置验证码', 400);
    }
    if (!$newPassword) {
        jsonError('请输入新密码', 400);
    }
    if ($newPassword !== $confirmPassword) {
        jsonError('两次输入的密码不一致', 400);
    }
    
    // 验证密码强度
    $passwordCheck = validatePassword($newPassword);
    if ($passwordCheck !== true) {
        jsonError($passwordCheck, 400);
    }
    
    // 查询用户
    $user = $db->fetchOne("SELECT id, username, email FROM users WHERE username = ?", [$username]);
    if (!$user) {
        jsonError('用户不存在', 404);
    }
    
    // 验证邮箱
    if ($user['email'] !== $email) {
        jsonError('邮箱与注册邮箱不匹配', 400);
    }
    
    // 验证验证码（重新生成并比较）
    $secret = 'securevault_reset_secret_2024';
    $valid = false;
    // 检查最近10分钟内的验证码
    $now = time();
    for ($t = $now; $t >= $now - 600; $t--) {
        $rawToken = $user['id'] . '|' . $t;
        $hash = hash_hmac('sha256', $rawToken, $secret);
        $expectedCode = '';
        for ($i = 0; $i < strlen($hash) && strlen($expectedCode) < 6; $i++) {
            if (ctype_digit($hash[$i])) {
                $expectedCode .= $hash[$i];
            }
        }
        while (strlen($expectedCode) < 6) {
            $expectedCode .= substr((string)$t, -1);
            $t = floor($t / 10);
        }
        if ($expectedCode === $code) {
            $valid = true;
            break;
        }
    }
    
    if (!$valid) {
        jsonError('验证码错误或已过期', 400);
    }
    
    // 更新密码
    $newHash = hashPassword($newPassword);
    $result = $db->execute(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [$newHash, $user['id']]
    );
    
    if ($result > 0) {
        // 使所有现有会话失效（安全考虑）
        $db->execute("UPDATE sessions SET is_active = 0 WHERE user_id = ?", [$user['id']]);
        
        // 记录管理员操作
        logAdminAction($user['id'], 'reset_password', 'user:' . $user['id'], '用户通过忘记密码重置密码');
        
        jsonSuccess(null, '密码重置成功，请使用新密码登录');
    } else {
        jsonError('密码重置失败', 500);
    }
}

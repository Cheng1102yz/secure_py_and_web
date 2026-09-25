<?php
/**
 * ============================================================
 * SecureVault 用户系统 - 用户管理 API
 * ============================================================
 * 接口列表（需要登录）：
 *   GET  /user.php?action=profile       获取个人资料
 *   POST /user.php?action=update        更新个人资料
 *   GET  /user.php?action=login_history 登录历史
 *   GET  /user.php?action=active_sessions 活跃会话
 *   POST /user.php?action=revoke_session 撤销指定会话
 * 
 * 所有接口需要在请求头中携带 Authorization: Bearer {token}
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 设置CORS头
setCorsHeaders();

// 验证登录
$user = authenticate();
if (!$user) {
    jsonError('请先登录', 401);
}

// 获取操作类型
$action = getParam('action', '');

// 根据操作类型分发
switch ($action) {
    case 'profile':
        handleGetProfile($user);
        break;
    case 'update':
    case 'update_profile':
        handleUpdateProfile($user);
        break;
    case 'login_history':
        handleLoginHistory($user);
        break;
    case 'active_sessions':
        handleActiveSessions($user);
        break;
    case 'revoke_session':
        handleRevokeSession($user);
        break;
    case 'upload_avatar':
        handleUploadAvatar($user);
        break;
    case 'user_info':
        handleGetUserInfo();
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 获取个人资料
// ============================================================
function handleGetProfile($user) {
    $db = Database::getInstance();
    
    $profile = $db->fetchOne(
        "SELECT id, username, email, nickname, status, is_admin, created_at, last_login, last_login_ip, avatar 
         FROM users WHERE id = ?",
        [$user['user_id']]
    );
    
    if (!$profile) {
        jsonError('用户不存在', 404);
    }
    
    jsonSuccess([
        'user_id' => (int)$profile['id'],
        'id' => (int)$profile['id'],
        'username' => $profile['username'],
        'nickname' => $profile['nickname'] ?: $profile['username'],
        'email' => $profile['email'],
        'avatar' => $profile['avatar'],
        'is_admin' => (int)$profile['is_admin'],
        'status' => (int)$profile['status'],
        'created_at' => $profile['created_at'],
        'last_login' => $profile['last_login'],
        'last_login_ip' => $profile['last_login_ip']
    ], '获取成功');
}

// ============================================================
// 更新个人资料
// ============================================================
function handleUpdateProfile($user) {
    $db = Database::getInstance();
    
    $nickname = trim(getParam('nickname', ''));
    $email = trim(getParam('email', ''));
    
    // 验证邮箱
    if (!validateEmail($email)) {
        jsonError('邮箱格式不正确', 400);
    }
    
    // 验证昵称
    if (!empty($nickname) && (strlen($nickname) < 2 || strlen($nickname) > 20)) {
        jsonError('昵称长度应为2-20个字符', 400);
    }
    
    // 更新资料
    $result = $db->execute(
        "UPDATE users SET nickname = ?, email = ? WHERE id = ?",
        [$nickname ?: null, $email ?: null, $user['user_id']]
    );
    
    if ($result >= 0) {
        // 记录操作
        logAdminAction($user['user_id'], 'update_profile', 'user:' . $user['user_id'], '用户更新个人资料');
        jsonSuccess(null, '资料更新成功');
    } else {
        jsonError('更新失败', 500);
    }
}

// ============================================================
// 登录历史
// ============================================================
function handleLoginHistory($user) {
    $db = Database::getInstance();
    
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    
    // 查询总数
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM login_logs WHERE user_id = ?",
        [$user['user_id']]
    );
    
    // 查询列表
    $logs = $db->fetchAll(
        "SELECT id, login_time, ip_address, device_info, status, fail_reason 
         FROM login_logs 
         WHERE user_id = ? 
         ORDER BY login_time DESC 
         LIMIT ? OFFSET ?",
        [$user['user_id'], $pageSize, $offset]
    );
    
    jsonSuccess([
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize,
        'list' => $logs
    ], '获取成功');
}

// ============================================================
// 活跃会话列表
// ============================================================
function handleActiveSessions($user) {
    $db = Database::getInstance();
    $currentToken = getAuthToken();
    
    $sessions = $db->fetchAll(
        "SELECT id, token, device_info, ip_address, created_at, expires_at, is_active 
         FROM sessions 
         WHERE user_id = ? AND is_active = 1 
         ORDER BY created_at DESC",
        [$user['user_id']]
    );
    
    // 处理每个会话，标记是否为当前设备
    foreach ($sessions as &$session) {
        // 标记是否为当前会话
        $session['is_current'] = ($currentToken && $session['token'] === $currentToken) ? 1 : 0;
        // 不返回完整令牌
        unset($session['token']);
    }
    
    jsonSuccess($sessions, '获取成功');
}

// ============================================================
// 撤销指定会话
// ============================================================
function handleRevokeSession($user) {
    $db = Database::getInstance();
    
    $sessionId = (int)getParam('session_id', 0);
    
    if ($sessionId <= 0) {
        jsonError('无效的会话ID', 400);
    }
    
    // 检查会话是否属于当前用户
    $session = $db->fetchOne(
        "SELECT id FROM sessions WHERE id = ? AND user_id = ?",
        [$sessionId, $user['user_id']]
    );
    
    if (!$session) {
        jsonError('会话不存在或无权操作', 404);
    }
    
    // 撤销会话
    $result = $db->execute(
        "UPDATE sessions SET is_active = 0 WHERE id = ?",
        [$sessionId]
    );
    
    if ($result > 0) {
        logAdminAction($user['user_id'], 'revoke_session', 'session:' . $sessionId, '用户撤销了一个登录会话');
        jsonSuccess(null, '会话已撤销');
    } else {
        jsonError('撤销失败', 500);
    }
}

// ============================================================
// 上传头像
// ============================================================
function handleUploadAvatar($user) {
    $db = Database::getInstance();
    
    // 获取base64编码的头像数据
    $input = json_decode(file_get_contents('php://input'), true);
    $avatarData = isset($input['avatar']) ? $input['avatar'] : '';
    
    if (empty($avatarData)) {
        jsonError('请提供头像数据', 400);
    }
    
    // 解析base64数据
    if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $avatarData, $matches)) {
        $imageType = strtolower($matches[1]);
        $imageData = base64_decode($matches[2]);
        
        // 验证图片格式
        $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        if (!in_array($imageType, $allowedTypes)) {
            jsonError('不支持的图片格式，仅支持 JPG、PNG、GIF、WebP', 400);
        }
        
        // 验证图片大小（最大2MB）
        if (strlen($imageData) > 2 * 1024 * 1024) {
            jsonError('图片大小不能超过2MB', 400);
        }
        
        // 生成文件名
        $extension = ($imageType === 'jpeg') ? 'jpg' : $imageType;
        $fileName = 'avatar_' . $user['user_id'] . '_' . time() . '.' . $extension;
        $uploadDir = __DIR__ . '/../uploads/';
        $filePath = $uploadDir . $fileName;
        
        // 确保上传目录存在
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // 保存图片
        if (file_put_contents($filePath, $imageData) === false) {
            jsonError('头像保存失败', 500);
        }
        
        // 删除旧头像（如果存在且不是默认头像）
        $oldAvatar = $db->fetchOne("SELECT avatar FROM users WHERE id = ?", [$user['user_id']]);
        if ($oldAvatar && $oldAvatar['avatar']) {
            $oldFilePath = $uploadDir . basename($oldAvatar['avatar']);
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }
        
        // 构建头像URL（相对路径）
        $avatarUrl = '/uploads/' . $fileName;
        
        // 更新数据库
        $db->execute(
            "UPDATE users SET avatar = ? WHERE id = ?",
            [$avatarUrl, $user['user_id']]
        );
        
        // 记录操作日志
        logAdminAction($user['user_id'], 'upload_avatar', 'user:' . $user['user_id'], '用户更新了头像');
        
        jsonSuccess([
            'avatar' => $avatarUrl
        ], '头像上传成功');
        
    } else {
        jsonError('无效的头像数据格式', 400);
    }
}

// ============================================================
// 获取用户公开信息（根据uid）
// ============================================================
function handleGetUserInfo() {
    global $user;
    $db = Database::getInstance();
    
    $userId = (int)getParam('uid', 0);
    
    if ($userId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    $userInfo = $db->fetchOne(
        "SELECT id, username, nickname, avatar, status, is_admin, created_at, bio, post_count, follower_count, following_count 
         FROM users WHERE id = ?",
        [$userId]
    );
    
    if (!$userInfo) {
        jsonError('用户不存在', 404);
    }
    
    // 检查当前登录用户是否是该用户本人
    $isOwner = $user && $user['user_id'] == $userId;
    
    jsonSuccess([
        'user_id' => (int)$userInfo['id'],
        'id' => (int)$userInfo['id'],
        'username' => $userInfo['username'],
        'nickname' => $userInfo['nickname'] ?: $userInfo['username'],
        'avatar' => $userInfo['avatar'],
        'is_admin' => (int)$userInfo['is_admin'],
        'status' => (int)$userInfo['status'],
        'created_at' => $userInfo['created_at'],
        'bio' => $userInfo['bio'] ?: '',
        'post_count' => (int)($userInfo['post_count'] ?: 0),
        'follower_count' => (int)($userInfo['follower_count'] ?: 0),
        'following_count' => (int)($userInfo['following_count'] ?: 0),
        'is_owner' => $isOwner
    ], '获取成功');
}

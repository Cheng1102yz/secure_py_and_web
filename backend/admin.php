<?php
/**
 * ============================================================
 * SecureVault 用户系统 - 管理员后台 API
 * ============================================================
 * 接口列表（需要管理员权限）：
 *   GET  /admin.php?action=dashboard       仪表盘统计数据
 *   GET  /admin.php?action=users           用户列表
 *   POST /admin.php?action=user_status     修改用户状态（启用/禁用）
 *   POST /admin.php?action=user_delete     删除用户
 *   POST /admin.php?action=user_reset_pwd  重置用户密码
 *   GET  /admin.php?action=online_users    在线用户列表
 *   GET  /admin.php?action=login_logs      登录日志
 *   GET  /admin.php?action=admin_logs      管理员操作日志
 *   GET  /admin.php?action=software_list   软件版本列表
 *   POST /admin.php?action=software_add    添加软件版本
 *   POST /admin.php?action=software_update 更新软件版本
 *   POST /admin.php?action=software_delete 删除软件版本
 *   GET  /admin.php?action=latest_version  获取最新版本信息
 * 
 * 所有接口需要管理员登录，在请求头中携带 Authorization: Bearer {token}
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 设置CORS头
setCorsHeaders();

// 验证管理员权限
$admin = authenticateAdmin();
if (!$admin) {
    jsonError('需要管理员权限', 403);
}

// 获取操作类型
$action = getParam('action', '');

// 根据操作类型分发
switch ($action) {
    case 'dashboard':
        handleDashboard($admin);
        break;
    case 'users':
        handleUserList($admin);
        break;
    case 'user_status':
        handleUserStatus($admin);
        break;
    case 'user_delete':
        handleUserDelete($admin);
        break;
    case 'user_reset_pwd':
        handleUserResetPassword($admin);
        break;
    case 'online_users':
        handleOnlineUsers($admin);
        break;
    case 'login_logs':
        handleLoginLogs($admin);
        break;
    case 'admin_logs':
        handleAdminLogs($admin);
        break;
    case 'software_list':
        handleSoftwareList($admin);
        break;
    case 'software_add':
        handleSoftwareAdd($admin);
        break;
    case 'software_update':
        handleSoftwareUpdate($admin);
        break;
    case 'software_delete':
        handleSoftwareDelete($admin);
        break;
    case 'latest_version':
        handleLatestVersion($admin);
        break;
    case 'posts_pending':
        handlePendingPosts($admin);
        break;
    case 'post_approve':
        handleApprovePost($admin);
        break;
    case 'post_reject':
        handleRejectPost($admin);
        break;
    case 'posts_all':
        handleAllPosts($admin);
        break;
    case 'post_delete':
        handleDeletePost($admin);
        break;
    case 'post_edit':
        handleEditPost($admin);
        break;
    case 'post_toggle_top':
        handleToggleTopPost($admin);
        break;
    case 'post_toggle_featured':
        handleToggleFeaturedPost($admin);
        break;
    case 'post_detail_admin':
        handlePostDetailAdmin($admin);
        break;
    case 'send_notification':
        handleSendNotification($admin);
        break;
    case 'broadcast_notification':
        handleBroadcastNotification($admin);
        break;
    case 'update_user_role':
        handleUpdateUserRole($admin);
        break;
    case 'login_as':
        handleLoginAsUser($admin);
        break;
    case 'ip_location':
        handleIpLocation($admin);
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 权限验证辅助函数：副管理员不能操作主管理员创建的内容
// ============================================================
/**
 * 检查内容操作权限
 * 副管理员不能操作主管理员创建的内容
 * @param array $admin 当前管理员信息
 * @param int $contentUserId 内容创建者的用户ID
 * @param string $contentType 内容类型（用于错误提示）
 * @return void 无返回值，权限不足时直接返回JSON错误
 */
function checkContentPermission($admin, $contentUserId, $contentType = '内容') {
    // 主管理员可以操作所有内容
    if (isSuperAdmin($admin)) {
        return;
    }
    
    // 副管理员需要检查内容创建者是否是主管理员
    if (isModerator($admin)) {
        $db = Database::getInstance();
        $contentUser = $db->fetchOne("SELECT is_admin, username FROM users WHERE id = ?", [$contentUserId]);
        if ($contentUser && (int)$contentUser['is_admin'] === 2) {
            jsonError("副管理员不能操作主管理员（{$contentUser['username']}）创建的{$contentType}", 403);
        }
    }
}

// ============================================================
// 仪表盘统计数据
// ============================================================
function handleDashboard($admin) {
    $db = Database::getInstance();
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $monthAgo = date('Y-m-d', strtotime('-30 days'));
    
    // ====== 用户相关统计 ======
    // 总用户数
    $totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    
    // 今日新增用户
    $todayUsers = $db->fetchOne(
        "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?",
        [$today]
    );
    
    // 本周新增用户
    $weekUsers = $db->fetchOne(
        "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) >= ?",
        [$weekAgo]
    );
    
    // 本月新增用户
    $monthUsers = $db->fetchOne(
        "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) >= ?",
        [$monthAgo]
    );
    
    // 禁用用户数
    $disabledUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 0");
    
    // 管理员数量
    $adminCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
    
    // 活跃用户数（7天内登录过的）
    $activeUsers = $db->fetchOne(
        "SELECT COUNT(DISTINCT user_id) as count FROM login_logs 
         WHERE status = 1 AND DATE(login_time) >= ?",
        [$weekAgo]
    );
    
    // ====== 登录相关统计 ======
    // 在线用户数（有效会话）
    $onlineUsers = $db->fetchOne(
        "SELECT COUNT(DISTINCT user_id) as count FROM sessions WHERE is_active = 1 AND expires_at > ?",
        [$now]
    );
    
    // 今日登录成功次数
    $todayLogins = $db->fetchOne(
        "SELECT COUNT(*) as count FROM login_logs WHERE status = 1 AND DATE(login_time) = ?",
        [$today]
    );
    
    // 今日登录失败次数
    $todayFailedLogins = $db->fetchOne(
        "SELECT COUNT(*) as count FROM login_logs WHERE status = 0 AND DATE(login_time) = ?",
        [$today]
    );
    
    // 总登录次数
    $totalLogins = $db->fetchOne("SELECT COUNT(*) as count FROM login_logs WHERE status = 1");
    
    // 总会话数
    $totalSessions = $db->fetchOne("SELECT COUNT(*) as count FROM sessions");
    
    // ====== 软件相关统计 ======
    // 软件版本数量
    $softwareCount = $db->fetchOne("SELECT COUNT(*) as count FROM software");
    
    // 活跃软件版本数
    $activeSoftware = $db->fetchOne("SELECT COUNT(*) as count FROM software WHERE is_active = 1");
    
    // ====== 趋势数据 ======
    // 最近7天用户增长趋势
    $userTrend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $count = $db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?",
            [$date]
        );
        $userTrend[] = [
            'date' => $date,
            'count' => (int)$count['count']
        ];
    }
    
    // 最近7天登录趋势
    $loginTrend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $success = $db->fetchOne(
            "SELECT COUNT(*) as count FROM login_logs WHERE status = 1 AND DATE(login_time) = ?",
            [$date]
        );
        $failed = $db->fetchOne(
            "SELECT COUNT(*) as count FROM login_logs WHERE status = 0 AND DATE(login_time) = ?",
            [$date]
        );
        $loginTrend[] = [
            'date' => $date,
            'success' => (int)$success['count'],
            'failed' => (int)$failed['count']
        ];
    }
    
    // 最近注册的5个用户
    $recentUsers = $db->fetchAll(
        "SELECT id, username, nickname, created_at, status, last_login
         FROM users 
         ORDER BY created_at DESC 
         LIMIT 5"
    );
    
    // 最近登录的5个用户
    $recentLogins = $db->fetchAll(
        "SELECT l.username, l.login_time, l.ip_address, l.status, l.fail_reason
         FROM login_logs l
         ORDER BY l.login_time DESC
         LIMIT 5"
    );
    
    // ====== 社交相关统计 ======
    // 总帖子数
    $totalPosts = $db->fetchOne("SELECT COUNT(*) as count FROM posts");
    
    // 待审核帖子数
    $pendingPosts = $db->fetchOne("SELECT COUNT(*) as count FROM posts WHERE status = 0");
    
    // 已通过帖子数
    $approvedPosts = $db->fetchOne("SELECT COUNT(*) as count FROM posts WHERE status = 1");
    
    // 已驳回帖子数
    $rejectedPosts = $db->fetchOne("SELECT COUNT(*) as count FROM posts WHERE status = 2");
    
    // 今日新增帖子
    $todayPosts = $db->fetchOne(
        "SELECT COUNT(*) as count FROM posts WHERE DATE(created_at) = ?",
        [$today]
    );
    
    // 总评论数
    $totalComments = $db->fetchOne("SELECT COUNT(*) as count FROM post_comments");
    
    // 总点赞数
    $totalLikes = $db->fetchOne("SELECT COUNT(*) as count FROM post_likes");
    
    // 总关注关系数
    $totalFollows = $db->fetchOne("SELECT COUNT(*) as count FROM follows");
    
    // 总好友关系数
    $totalFriends = $db->fetchOne("SELECT COUNT(*) as count FROM friends WHERE status = 1");
    
    // 总消息数
    $totalMessages = $db->fetchOne("SELECT COUNT(*) as count FROM messages");
    
    // 总通知数
    $totalNotifications = $db->fetchOne("SELECT COUNT(*) as count FROM notifications");
    
    // ====== 文件相关统计 ======
    // 总文件数
    $totalFiles = $db->fetchOne("SELECT COUNT(*) as count FROM files");
    
    // 已上架文件数
    $activeFiles = $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE status = 1");
    
    // 总下载次数
    $totalDownloads = $db->fetchOne("SELECT COALESCE(SUM(download_count), 0) as count FROM files");
    
    // 今日新增文件
    $todayFiles = $db->fetchOne(
        "SELECT COUNT(*) as count FROM files WHERE DATE(created_at) = ?",
        [$today]
    );
    
    // 最近发布的5个帖子
    $recentPosts = $db->fetchAll(
        "SELECT p.id, p.title, p.content, p.status, p.created_at, p.like_count, p.comment_count,
                u.username, u.nickname
         FROM posts p
         LEFT JOIN users u ON p.user_id = u.id
         ORDER BY p.created_at DESC
         LIMIT 5"
    );
    
    jsonSuccess([
        // 用户统计
        'total_users' => (int)$totalUsers['count'],
        'today_new_users' => (int)$todayUsers['count'],
        'week_new_users' => (int)$weekUsers['count'],
        'month_new_users' => (int)$monthUsers['count'],
        'disabled_users' => (int)$disabledUsers['count'],
        'admin_count' => (int)$adminCount['count'],
        'active_users' => (int)$activeUsers['count'],
        // 登录统计
        'online_users' => (int)$onlineUsers['count'],
        'today_logins' => (int)$todayLogins['count'],
        'today_failed_logins' => (int)$todayFailedLogins['count'],
        'total_logins' => (int)$totalLogins['count'],
        'total_sessions' => (int)$totalSessions['count'],
        // 软件统计
        'software_count' => (int)$softwareCount['count'],
        'active_software' => (int)$activeSoftware['count'],
        // 社交统计
        'total_posts' => (int)$totalPosts['count'],
        'pending_posts' => (int)$pendingPosts['count'],
        'approved_posts' => (int)$approvedPosts['count'],
        'rejected_posts' => (int)$rejectedPosts['count'],
        'today_new_posts' => (int)$todayPosts['count'],
        'total_comments' => (int)$totalComments['count'],
        'total_likes' => (int)$totalLikes['count'],
        'total_follows' => (int)$totalFollows['count'],
        'total_friends' => (int)$totalFriends['count'],
        'total_messages' => (int)$totalMessages['count'],
        'total_notifications' => (int)$totalNotifications['count'],
        // 文件统计
        'total_files' => (int)$totalFiles['count'],
        'active_files' => (int)$activeFiles['count'],
        'total_downloads' => (int)$totalDownloads['count'],
        'today_new_files' => (int)$todayFiles['count'],
        // 趋势数据
        'week_trend' => $userTrend,
        'login_trend' => $loginTrend,
        // 列表数据
        'recent_users' => $recentUsers,
        'recent_logins' => $recentLogins,
        'recent_posts' => $recentPosts
    ], '获取成功');
}

// ============================================================
// 用户列表
// ============================================================
function handleUserList($admin) {
    $db = Database::getInstance();
    
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(100, max(1, (int)getParam('page_size', 20)));
    $keyword = trim(getParam('keyword', ''));
    $status = getParam('status', '');
    $offset = ($page - 1) * $pageSize;
    
    // 构建查询条件
    $where = [];
    $params = [];
    
    if (!empty($keyword)) {
        $where[] = "(username LIKE ? OR nickname LIKE ? OR email LIKE ?)";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%";
    }
    
    if ($status !== '' && $status !== 'all') {
        $where[] = "status = ?";
        $params[] = (int)$status;
    }
    
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // 查询总数
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM users {$whereSql}", $params);
    
    // 查询列表
    $users = $db->fetchAll(
        "SELECT id, username, nickname, email, status, is_admin, created_at, last_login, last_login_ip 
         FROM users 
         {$whereSql}
         ORDER BY created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$pageSize, $offset])
    );
    
    jsonSuccess([
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize,
        'list' => $users
    ], '获取成功');
}

// ============================================================
// 修改用户状态（启用/禁用）
// ============================================================
function handleUserStatus($admin) {
    $db = Database::getInstance();
    
    $userId = (int)getParam('user_id', 0);
    $status = (int)getParam('status', 0);
    
    if ($userId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    if (!in_array($status, [0, 1, 2])) {
        jsonError('无效的状态值', 400);
    }
    
    // 不能禁用自己
    if ($userId == $admin['user_id']) {
        jsonError('不能修改自己的状态', 400);
    }
    
    // 不能禁用管理员（需要先取消管理员权限）
    $targetUser = $db->fetchOne("SELECT is_admin, username FROM users WHERE id = ?", [$userId]);
    if (!$targetUser) {
        jsonError('用户不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员
    if (isModerator($admin) && (int)$targetUser['is_admin'] === 2) {
        jsonError('副管理员不能操作主管理员账号', 403);
    }
    
    // 不能禁用管理员（包括副管理员和主管理员，需要先取消管理员权限）
    if ((int)$targetUser['is_admin'] >= 1 && $status == 0) {
        jsonError('不能禁用管理员账号，请先取消其管理员权限', 400);
    }
    
    $result = $db->execute("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
    
    if ($result >= 0) {
        // 如果是禁用，同时使该用户的所有会话失效
        if ($status == 0) {
            $db->execute("UPDATE sessions SET is_active = 0 WHERE user_id = ?", [$userId]);
        }
        
        $statusText = $status == 1 ? '启用' : ($status == 0 ? '禁用' : '待验证');
        logAdminAction($admin['user_id'], 'user_status', 'user:' . $userId, "将用户 {$targetUser['username']} 状态修改为 {$statusText}");
        
        jsonSuccess(null, "用户已{$statusText}");
    } else {
        jsonError('操作失败', 500);
    }
}

// ============================================================
// 删除用户
// ============================================================
function handleUserDelete($admin) {
    $db = Database::getInstance();
    
    $userId = (int)getParam('user_id', 0);
    
    if ($userId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    if ($userId == $admin['user_id']) {
        jsonError('不能删除自己', 400);
    }
    
    $targetUser = $db->fetchOne("SELECT username, is_admin FROM users WHERE id = ?", [$userId]);
    if (!$targetUser) {
        jsonError('用户不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员
    if (isModerator($admin) && (int)$targetUser['is_admin'] === 2) {
        jsonError('副管理员不能操作主管理员账号', 403);
    }
    
    // 不能删除管理员（包括副管理员和主管理员，需要先取消管理员权限）
    if ((int)$targetUser['is_admin'] >= 1) {
        jsonError('不能删除管理员账号，请先取消其管理员权限', 400);
    }
    
    $result = $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
    
    if ($result > 0) {
        logAdminAction($admin['user_id'], 'user_delete', 'user:' . $userId, "删除用户 {$targetUser['username']}");
        jsonSuccess(null, '用户已删除');
    } else {
        jsonError('删除失败', 500);
    }
}

// ============================================================
// 重置用户密码
// ============================================================
function handleUserResetPassword($admin) {
    $db = Database::getInstance();
    
    $userId = (int)getParam('user_id', 0);
    $newPassword = getParam('new_password', '');
    
    if ($userId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    if (empty($newPassword)) {
        jsonError('新密码不能为空', 400);
    }
    
    $passwordCheck = validatePassword($newPassword);
    if ($passwordCheck !== true) {
        jsonError($passwordCheck, 400);
    }
    
    $targetUser = $db->fetchOne("SELECT username, is_admin FROM users WHERE id = ?", [$userId]);
    if (!$targetUser) {
        jsonError('用户不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员
    if (isModerator($admin) && (int)$targetUser['is_admin'] === 2) {
        jsonError('副管理员不能操作主管理员账号', 403);
    }
    
    $newHash = hashPassword($newPassword);
    $result = $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $userId]);
    
    if ($result >= 0) {
        // 使该用户的所有会话失效
        $db->execute("UPDATE sessions SET is_active = 0 WHERE user_id = ?", [$userId]);
        
        logAdminAction($admin['user_id'], 'user_reset_pwd', 'user:' . $userId, "重置用户 {$targetUser['username']} 的密码");
        
        jsonSuccess(null, '密码已重置，用户需要重新登录');
    } else {
        jsonError('重置失败', 500);
    }
}

// ============================================================
// 在线用户列表
// ============================================================
function handleOnlineUsers($admin) {
    $db = Database::getInstance();
    
    $sessions = $db->fetchAll(
        "SELECT s.id, s.user_id, s.token, s.device_info, s.ip_address, s.created_at, s.expires_at,
                u.username, u.nickname, u.is_admin
         FROM sessions s
         JOIN users u ON s.user_id = u.id
         WHERE s.is_active = 1 AND s.expires_at > ?
         ORDER BY s.created_at DESC",
        [date('Y-m-d H:i:s')]
    );
    
    // 隐藏令牌
    foreach ($sessions as &$session) {
        $token = $session['token'];
        $session['token_masked'] = substr($token, 0, 8) . '...' . substr($token, -8);
        unset($session['token']);
    }
    
    jsonSuccess([
        'count' => count($sessions),
        'list' => $sessions
    ], '获取成功');
}

// ============================================================
// 登录日志
// ============================================================
function handleLoginLogs($admin) {
    $db = Database::getInstance();
    
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(100, max(1, (int)getParam('page_size', 20)));
    $keyword = trim(getParam('keyword', ''));
    $offset = ($page - 1) * $pageSize;
    
    $where = [];
    $params = [];
    
    if (!empty($keyword)) {
        $where[] = "(username LIKE ? OR ip_address LIKE ?)";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%";
    }
    
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM login_logs {$whereSql}", $params);
    
    $logs = $db->fetchAll(
        "SELECT * FROM login_logs {$whereSql} ORDER BY login_time DESC LIMIT ? OFFSET ?",
        array_merge($params, [$pageSize, $offset])
    );
    
    jsonSuccess([
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize,
        'list' => $logs
    ], '获取成功');
}

// ============================================================
// 管理员操作日志
// ============================================================
function handleAdminLogs($admin) {
    $db = Database::getInstance();
    
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(100, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM admin_logs");
    
    $logs = $db->fetchAll(
        "SELECT a.*, u.username as admin_name 
         FROM admin_logs a 
         LEFT JOIN users u ON a.admin_id = u.id 
         ORDER BY a.created_at DESC 
         LIMIT ? OFFSET ?",
        [$pageSize, $offset]
    );
    
    jsonSuccess([
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize,
        'list' => $logs
    ], '获取成功');
}

// ============================================================
// 软件版本列表
// ============================================================
function handleSoftwareList($admin) {
    $db = Database::getInstance();
    
    $software = $db->fetchAll(
        "SELECT * FROM software ORDER BY created_at DESC"
    );
    
    jsonSuccess([
        'count' => count($software),
        'list' => $software
    ], '获取成功');
}

// ============================================================
// 添加软件版本
// ============================================================
function handleSoftwareAdd($admin) {
    $db = Database::getInstance();
    
    $name = trim(getParam('name', ''));
    $version = trim(getParam('version', ''));
    $description = trim(getParam('description', ''));
    $downloadUrl = trim(getParam('download_url', ''));
    $fileSize = trim(getParam('file_size', ''));
    $isActive = (int)getParam('is_active', 1);
    
    if (empty($name) || empty($version)) {
        jsonError('软件名称和版本号不能为空', 400);
    }
    
    $id = $db->insert(
        "INSERT INTO software (name, version, description, download_url, file_size, is_active) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$name, $version, $description, $downloadUrl, $fileSize, $isActive]
    );
    
    if ($id) {
        logAdminAction($admin['user_id'], 'software_add', 'software:' . $id, "添加软件版本 {$name} v{$version}");
        jsonSuccess(['id' => (int)$id], '添加成功');
    } else {
        jsonError('添加失败', 500);
    }
}

// ============================================================
// 更新软件版本
// ============================================================
function handleSoftwareUpdate($admin) {
    $db = Database::getInstance();
    
    $id = (int)getParam('id', 0);
    $name = trim(getParam('name', ''));
    $version = trim(getParam('version', ''));
    $description = trim(getParam('description', ''));
    $downloadUrl = trim(getParam('download_url', ''));
    $fileSize = trim(getParam('file_size', ''));
    $isActive = (int)getParam('is_active', 1);
    
    if ($id <= 0) {
        jsonError('无效的ID', 400);
    }
    
    $result = $db->execute(
        "UPDATE software SET name = ?, version = ?, description = ?, download_url = ?, file_size = ?, is_active = ? 
         WHERE id = ?",
        [$name, $version, $description, $downloadUrl, $fileSize, $isActive, $id]
    );
    
    if ($result >= 0) {
        logAdminAction($admin['user_id'], 'software_update', 'software:' . $id, "更新软件版本 {$name} v{$version}");
        jsonSuccess(null, '更新成功');
    } else {
        jsonError('更新失败', 500);
    }
}

// ============================================================
// 删除软件版本
// ============================================================
function handleSoftwareDelete($admin) {
    $db = Database::getInstance();
    
    $id = (int)getParam('id', 0);
    
    if ($id <= 0) {
        jsonError('无效的ID', 400);
    }
    
    $software = $db->fetchOne("SELECT name, version FROM software WHERE id = ?", [$id]);
    
    $result = $db->execute("DELETE FROM software WHERE id = ?", [$id]);
    
    if ($result > 0) {
        logAdminAction($admin['user_id'], 'software_delete', 'software:' . $id, "删除软件版本 {$software['name']} v{$software['version']}");
        jsonSuccess(null, '删除成功');
    } else {
        jsonError('删除失败', 500);
    }
}

// ============================================================
// 获取最新版本信息（公开接口，但放在admin.php中方便管理）
// ============================================================
function handleLatestVersion($admin) {
    $db = Database::getInstance();
    
    $latest = $db->fetchOne(
        "SELECT * FROM software WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1"
    );
    
    if ($latest) {
        jsonSuccess($latest, '获取成功');
    } else {
        jsonSuccess([
            'name' => SOFTWARE_NAME,
            'version' => SOFTWARE_VERSION,
            'description' => '暂无更新',
            'download_url' => null
        ], '暂无发布版本');
    }
}

// ============================================================
// 待审核帖子列表
// ============================================================
function handlePendingPosts($admin) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM posts WHERE status = 0");
    
    $posts = $db->fetchAll(
        "SELECT p.*, u.username, u.nickname, u.avatar, u.unique_id
         FROM posts p 
         LEFT JOIN users u ON p.user_id = u.id 
         WHERE p.status = 0
         ORDER BY p.created_at ASC 
         LIMIT ? OFFSET ?",
        [$pageSize, $offset]
    );
    
    foreach ($posts as &$post) {
        if ($post['images']) {
            $post['images'] = json_decode($post['images'], true) ?: [];
        } else {
            $post['images'] = [];
        }
        $post['summary'] = mb_substr(strip_tags($post['content']), 0, 200);
    }
    
    jsonSuccess([
        'list' => $posts,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 审核通过帖子
// ============================================================
function handleApprovePost($admin) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    
    $post = $db->fetchOne("SELECT id, user_id, title FROM posts WHERE id = ? AND status = 0", [$postId]);
    if (!$post) {
        jsonError('帖子不存在或已审核', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员的帖子
    checkContentPermission($admin, $post['user_id'], '帖子');
    
    $db->execute("UPDATE posts SET status = 1, updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $postId]);
    
    // 发送通知给作者
    sendNotification($post['user_id'], 'post_approved', '帖子审核通过', '您的帖子《' . ($post['title'] ?: mb_substr($post['content'], 0, 20)) . '》已通过审核，现已展示在主页。', $postId);
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'post_approve', 'post:' . $postId, "审核通过帖子 #$postId");
    
    jsonSuccess(null, '审核通过');
}

// ============================================================
// 驳回帖子
// ============================================================
function handleRejectPost($admin) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    $reason = isset($input['reason']) ? trim($input['reason']) : '';
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    if (empty($reason)) {
        jsonError('请填写驳回原因', 400);
    }
    
    $post = $db->fetchOne("SELECT id, user_id, title, content FROM posts WHERE id = ? AND status = 0", [$postId]);
    if (!$post) {
        jsonError('帖子不存在或已审核', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员的帖子
    checkContentPermission($admin, $post['user_id'], '帖子');
    
    $db->execute("UPDATE posts SET status = 2, reject_reason = ?, updated_at = ? WHERE id = ?", [$reason, date('Y-m-d H:i:s'), $postId]);
    
    // 发送通知给作者
    sendNotification($post['user_id'], 'post_rejected', '帖子被驳回', '您的帖子《' . ($post['title'] ?: mb_substr($post['content'], 0, 20)) . '》未通过审核，原因：' . $reason, $postId);
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'post_reject', 'post:' . $postId, "驳回帖子 #$postId，原因：$reason");
    
    jsonSuccess(null, '已驳回');
}

// ============================================================
// 所有帖子列表（管理用）
// ============================================================
function handleAllPosts($admin) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    $status = getParam('status', '');
    $isTop = getParam('is_top', '');
    $isFeatured = getParam('is_featured', '');
    $categoryId = getParam('category_id', '');
    
    $where = "WHERE 1=1";
    $params = [];
    
    if ($status !== '') {
        $where .= " AND p.status = ?";
        $params[] = (int)$status;
    }
    
    if ($isTop !== '') {
        $where .= " AND p.is_top = ?";
        $params[] = (int)$isTop;
    }
    
    if ($isFeatured !== '') {
        $where .= " AND p.is_featured = ?";
        $params[] = (int)$isFeatured;
    }
    
    if ($categoryId !== '') {
        $where .= " AND p.category_id = ?";
        $params[] = (int)$categoryId;
    }
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM posts p $where", $params);
    
    $posts = $db->fetchAll(
        "SELECT p.*, u.username, u.nickname, u.avatar, u.unique_id,
                c.name as category_name, c.slug as category_slug
         FROM posts p 
         LEFT JOIN users u ON p.user_id = u.id 
         LEFT JOIN categories c ON p.category_id = c.id
         $where
         ORDER BY p.id DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$pageSize, $offset])
    );
    
    foreach ($posts as &$post) {
        if ($post['images']) {
            $post['images'] = json_decode($post['images'], true) ?: [];
        } else {
            $post['images'] = [];
        }
        $post['summary'] = mb_substr(strip_tags($post['content']), 0, 100);
    }
    
    jsonSuccess([
        'list' => $posts,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 删除帖子
// ============================================================
function handleDeletePost($admin) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    
    $post = $db->fetchOne("SELECT id, user_id FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        jsonError('帖子不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员的帖子
    checkContentPermission($admin, $post['user_id'], '帖子');
    
    // 删除帖子（级联删除评论、点赞）
    $db->execute("DELETE FROM posts WHERE id = ?", [$postId]);
    $db->execute("DELETE FROM post_comments WHERE post_id = ?", [$postId]);
    $db->execute("DELETE FROM post_likes WHERE post_id = ?", [$postId]);
    
    // 更新用户帖子数
    $db->execute("UPDATE users SET post_count = GREATEST(post_count - 1, 0) WHERE id = ?", [$post['user_id']]);
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'post_delete', 'post:' . $postId, "删除帖子 #$postId");
    
    jsonSuccess(null, '删除成功');
}

// ============================================================
// 编辑帖子
// ============================================================
function handleEditPost($admin) {
    $db = Database::getInstance();
    $input = getJsonInput();
    
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    $title = isset($input['title']) ? trim($input['title']) : '';
    $content = isset($input['content']) ? trim($input['content']) : '';
    $adminNote = isset($input['admin_note']) ? trim($input['admin_note']) : '';
    
    if (!$postId) {
        jsonError('帖子ID不能为空');
    }
    
    $post = $db->fetchOne("SELECT * FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        jsonError('帖子不存在');
    }
    
    // 【权限验证】副管理员不能操作主管理员的帖子
    checkContentPermission($admin, $post['user_id'], '帖子');
    
    $fields = [];
    $params = [];
    
    if ($title !== '') {
        $fields[] = 'title = ?';
        $params[] = $title;
    }
    if ($content !== '') {
        $fields[] = 'content = ?';
        $params[] = $content;
    }
    if ($adminNote !== '') {
        $fields[] = 'admin_note = ?';
        $params[] = $adminNote;
    }
    $fields[] = 'updated_at = ?';
    $params[] = date('Y-m-d H:i:s');
    
    if (empty($fields)) {
        jsonError('没有需要更新的字段');
    }
    
    $params[] = $postId;
    $db->execute("UPDATE posts SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    
    logAdminAction($admin['user_id'], 'post_edit', 'post:' . $postId, "编辑帖子 #$postId");
    
    jsonSuccess(null, '编辑成功');
}

// ============================================================
// 置顶/取消置顶帖子
// ============================================================
function handleToggleTopPost($admin) {
    $db = Database::getInstance();
    $input = getJsonInput();
    
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    
    if (!$postId) {
        jsonError('帖子ID不能为空');
    }
    
    $post = $db->fetchOne("SELECT * FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        jsonError('帖子不存在');
    }
    
    // 【权限验证】副管理员不能操作主管理员的帖子
    checkContentPermission($admin, $post['user_id'], '帖子');
    
    $newStatus = $post['is_top'] == 1 ? 0 : 1;
    $db->execute("UPDATE posts SET is_top = ? WHERE id = ?", [$newStatus, $postId]);
    
    logAdminAction($admin['user_id'], 'post_toggle_top', 'post:' . $postId, ($newStatus ? '置顶' : '取消置顶') . "帖子 #$postId");
    
    jsonSuccess(['is_top' => $newStatus], $newStatus ? '置顶成功' : '取消置顶成功');
}

// ============================================================
// 加精/取消加精帖子
// ============================================================
function handleToggleFeaturedPost($admin) {
    $db = Database::getInstance();
    $input = getJsonInput();
    
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    
    if (!$postId) {
        jsonError('帖子ID不能为空');
    }
    
    $post = $db->fetchOne("SELECT * FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        jsonError('帖子不存在');
    }
    
    // 【权限验证】副管理员不能操作主管理员的帖子
    checkContentPermission($admin, $post['user_id'], '帖子');
    
    $newStatus = $post['is_featured'] == 1 ? 0 : 1;
    $db->execute("UPDATE posts SET is_featured = ? WHERE id = ?", [$newStatus, $postId]);
    
    logAdminAction($admin['user_id'], 'post_toggle_featured', 'post:' . $postId, ($newStatus ? '加精' : '取消加精') . "帖子 #$postId");
    
    jsonSuccess(['is_featured' => $newStatus], $newStatus ? '加精成功' : '取消加精成功');
}

// ============================================================
// 查看帖子详情（管理员视角）
// ============================================================
function handlePostDetailAdmin($admin) {
    $db = Database::getInstance();
    
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : (isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0);
    
    if (!$postId) {
        jsonError('帖子ID不能为空');
    }
    
    $post = $db->fetchOne("
        SELECT p.*, u.username, u.nickname, u.avatar, u.unique_id
        FROM posts p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ", [$postId]);
    
    if (!$post) {
        jsonError('帖子不存在');
    }
    
    // 获取评论数
    $commentCount = $db->fetchOne("SELECT COUNT(*) as count FROM post_comments WHERE post_id = ?", [$postId]);
    $post['comment_count'] = (int)$commentCount['count'];
    
    // 获取点赞数
    $likeCount = $db->fetchOne("SELECT COUNT(*) as count FROM post_likes WHERE post_id = ?", [$postId]);
    $post['like_count'] = (int)$likeCount['count'];
    
    jsonSuccess($post, '获取成功');
}

// ============================================================
// 给指定用户发送官方消息
// ============================================================
function handleSendNotification($admin) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $title = isset($input['title']) ? trim($input['title']) : '';
    $content = isset($input['content']) ? trim($input['content']) : '';
    
    // 参数验证
    if ($userId <= 0) {
        jsonError('用户ID无效', 400);
    }
    if (empty($title)) {
        jsonError('消息标题不能为空', 400);
    }
    if (empty($content)) {
        jsonError('消息内容不能为空', 400);
    }
    
    // 检查用户是否存在
    $user = $db->fetchOne("SELECT id, username, nickname FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        jsonError('用户不存在', 404);
    }
    
    // 插入官方消息（type=official，系统通知和官方消息归为一类）
    $notificationId = $db->insert(
        "INSERT INTO notifications (user_id, type, title, content, related_id, is_read, created_at)
         VALUES (?, 'official', ?, ?, 0, 0, ?)",
        [$userId, $title, $content, date('Y-m-d H:i:s')]
    );
    
    if (!$notificationId) {
        jsonError('发送失败，请稍后重试', 500);
    }
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'send_notification', "给用户 {$user['username']} 发送官方消息: {$title}");
    
    jsonSuccess([
        'notification_id' => $notificationId,
        'user_id' => $userId,
        'username' => $user['username']
    ], '官方消息发送成功');
}

// ============================================================
// 给所有用户广播官方消息
// ============================================================
function handleBroadcastNotification($admin) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $title = isset($input['title']) ? trim($input['title']) : '';
    $content = isset($input['content']) ? trim($input['content']) : '';
    
    // 参数验证
    if (empty($title)) {
        jsonError('消息标题不能为空', 400);
    }
    if (empty($content)) {
        jsonError('消息内容不能为空', 400);
    }
    
    // 获取所有启用的用户
    $users = $db->fetchAll("SELECT id FROM users WHERE status = 1");
    $userCount = count($users);
    
    if ($userCount == 0) {
        jsonError('没有可发送的用户', 400);
    }
    
    // 批量插入官方消息
    $successCount = 0;
    foreach ($users as $user) {
        $notificationId = $db->insert(
            "INSERT INTO notifications (user_id, type, title, content, related_id, is_read, created_at)
             VALUES (?, 'official', ?, ?, 0, 0, ?)",
            [$user['id'], $title, $content, date('Y-m-d H:i:s')]
        );
        if ($notificationId) {
            $successCount++;
        }
    }
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'broadcast_notification', "广播官方消息: {$title}, 发送给 {$successCount}/{$userCount} 个用户");
    
    jsonSuccess([
        'total_users' => $userCount,
        'success_count' => $successCount,
        'title' => $title
    ], "广播成功，已发送给 {$successCount} 个用户");
}

// ============================================================
// 修改用户身份（只有主管理员可以操作）
// ============================================================
function handleUpdateUserRole($admin) {
    $db = Database::getInstance();
    
    // 【权限验证】只有主管理员才能修改用户身份
    if (!isSuperAdmin($admin)) {
        jsonError('只有主管理员才能修改用户身份', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $newRole = isset($input['role']) ? (int)$input['role'] : 0;
    
    // 参数验证
    if ($userId <= 0) {
        jsonError('用户ID无效', 400);
    }
    if (!in_array($newRole, [0, 1, 2])) {
        jsonError('身份值无效（0=普通用户, 1=副管理员, 2=主管理员）', 400);
    }
    
    // 检查目标用户是否存在
    $targetUser = $db->fetchOne("SELECT id, username, nickname, is_admin FROM users WHERE id = ?", [$userId]);
    if (!$targetUser) {
        jsonError('用户不存在', 404);
    }
    
    // 【安全限制1】不能修改自己的身份（防止把自己降级后无法管理）
    if ($targetUser['id'] == $admin['user_id']) {
        jsonError('不能修改自己的身份', 400);
    }
    
    // 【安全限制2】如果要把用户降级为普通用户，且该用户是主管理员，需要确保还有其他主管理员
    if ((int)$targetUser['is_admin'] === 2 && $newRole < 2) {
        $superAdminCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_admin = 2");
        if ((int)$superAdminCount['count'] <= 1) {
            jsonError('系统至少需要保留一个主管理员，不能降级最后一个主管理员', 400);
        }
    }
    
    // 执行更新
    $result = $db->execute(
        "UPDATE users SET is_admin = ? WHERE id = ?",
        [$newRole, $userId]
    );
    
    if (!$result) {
        jsonError('修改身份失败，请稍后重试', 500);
    }
    
    $roleName = getRoleName($newRole);
    $oldRoleName = getRoleName($targetUser['is_admin']);
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'update_user_role', "将用户 {$targetUser['username']} 的身份从 {$oldRoleName} 修改为 {$roleName}");
    
    jsonSuccess([
        'user_id' => $userId,
        'username' => $targetUser['username'],
        'old_role' => (int)$targetUser['is_admin'],
        'old_role_name' => $oldRoleName,
        'new_role' => $newRole,
        'new_role_name' => $roleName
    ], "用户身份已修改为：{$roleName}");
}

// ============================================================
// 主管理员登录用户账号
// ============================================================
function handleLoginAsUser($admin) {
    // 只有主管理员可以登录用户账号
    if (!isSuperAdmin($admin)) {
        jsonError('只有主管理员可以登录用户账号', 403);
    }
    
    $userId = (int)getParam('user_id', 0);
    if ($userId <= 0) {
        // 尝试从POST获取
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    }
    
    if ($userId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    $db = Database::getInstance();
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        jsonError('用户不存在', 404);
    }
    
    // 不能登录主管理员账号
    if ((int)$user['is_admin'] === 2) {
        jsonError('不能登录主管理员账号', 400);
    }
    
    // 生成登录token（复用auth.php的逻辑）
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24小时有效
    $ip = getClientIp();
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // 插入登录会话（使用正确的表名 sessions）
    $db->execute(
        "INSERT INTO sessions (user_id, token, device_info, ip_address, expires_at, is_active)
         VALUES (?, ?, ?, ?, ?, 1)",
        [$userId, $token, $deviceInfo, $ip, $expiresAt]
    );
    
    // 更新用户最后登录时间
    $db->execute(
        "UPDATE users SET last_login = ? WHERE id = ?",
        [date('Y-m-d H:i:s'), $userId]
    );
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'login_as_user', "主管理员登录了用户 {$user['username']} 的账号");
    
    jsonSuccess([
        'token' => $token,
        'user' => [
            'user_id' => (int)$user['id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'email' => $user['email'],
            'avatar' => $user['avatar'],
            'is_admin' => (int)$user['is_admin']
        ],
        'expires_at' => $expiresAt
    ], "已登录用户 {$user['username']} 的账号");
}

// ============================================================
// 判断是否为私有/本地IP地址（完整的IP段判断）
// ============================================================
function isPrivateIp($ip) {
    // IPv4 私有地址段
    $ipv4PrivateRanges = [
        ['10.0.0.0', '10.255.255.255'],      // 10.0.0.0/8 - A类私有
        ['172.16.0.0', '172.31.255.255'],    // 172.16.0.0/12 - B类私有
        ['192.168.0.0', '192.168.255.255'],  // 192.168.0.0/16 - C类私有
        ['127.0.0.0', '127.255.255.255'],    // 127.0.0.0/8 - 回环地址
        ['169.254.0.0', '169.254.255.255'],  // 169.254.0.0/16 - 链路本地
        ['100.64.0.0', '100.127.255.255'],   // 100.64.0.0/10 - 运营商级NAT
        ['0.0.0.0', '0.255.255.255'],         // 0.0.0.0/8 - 本网络
        ['255.255.255.255', '255.255.255.255'], // 广播地址
    ];
    
    // IPv6 私有地址前缀
    $ipv6PrivatePrefixes = [
        '::1',       // 回环地址
        'fc',        // 唯一本地地址（ULA）fc00::/7
        'fd',        // 唯一本地地址（ULA）fc00::/7
        'fe80',      // 链路本地地址 fe80::/10
        'fe90',      // 链路本地地址 fe80::/10
        'fea0',      // 链路本地地址 fe80::/10
        'feb0',      // 链路本地地址 fe80::/10
        '::ffff:',   // IPv4映射地址
    ];
    
    // 判断是否为IPv4
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ipLong = ip2long($ip);
        if ($ipLong === false) return false;
        
        foreach ($ipv4PrivateRanges as $range) {
            $start = ip2long($range[0]);
            $end = ip2long($range[1]);
            if ($ipLong >= $start && $ipLong <= $end) {
                return true;
            }
        }
        return false;
    }
    
    // 判断是否为IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ipLower = strtolower($ip);
        foreach ($ipv6PrivatePrefixes as $prefix) {
            if (strpos($ipLower, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }
    
    return false;
}

// ============================================================
// 查询IP地理位置
// ============================================================
function handleIpLocation($admin) {
    $ip = getParam('ip', '');
    if (empty($ip)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $ip = isset($input['ip']) ? trim($input['ip']) : '';
    }
    
    if (empty($ip)) {
        jsonError('IP地址不能为空', 400);
    }
    
    // 验证IP格式
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        jsonError('无效的IP地址格式', 400);
    }
    
    // 私有/本地IP直接返回（使用完整的IP段判断）
    if (isPrivateIp($ip)) {
        // 判断具体类型，给出更准确的描述
        $ipLong = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? ip2long($ip) : 0;
        $type = '本地网络';
        if ($ipLong && $ipLong >= ip2long('127.0.0.0') && $ipLong <= ip2long('127.255.255.255')) {
            $type = '回环地址';
        } elseif ($ip === '::1') {
            $type = '回环地址';
        } elseif (strpos($ip, '169.254.') === 0) {
            $type = '链路本地';
        } elseif (strpos($ip, '100.64.') === 0 || strpos($ip, '100.65.') === 0 || strpos($ip, '100.66.') === 0 || strpos($ip, '100.67.') === 0 || strpos($ip, '100.68.') === 0 || strpos($ip, '100.69.') === 0 || strpos($ip, '100.70.') === 0 || strpos($ip, '100.71.') === 0 || strpos($ip, '100.72.') === 0 || strpos($ip, '100.73.') === 0 || strpos($ip, '100.74.') === 0 || strpos($ip, '100.75.') === 0 || strpos($ip, '100.76.') === 0 || strpos($ip, '100.77.') === 0 || strpos($ip, '100.78.') === 0 || strpos($ip, '100.79.') === 0 || strpos($ip, '100.80.') === 0 || strpos($ip, '100.81.') === 0 || strpos($ip, '100.82.') === 0 || strpos($ip, '100.83.') === 0 || strpos($ip, '100.84.') === 0 || strpos($ip, '100.85.') === 0 || strpos($ip, '100.86.') === 0 || strpos($ip, '100.87.') === 0 || strpos($ip, '100.88.') === 0 || strpos($ip, '100.89.') === 0 || strpos($ip, '100.90.') === 0 || strpos($ip, '100.91.') === 0 || strpos($ip, '100.92.') === 0 || strpos($ip, '100.93.') === 0 || strpos($ip, '100.94.') === 0 || strpos($ip, '100.95.') === 0 || strpos($ip, '100.96.') === 0 || strpos($ip, '100.97.') === 0 || strpos($ip, '100.98.') === 0 || strpos($ip, '100.99.') === 0 || strpos($ip, '100.100.') === 0 || strpos($ip, '100.101.') === 0 || strpos($ip, '100.102.') === 0 || strpos($ip, '100.103.') === 0 || strpos($ip, '100.104.') === 0 || strpos($ip, '100.105.') === 0 || strpos($ip, '100.106.') === 0 || strpos($ip, '100.107.') === 0 || strpos($ip, '100.108.') === 0 || strpos($ip, '100.109.') === 0 || strpos($ip, '100.110.') === 0 || strpos($ip, '100.111.') === 0 || strpos($ip, '100.112.') === 0 || strpos($ip, '100.113.') === 0 || strpos($ip, '100.114.') === 0 || strpos($ip, '100.115.') === 0 || strpos($ip, '100.116.') === 0 || strpos($ip, '100.117.') === 0 || strpos($ip, '100.118.') === 0 || strpos($ip, '100.119.') === 0 || strpos($ip, '100.120.') === 0 || strpos($ip, '100.121.') === 0 || strpos($ip, '100.122.') === 0 || strpos($ip, '100.123.') === 0 || strpos($ip, '100.124.') === 0 || strpos($ip, '100.125.') === 0 || strpos($ip, '100.126.') === 0 || strpos($ip, '100.127.') === 0) {
            $type = '运营商NAT';
        }
        
        jsonSuccess([
            'ip' => $ip,
            'country' => $type,
            'region' => '局域网',
            'city' => '内网',
            'isp' => '本地',
            'location' => $type
        ], '本地/私有IP地址');
        return;
    }
    
    // 调用多个IP查询API，失败时自动切换
    $apis = [
        [
            'url' => "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,country,regionName,city,isp,org,query",
            'timeout' => 8,
            'parser' => function($data) {
                if (!$data || !isset($data['status']) || $data['status'] !== 'success') return null;
                return [
                    'country' => $data['country'] ?? '',
                    'region' => $data['regionName'] ?? '',
                    'city' => $data['city'] ?? '',
                    'isp' => $data['isp'] ?? '',
                    'org' => $data['org'] ?? ''
                ];
            }
        ],
        [
            'url' => "https://ipapi.co/{$ip}/json/",
            'timeout' => 8,
            'parser' => function($data) {
                if (!$data || isset($data['error'])) return null;
                return [
                    'country' => $data['country_name'] ?? '',
                    'region' => $data['region'] ?? '',
                    'city' => $data['city'] ?? '',
                    'isp' => $data['org'] ?? '',
                    'org' => $data['org'] ?? ''
                ];
            }
        ],
        [
            'url' => "https://ipwhois.app/json/{$ip}?lang=zh-CN",
            'timeout' => 8,
            'parser' => function($data) {
                if (!$data || !isset($data['success']) || !$data['success']) return null;
                return [
                    'country' => $data['country'] ?? '',
                    'region' => $data['region'] ?? '',
                    'city' => $data['city'] ?? '',
                    'isp' => $data['isp'] ?? '',
                    'org' => $data['org'] ?? ''
                ];
            }
        ]
    ];
    
    $result = null;
    $usedApi = '';
    
    // 依次尝试每个API，每个API重试1次
    foreach ($apis as $apiIndex => $api) {
        for ($retry = 0; $retry < 2; $retry++) {
            try {
                $response = false;
                
                // 优先使用 curl
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $api['url']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, $api['timeout']);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode !== 200) {
                        $response = false;
                    }
                }
                
                // curl 不可用或失败时，使用 file_get_contents
                if (!$response && function_exists('file_get_contents')) {
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => $api['timeout'],
                            'method' => 'GET',
                            'header' => "User-Agent: Mozilla/5.0\r\n"
                        ],
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false
                        ]
                    ]);
                    $response = @file_get_contents($api['url'], false, $context);
                }
                
                if (!$response) {
                    continue; // 重试或切换API
                }
                
                $data = json_decode($response, true);
                $parsed = $api['parser']($data);
                
                if ($parsed && !empty($parsed['country'])) {
                    $result = $parsed;
                    $usedApi = 'API_' . ($apiIndex + 1);
                    break 2; // 成功，跳出所有循环
                }
                
            } catch (Exception $e) {
                // 继续尝试下一个API
                continue;
            }
        }
    }
    
    // 如果所有API都失败，返回默认值
    if (!$result) {
        jsonSuccess([
            'ip' => $ip,
            'country' => '未知',
            'region' => '未知',
            'city' => '未知',
            'isp' => '未知',
            'org' => '',
            'location' => '未知地区'
        ], '无法查询到该IP的地理位置');
        return;
    }
    
    // 组装位置信息
    $locationParts = [];
    if (!empty($result['country'])) $locationParts[] = $result['country'];
    if (!empty($result['region'])) $locationParts[] = $result['region'];
    if (!empty($result['city'])) $locationParts[] = $result['city'];
    $location = implode(' · ', $locationParts);
    
    jsonSuccess([
        'ip' => $ip,
        'country' => $result['country'] ?: '未知',
        'region' => $result['region'] ?: '未知',
        'city' => $result['city'] ?: '未知',
        'isp' => $result['isp'] ?: '未知',
        'org' => $result['org'] ?: '',
        'location' => $location ?: '未知地区',
        'api_source' => $usedApi
    ], '查询成功');
}

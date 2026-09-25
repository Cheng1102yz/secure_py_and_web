<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 公告管理 API
 * ============================================================
 * 功能：公告的发布、列表、详情、更新、删除等操作
 * 接口：
 *   - list: 公告列表（公开访问，只返回已发布的公告）
 *   - detail: 公告详情（公开访问）
 *   - create: 发布公告（需要管理员权限）
 *   - update: 更新公告（需要管理员权限）
 *   - delete: 删除公告（需要管理员权限）
 *   - admin_list: 管理员公告列表（需要管理员权限）
 */

require_once __DIR__ . '/helpers.php';

// 设置CORS头
setCorsHeaders();

// 获取操作类型
$action = getParam('action', '');

// 需要管理员权限的操作
$adminActions = ['create', 'update', 'delete', 'admin_list'];
$needAdmin = in_array($action, $adminActions);

// 验证登录
$user = authenticate();
if ($needAdmin) {
    if (!$user) {
        jsonError('请先登录', 401);
    }
    // 验证是否是管理员
    if (!isset($user['is_admin']) || !$user['is_admin']) {
        jsonError('需要管理员权限', 403);
    }
}

// 根据操作类型分发
switch ($action) {
    case 'list':
        handleAnnouncementList();
        break;
    case 'detail':
        handleAnnouncementDetail();
        break;
    case 'create':
        handleAnnouncementCreate($user);
        break;
    case 'update':
        handleAnnouncementUpdate($user);
        break;
    case 'delete':
        handleAnnouncementDelete($user);
        break;
    case 'admin_list':
        handleAdminAnnouncementList($user);
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 公告列表（公开访问，只返回已发布的公告）
// ============================================================
function handleAnnouncementList() {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 10)));
    $offset = ($page - 1) * $pageSize;
    $type = trim(getParam('type', ''));
    
    // 注意：所有列名必须加表别名a.，避免与users表的列名歧义
    $where = "WHERE a.status = 1";
    $params = [];
    
    // 按类型筛选
    if ($type && $type !== 'all') {
        $where .= " AND a.type = ?";
        $params[] = $type;
    }
    
    // 查询总数
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM announcements a $where", $params);
    
    // 查询列表（按创建时间倒序，重要公告优先）
    // 注意：LIMIT和OFFSET必须直接拼接到SQL中，不能用参数绑定
    // 因为SQLite中如果参数是字符串类型，会导致查询返回空结果
    $sql = "SELECT a.*, u.username as admin_username, u.nickname as admin_nickname
            FROM announcements a
            LEFT JOIN users u ON a.admin_id = u.id
            $where
            ORDER BY CASE WHEN a.type = 'urgent' THEN 0 WHEN a.type = 'important' THEN 1 ELSE 2 END, a.created_at DESC
            LIMIT " . (int)$pageSize . " OFFSET " . (int)$offset;
    $announcements = $db->fetchAll($sql, $params);
    
    // 处理内容摘要
    foreach ($announcements as &$ann) {
        $ann['summary'] = mb_substr(strip_tags($ann['content']), 0, 150);
    }
    
    jsonSuccess([
        'list' => $announcements,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 公告详情（公开访问）
// ============================================================
function handleAnnouncementDetail() {
    $db = Database::getInstance();
    $id = (int)getParam('id', 0);
    
    if ($id <= 0) {
        jsonError('无效的公告ID', 400);
    }
    
    $announcement = $db->fetchOne(
        "SELECT a.*, u.username as admin_username, u.nickname as admin_nickname
         FROM announcements a
         LEFT JOIN users u ON a.admin_id = u.id
         WHERE a.id = ? AND a.status = 1",
        [$id]
    );
    
    if (!$announcement) {
        jsonError('公告不存在或未发布', 404);
    }
    
    jsonSuccess($announcement, '获取成功');
}

// ============================================================
// 发布公告（需要管理员权限）
// ============================================================
function handleAnnouncementCreate($user) {
    $db = Database::getInstance();
    
    // 使用getJsonInput函数读取请求体（更安全，避免重复读取问题）
    $input = getJsonInput();
    
    // 调试日志：记录接收到的输入数据
    error_log('[公告创建] 接收到的输入: ' . json_encode($input, JSON_UNESCAPED_UNICODE));
    
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $type = trim($input['type'] ?? 'normal');
    
    // 确保status是有效的数字（0或1），无效则默认为1（已发布）
    $status = 1;
    if (isset($input['status']) && $input['status'] !== null) {
        $parsedStatus = (int)$input['status'];
        if (in_array($parsedStatus, [0, 1], true)) {
            $status = $parsedStatus;
        }
    }
    
    // 调试日志：记录最终的status值
    error_log('[公告创建] 最终status值: ' . $status . ' (原始值: ' . ($input['status'] ?? '未设置') . ')');
    
    // 验证参数
    if (!$title) {
        jsonError('请输入公告标题', 400);
    }
    if (!$content) {
        jsonError('请输入公告内容', 400);
    }
    
    // 验证类型
    $validTypes = ['normal', 'important', 'urgent'];
    if (!in_array($type, $validTypes)) {
        $type = 'normal';
    }
    
    // 插入公告
    $result = $db->execute(
        "INSERT INTO announcements (title, content, type, status, admin_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$title, $content, $type, $status, $user['user_id'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
    );
    
    if ($result > 0) {
        $announcementId = $db->lastInsertId();
        
        // 记录管理员操作
        logAdminAction($user['user_id'], 'create_announcement', 'announcement:' . $announcementId, '发布公告: ' . $title);
        
        jsonSuccess([
            'id' => $announcementId,
            'title' => $title,
            'type' => $type,
            'status' => $status
        ], '公告发布成功');
    } else {
        jsonError('公告发布失败', 500);
    }
}

// ============================================================
// 更新公告（需要管理员权限）
// ============================================================
function handleAnnouncementUpdate($user) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $type = trim($input['type'] ?? '');
    $status = isset($input['status']) ? (int)$input['status'] : null;
    
    if ($id <= 0) {
        jsonError('无效的公告ID', 400);
    }
    
    // 检查公告是否存在
    $announcement = $db->fetchOne("SELECT * FROM announcements WHERE id = ?", [$id]);
    if (!$announcement) {
        jsonError('公告不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员的公告
    if (function_exists('checkContentPermission')) {
        checkContentPermission($user, $announcement['admin_id'], '公告');
    } else {
        // 兼容：如果checkContentPermission函数不存在（在admin.php中定义），手动检查
        if (function_exists('isModerator') && isModerator($user)) {
            $adminUser = $db->fetchOne("SELECT is_admin, username FROM users WHERE id = ?", [$announcement['admin_id']]);
            if ($adminUser && (int)$adminUser['is_admin'] === 2) {
                jsonError("副管理员不能操作主管理员（{$adminUser['username']}）创建的公告", 403);
            }
        }
    }
    
    // 构建更新字段
    $updateFields = [];
    $params = [];
    
    if ($title) {
        $updateFields[] = "title = ?";
        $params[] = $title;
    }
    if ($content) {
        $updateFields[] = "content = ?";
        $params[] = $content;
    }
    if ($type) {
        $validTypes = ['normal', 'important', 'urgent'];
        if (in_array($type, $validTypes)) {
            $updateFields[] = "type = ?";
            $params[] = $type;
        }
    }
    if ($status !== null) {
        if (in_array($status, [0, 1])) {
            $updateFields[] = "status = ?";
            $params[] = $status;
        }
    }
    
    if (empty($updateFields)) {
        jsonError('没有需要更新的字段', 400);
    }
    
    // 添加更新时间
    $updateFields[] = "updated_at = ?";
    $params[] = date('Y-m-d H:i:s');
    
    // 添加ID参数
    $params[] = $id;
    
    // 执行更新
    $result = $db->execute(
        "UPDATE announcements SET " . implode(', ', $updateFields) . " WHERE id = ?",
        $params
    );
    
    // 记录管理员操作
    logAdminAction($user['user_id'], 'update_announcement', 'announcement:' . $id, '更新公告: ' . ($title ?: $announcement['title']));
    
    jsonSuccess(null, '公告更新成功');
}

// ============================================================
// 删除公告（需要管理员权限）
// ============================================================
function handleAnnouncementDelete($user) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($id <= 0) {
        jsonError('无效的公告ID', 400);
    }
    
    // 检查公告是否存在
    $announcement = $db->fetchOne("SELECT * FROM announcements WHERE id = ?", [$id]);
    if (!$announcement) {
        jsonError('公告不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员的公告
    if (function_exists('isModerator') && isModerator($user)) {
        $adminUser = $db->fetchOne("SELECT is_admin, username FROM users WHERE id = ?", [$announcement['admin_id']]);
        if ($adminUser && (int)$adminUser['is_admin'] === 2) {
            jsonError("副管理员不能操作主管理员（{$adminUser['username']}）创建的公告", 403);
        }
    }
    
    // 执行删除
    $result = $db->execute("DELETE FROM announcements WHERE id = ?", [$id]);
    
    if ($result > 0) {
        // 记录管理员操作
        logAdminAction($user['user_id'], 'delete_announcement', 'announcement:' . $id, '删除公告: ' . $announcement['title']);
        
        jsonSuccess(null, '公告删除成功');
    } else {
        jsonError('公告删除失败', 500);
    }
}

// ============================================================
// 管理员公告列表（需要管理员权限）
// ============================================================
function handleAdminAnnouncementList($user) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    $status = getParam('status', '');
    $type = trim(getParam('type', ''));
    
    // 注意：所有列名必须加表别名a.，避免与users表的列名歧义
    $where = "WHERE 1=1";
    $params = [];
    
    // 按状态筛选
    if ($status !== '' && $status !== 'all') {
        $where .= " AND a.status = ?";
        $params[] = (int)$status;
    }
    
    // 按类型筛选
    if ($type && $type !== 'all') {
        $where .= " AND a.type = ?";
        $params[] = $type;
    }
    
    // 查询总数
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM announcements a $where", $params);
    
    // 查询列表（按创建时间倒序）
    // 注意：LIMIT和OFFSET必须直接拼接到SQL中，不能用参数绑定
    // 因为SQLite中如果参数是字符串类型，会导致查询返回空结果
    $sql = "SELECT a.*, u.username as admin_username, u.nickname as admin_nickname
            FROM announcements a
            LEFT JOIN users u ON a.admin_id = u.id
            $where
            ORDER BY a.created_at DESC
            LIMIT " . (int)$pageSize . " OFFSET " . (int)$offset;
    $announcements = $db->fetchAll($sql, $params);
    
    // 处理内容摘要
    foreach ($announcements as &$ann) {
        $ann['summary'] = mb_substr(strip_tags($ann['content']), 0, 100);
    }
    
    jsonSuccess([
        'list' => $announcements,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

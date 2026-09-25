<?php
/**
 * ============================================================
 * SecureVault 站点配置API - 分类、热门话题等公共配置
 * ============================================================
 * 提供分类列表、热门话题列表等公共数据接口
 * 以及管理员的分类、热门话题管理接口
 * ============================================================
 */

require_once 'config.php';
require_once 'db.php';
require_once 'helpers.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 处理CORS
setCorsHeaders();

// 获取请求动作
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 获取数据库实例
$db = Database::getInstance();

// ============================================================
// 公共接口（不需要登录）
// ============================================================

/**
 * 获取分类列表
 */
if ($action === 'categories') {
    $categories = $db->fetchAll(
        "SELECT id, name, icon, slug, sort_order 
         FROM categories 
         WHERE status = 1 
         ORDER BY sort_order ASC, id ASC"
    );
    jsonSuccess(['list' => $categories], '获取成功');
}

/**
 * 获取热门话题列表
 */
if ($action === 'hot_topics') {
    $topics = $db->fetchAll(
        "SELECT id, title, sort_order 
         FROM hot_topics 
         WHERE status = 1 
         ORDER BY sort_order ASC, id ASC"
    );
    jsonSuccess(['list' => $topics], '获取成功');
}

/**
 * 获取活跃用户列表（最近7天内登录过的用户，按最后登录时间倒序）
 */
if ($action === 'active_users') {
    $limit = isset($_GET['limit']) ? min(20, max(1, (int)$_GET['limit'])) : 10;
    $users = $db->fetchAll(
        "SELECT id, username, nickname, avatar, unique_id, last_login
         FROM users 
         WHERE status = 1 AND last_login IS NOT NULL AND last_login != ''
         ORDER BY last_login DESC 
         LIMIT ?",
        [$limit]
    );
    jsonSuccess(['list' => $users], '获取成功');
}

// ============================================================
// 管理员接口（需要登录且是管理员）
// ============================================================

// 验证管理员权限
$admin = authenticateAdmin();
if (!$admin) {
    jsonError('需要管理员权限', 403);
}

/**
 * 管理员：获取全部分类（包括禁用的）
 */
if ($action === 'admin_categories') {
    $categories = $db->fetchAll(
        "SELECT * FROM categories ORDER BY sort_order ASC, id ASC"
    );
    jsonSuccess(['list' => $categories, 'total' => count($categories)], '获取成功');
}

/**
 * 管理员：添加分类
 */
if ($action === 'category_add') {
    $input = getJsonInput();
    $name = $input['name'] ?? '';
    $icon = $input['icon'] ?? '';
    $slug = $input['slug'] ?? '';
    $sort_order = (int)($input['sort_order'] ?? 0);
    
    if (!$name) {
        jsonError('分类名称不能为空');
    }
    
    $db->query(
        "INSERT INTO categories (name, icon, slug, sort_order) VALUES (?, ?, ?, ?)",
        [$name, $icon, $slug, $sort_order]
    );
    
    jsonSuccess(['id' => $db->lastInsertId()], '添加成功');
}

/**
 * 管理员：更新分类
 */
if ($action === 'category_update') {
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);
    $name = $input['name'] ?? '';
    $icon = $input['icon'] ?? '';
    $slug = $input['slug'] ?? '';
    $sort_order = (int)($input['sort_order'] ?? 0);
    $status = isset($input['status']) ? (int)$input['status'] : null;
    
    if (!$id) {
        jsonError('分类ID不能为空');
    }
    
    $category = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
    if (!$category) {
        jsonError('分类不存在');
    }
    
    $fields = [];
    $params = [];
    
    if ($name !== '') { $fields[] = 'name = ?'; $params[] = $name; }
    if ($icon !== '') { $fields[] = 'icon = ?'; $params[] = $icon; }
    if ($slug !== '') { $fields[] = 'slug = ?'; $params[] = $slug; }
    if ($sort_order !== 0) { $fields[] = 'sort_order = ?'; $params[] = $sort_order; }
    if ($status !== null) { $fields[] = 'status = ?'; $params[] = $status; }
    
    if (empty($fields)) {
        jsonError('没有需要更新的字段');
    }
    
    $params[] = $id;
    $db->query("UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    
    jsonSuccess([], '更新成功');
}

/**
 * 管理员：删除分类
 */
if ($action === 'category_delete') {
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        jsonError('分类ID不能为空');
    }
    
    $db->query("DELETE FROM categories WHERE id = ?", [$id]);
    jsonSuccess([], '删除成功');
}

/**
 * 管理员：获取全部热门话题（包括禁用的）
 */
if ($action === 'admin_hot_topics') {
    $topics = $db->fetchAll(
        "SELECT * FROM hot_topics ORDER BY sort_order ASC, id ASC"
    );
    jsonSuccess(['list' => $topics, 'total' => count($topics)], '获取成功');
}

/**
 * 管理员：添加热门话题
 */
if ($action === 'hot_topic_add') {
    $input = getJsonInput();
    $title = $input['title'] ?? '';
    $sort_order = (int)($input['sort_order'] ?? 0);
    
    if (!$title) {
        jsonError('话题标题不能为空');
    }
    
    $db->query(
        "INSERT INTO hot_topics (title, sort_order) VALUES (?, ?)",
        [$title, $sort_order]
    );
    
    jsonSuccess(['id' => $db->lastInsertId()], '添加成功');
}

/**
 * 管理员：更新热门话题
 */
if ($action === 'hot_topic_update') {
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);
    $title = $input['title'] ?? '';
    $sort_order = (int)($input['sort_order'] ?? 0);
    $status = isset($input['status']) ? (int)$input['status'] : null;
    
    if (!$id) {
        jsonError('话题ID不能为空');
    }
    
    $topic = $db->fetchOne("SELECT * FROM hot_topics WHERE id = ?", [$id]);
    if (!$topic) {
        jsonError('话题不存在');
    }
    
    $fields = [];
    $params = [];
    
    if ($title !== '') { $fields[] = 'title = ?'; $params[] = $title; }
    if ($sort_order !== 0) { $fields[] = 'sort_order = ?'; $params[] = $sort_order; }
    if ($status !== null) { $fields[] = 'status = ?'; $params[] = $status; }
    
    if (empty($fields)) {
        jsonError('没有需要更新的字段');
    }
    
    $params[] = $id;
    $db->query("UPDATE hot_topics SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    
    jsonSuccess([], '更新成功');
}

/**
 * 管理员：删除热门话题
 */
if ($action === 'hot_topic_delete') {
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        jsonError('话题ID不能为空');
    }
    
    $db->query("DELETE FROM hot_topics WHERE id = ?", [$id]);
    jsonSuccess([], '删除成功');
}

// 如果没有匹配的动作
jsonError('未知的操作: ' . $action, 400);

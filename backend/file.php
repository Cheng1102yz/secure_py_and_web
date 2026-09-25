<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 文件/公告管理 API
 * ============================================================
 * 接口列表：
 *   GET  /file.php?action=list           文件列表（用户端，已发布）
 *   GET  /file.php?action=detail         文件详情
 *   POST /file.php?action=download       下载文件（增加下载次数）
 *   GET  /file.php?action=versions       版本历史
 *   POST /file.php?action=upload         上传文件（管理员）
 *   POST /file.php?action=update         更新文件（管理员）
 *   POST /file.php?action=delete         删除文件（管理员）
 *   GET  /file.php?action=admin_list     管理员文件列表
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 设置CORS头
setCorsHeaders();

// 获取操作类型
$action = getParam('action', '');

// 管理员接口需要验证
$adminActions = ['upload', 'update', 'delete', 'admin_list', 'upload_file', 'category_add', 'category_edit', 'category_delete'];
if (in_array($action, $adminActions)) {
    $admin = authenticateAdmin();
    if (!$admin) {
        jsonError('需要管理员权限', 403);
    }
} else {
    $admin = null;
}

// 根据操作类型分发
switch ($action) {
    case 'list':
        handleFileList();
        break;
    case 'detail':
        handleFileDetail();
        break;
    case 'download':
        handleFileDownload();
        break;
    case 'versions':
        handleFileVersions();
        break;
    case 'upload':
        handleFileUpload($admin);
        break;
    case 'update':
        handleFileUpdate($admin);
        break;
    case 'delete':
        handleFileDelete($admin);
        break;
    case 'admin_list':
        handleAdminFileList($admin);
        break;
    case 'categories':
        handleFileCategories();
        break;
    case 'upload_file':
        handleFileUploadReal($admin);
        break;
    case 'category_list':
        handleCategoryList($admin);
        break;
    case 'category_add':
        handleCategoryAdd($admin);
        break;
    case 'category_edit':
        handleCategoryEdit($admin);
        break;
    case 'category_delete':
        handleCategoryDelete($admin);
        break;
    case 'upload_image':
        handleUploadImage($admin);
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 文件列表（用户端）
// ============================================================
function handleFileList() {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 12)));
    $offset = ($page - 1) * $pageSize;
    $category = getParam('category', '');
    $app_key = getParam('app_key', '');
    $keyword = trim(getParam('keyword', ''));
    
    $where = "WHERE status = 1";
    $params = [];
    
    if ($category) {
        $where .= " AND category = ?";
        $params[] = $category;
    }
    if ($app_key) {
        $where .= " AND app_key = ?";
        $params[] = $app_key;
    }
    if ($keyword) {
        $where .= " AND (title LIKE ? OR description LIKE ?)";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM files $where", $params);
    
    // 简化查询，先只查files表，确保能返回数据
    $files = $db->fetchAll(
        "SELECT * FROM files $where ORDER BY created_at DESC LIMIT $pageSize OFFSET $offset",
        $params
    );
    
    // 补充用户信息
    foreach ($files as &$file) {
        if (!empty($file['created_by'])) {
            $user = $db->fetchOne("SELECT username, nickname FROM users WHERE id = ?", [$file['created_by']]);
            if ($user) {
                $file['username'] = $user['username'];
                $file['nickname'] = $user['nickname'];
            }
        }
    }
    
    // 格式化文件大小
    foreach ($files as &$file) {
        $file['size_formatted'] = formatFileSize($file['file_size']);
        $file['date_formatted'] = date('Y-m-d', strtotime($file['created_at']));
    }
    
    jsonSuccess([
        'list' => $files,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 文件详情
// ============================================================
function handleFileDetail() {
    $db = Database::getInstance();
    $fileId = (int)getParam('id', 0);
    
    if ($fileId <= 0) {
        jsonError('无效的文件ID', 400);
    }
    
    $file = $db->fetchOne(
        "SELECT f.*, u.username, u.nickname 
         FROM files f 
         LEFT JOIN users u ON f.created_by = u.id 
         WHERE f.id = ? AND f.status = 1",
        [$fileId]
    );
    
    if (!$file) {
        jsonError('文件不存在或已下架', 404);
    }
    
    $file['size_formatted'] = formatFileSize($file['file_size']);
    $file['date_formatted'] = date('Y-m-d H:i', strtotime($file['created_at']));
    
    jsonSuccess($file, '获取成功');
}

// ============================================================
// 下载文件（增加下载次数）
// ============================================================
function handleFileDownload() {
    $db = Database::getInstance();
    
    // 支持GET和POST两种方式获取文件ID
    $fileId = 0;
    if (isset($_GET['id'])) {
        $fileId = (int)$_GET['id'];
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $fileId = isset($input['id']) ? (int)$input['id'] : 0;
    }
    
    if ($fileId <= 0) {
        jsonError('无效的文件ID', 400);
    }
    
    $file = $db->fetchOne("SELECT id, file_url, file_name, file_size, status FROM files WHERE id = ?", [$fileId]);
    
    if (!$file || $file['status'] != 1) {
        jsonError('文件不存在或已下架', 404);
    }
    
    // 增加下载次数
    $db->execute("UPDATE files SET download_count = download_count + 1 WHERE id = ?", [$fileId]);
    
    // 直接输出文件内容，强制下载
    $filePath = __DIR__ . '/..' . $file['file_url'];
    
    if (!file_exists($filePath)) {
        jsonError('文件不存在', 404);
    }
    
    // 清除之前的输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 设置下载响应头
    $fileName = $file['file_name'];
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // 输出文件内容
    readfile($filePath);
    exit;
}

// ============================================================
// 版本历史
// ============================================================
function handleFileVersions() {
    $db = Database::getInstance();
    $appKey = trim(getParam('app_key', ''));
    
    if (empty($appKey)) {
        jsonError('请提供软件app_key', 400);
    }
    
    $versions = $db->fetchAll(
        "SELECT id, title, version, description, file_size, download_count, created_at, app_key, image_url
         FROM files 
         WHERE app_key = ? AND status = 1
         ORDER BY created_at DESC",
        [$appKey]
    );
    
    foreach ($versions as &$v) {
        $v['size_formatted'] = formatFileSize($v['file_size']);
        $v['date_formatted'] = date('Y-m-d', strtotime($v['created_at']));
    }
    
    jsonSuccess(['list' => $versions], '获取成功');
}

// ============================================================
// 上传文件（管理员）
// ============================================================
function handleFileUpload($admin) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $title = isset($input['title']) ? trim($input['title']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    $fileUrl = isset($input['file_url']) ? trim($input['file_url']) : '';
    $fileName = isset($input['file_name']) ? trim($input['file_name']) : '';
    $fileSize = isset($input['file_size']) ? (int)$input['file_size'] : 0;
    $version = isset($input['version']) ? trim($input['version']) : '1.0';
    $category = isset($input['category']) ? trim($input['category']) : 'software';
    $appKey = isset($input['app_key']) ? trim($input['app_key']) : '';
    $imageUrl = isset($input['image_url']) ? trim($input['image_url']) : '';
    
    if (empty($title)) {
        jsonError('文件标题不能为空', 400);
    }
    if (empty($fileUrl)) {
        jsonError('文件链接不能为空', 400);
    }
    if (empty($fileName)) {
        jsonError('文件名不能为空', 400);
    }
    
    $db->execute(
        "INSERT INTO files (title, description, file_url, file_name, file_size, version, category, app_key, image_url, status, download_count, created_by, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)",
        [$title, $description, $fileUrl, $fileName, $fileSize, $version, $category, $appKey, $imageUrl, $admin['user_id'], date('Y-m-d H:i:s')]
    );
    
    $fileId = $db->lastInsertId();
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'file_upload', 'file:' . $fileId, "上传文件: $title v$version");
    
    jsonSuccess(['file_id' => (int)$fileId], '上传成功');
}

// ============================================================
// 真正的文件上传（支持multipart/form-data）
// ============================================================
function handleFileUploadReal($admin) {
    $db = Database::getInstance();
    
    // 检查是否有文件上传
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = '文件上传失败';
        if (isset($_FILES['file'])) {
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = '文件大小超过限制';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = '文件只有部分被上传';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = '没有文件被上传';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMsg = '找不到临时文件夹';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMsg = '文件写入失败';
                    break;
            }
        }
        jsonError($errorMsg, 400);
    }
    
    $file = $_FILES['file'];
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $version = isset($_POST['version']) ? trim($_POST['version']) : '1.0';
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'software';
    $appKey = isset($_POST['app_key']) ? trim($_POST['app_key']) : '';
    
    // 处理软件图片标识上传
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageFile = $_FILES['image'];
        $imageExt = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
        $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];
        if (in_array($imageExt, $allowedImageExts)) {
            $imageUploadDir = __DIR__ . '/../uploads/software_images/';
            if (!is_dir($imageUploadDir)) {
                mkdir($imageUploadDir, 0755, true);
            }
            $imageUniqueName = date('Ymd_His') . '_' . uniqid() . '.' . $imageExt;
            $imageTargetPath = $imageUploadDir . $imageUniqueName;
            if (move_uploaded_file($imageFile['tmp_name'], $imageTargetPath)) {
                $imageUrl = '/uploads/software_images/' . $imageUniqueName;
            }
        }
    }
    
    // 验证文件
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    
    if (empty($title)) {
        $title = pathinfo($fileName, PATHINFO_FILENAME);
    }
    
    // 文件大小限制（500MB）
    $maxSize = 500 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        jsonError('文件大小不能超过500MB', 400);
    }
    
    // 安全检查：禁止上传危险的脚本文件
    // 注意：允许上传exe文件，因为下载中心需要提供软件下载
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $blockedExts = ['php', 'php3', 'php4', 'php5', 'phtml', 'bat', 'cmd', 'sh', 'js', 'html', 'htm'];
    if (in_array($ext, $blockedExts)) {
        jsonError('不允许上传此类型文件', 400);
    }
    
    // 创建上传目录
    $uploadDir = __DIR__ . '/../uploads/files/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 生成唯一文件名
    $uniqueName = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $uniqueName;
    
    // 移动上传文件
    if (!move_uploaded_file($fileTmp, $targetPath)) {
        jsonError('文件保存失败，请检查目录权限', 500);
    }
    
    // 生成文件URL
    $fileUrl = '/uploads/files/' . $uniqueName;
    
    // 获取分类ID
    if ($categoryId > 0) {
        $cat = $db->fetchOne("SELECT slug FROM file_categories WHERE id = ?", [$categoryId]);
        if ($cat) {
            $category = $cat['slug'];
        }
    }
    
    // 插入数据库（使用execute + lastInsertId，更可靠）
    try {
        $sql = "INSERT INTO files (title, description, file_url, file_name, file_size, version, category, category_id, app_key, image_url, status, download_count, created_by, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)";
        $params = [$title, $description, $fileUrl, $fileName, $fileSize, $version, $category, $categoryId, $appKey, $imageUrl, $admin['user_id'], date('Y-m-d H:i:s')];
        
        $insertResult = $db->execute($sql, $params);
        
        if (!$insertResult) {
            // 获取更详细的PDO错误信息
            $errorInfo = '';
            try {
                $pdo = $db->getPdo();
                if ($pdo) {
                    $errorArr = $pdo->errorInfo();
                    if ($errorArr && count($errorArr) >= 3) {
                        $errorInfo = ' | SQLSTATE: ' . $errorArr[0] . ' | 错误码: ' . $errorArr[1] . ' | 错误信息: ' . $errorArr[2];
                    }
                }
            } catch (Exception $e2) {
                // 忽略获取错误信息的异常
            }
            
            // 记录详细日志
            error_log('文件上传数据库插入失败!');
            error_log('SQL: ' . $sql);
            error_log('参数: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
            error_log('错误信息: ' . $errorInfo);
            
            jsonError('数据库插入失败' . $errorInfo, 500);
            return;
        }
        
        $fileId = $db->lastInsertId();
        
        if (!$fileId || $fileId <= 0) {
            jsonError('获取文件ID失败，插入可能未成功', 500);
            return;
        }
        
    } catch (Exception $e) {
        // 记录错误日志
        error_log('文件上传数据库插入异常: ' . $e->getMessage());
        error_log('SQL: INSERT INTO files ...');
        error_log('参数: title=' . $title . ', version=' . $version . ', category=' . $category . ', app_key=' . $appKey);
        error_log('追踪: ' . $e->getTraceAsString());
        
        jsonError('数据库插入异常: ' . $e->getMessage(), 500);
        return;
    }
    
    // 记录管理员操作日志
    logAdminAction($admin['user_id'], 'file_upload_real', 'file:' . $fileId, "上传文件: $title ($fileName, " . round($fileSize/1024/1024, 2) . "MB)");
    
    jsonSuccess([
        'file_id' => (int)$fileId,
        'file_url' => $fileUrl,
        'file_name' => $fileName,
        'file_size' => (int)$fileSize,
        'title' => $title
    ], '文件上传成功');
}

// ============================================================
// 更新文件（管理员）
// ============================================================
function handleFileUpdate($admin) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $fileId = isset($input['id']) ? (int)$input['id'] : 0;
    $title = isset($input['title']) ? trim($input['title']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    $version = isset($input['version']) ? trim($input['version']) : '';
    $category = isset($input['category']) ? trim($input['category']) : '';
    $status = isset($input['status']) ? (int)$input['status'] : null;
    
    if ($fileId <= 0) {
        jsonError('无效的文件ID', 400);
    }
    
    $file = $db->fetchOne("SELECT id, created_by FROM files WHERE id = ?", [$fileId]);
    if (!$file) {
        jsonError('文件不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员的文件
    if (function_exists('isModerator') && isModerator($admin)) {
        $creator = $db->fetchOne("SELECT is_admin, username FROM users WHERE id = ?", [$file['created_by']]);
        if ($creator && (int)$creator['is_admin'] === 2) {
            jsonError("副管理员不能操作主管理员（{$creator['username']}）上传的文件", 403);
        }
    }
    
    $updates = [];
    $params = [];
    
    if ($title) { $updates[] = "title = ?"; $params[] = $title; }
    if ($description !== '') { $updates[] = "description = ?"; $params[] = $description; }
    if ($version) { $updates[] = "version = ?"; $params[] = $version; }
    if ($category) { $updates[] = "category = ?"; $params[] = $category; }
    if ($appKey !== '') { $updates[] = "app_key = ?"; $params[] = $appKey; }
    if ($imageUrl !== '') { $updates[] = "image_url = ?"; $params[] = $imageUrl; }
    if ($status !== null) { $updates[] = "status = ?"; $params[] = $status; }
    
    if (empty($updates)) {
        jsonError('没有需要更新的内容', 400);
    }
    
    $params[] = $fileId;
    $db->execute("UPDATE files SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    
    logAdminAction($admin['user_id'], 'file_update', 'file:' . $fileId, "更新文件信息");
    
    jsonSuccess(null, '更新成功');
}

// ============================================================
// 删除文件（管理员）
// ============================================================
function handleFileDelete($admin) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $fileId = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($fileId <= 0) {
        jsonError('无效的文件ID', 400);
    }
    
    $file = $db->fetchOne("SELECT id, title, created_by FROM files WHERE id = ?", [$fileId]);
    if (!$file) {
        jsonError('文件不存在', 404);
    }
    
    // 【权限验证】副管理员不能操作主管理员的文件
    if (function_exists('isModerator') && isModerator($admin)) {
        $creator = $db->fetchOne("SELECT is_admin, username FROM users WHERE id = ?", [$file['created_by']]);
        if ($creator && (int)$creator['is_admin'] === 2) {
            jsonError("副管理员不能操作主管理员（{$creator['username']}）上传的文件", 403);
        }
    }
    
    // 软删除（设置status为0）
    $db->execute("UPDATE files SET status = 0 WHERE id = ?", [$fileId]);
    
    logAdminAction($admin['user_id'], 'file_delete', 'file:' . $fileId, "删除文件: {$file['title']}");
    
    jsonSuccess(null, '删除成功');
}

// ============================================================
// 管理员文件列表
// ============================================================
function handleAdminFileList($admin) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    $status = getParam('status', '');
    $category = getParam('category', '');
    
    // 注意：所有列名必须加表别名f.，避免与users表的列名歧义
    $where = "WHERE 1=1";
    $params = [];
    
    if ($status !== '') {
        $where .= " AND f.status = ?";
        $params[] = (int)$status;
    }
    if ($category) {
        $where .= " AND f.category = ?";
        $params[] = $category;
    }
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM files f $where", $params);
    
    $files = $db->fetchAll(
        "SELECT f.*, u.username, u.nickname 
         FROM files f 
         LEFT JOIN users u ON f.created_by = u.id 
         $where
         ORDER BY f.created_at DESC 
         LIMIT " . (int)$pageSize . " OFFSET " . (int)$offset,
        $params
    );
    
    foreach ($files as &$file) {
        $file['size_formatted'] = formatFileSize($file['file_size']);
    }
    
    jsonSuccess([
        'list' => $files,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 工具函数：格式化文件大小
// ============================================================
function formatFileSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

// ============================================================
// 文件分类管理
// ============================================================

/**
 * 获取所有分类（公共接口）
 */
function handleFileCategories() {
    $db = Database::getInstance();
    $categories = $db->fetchAll(
        "SELECT * FROM file_categories WHERE status = 1 ORDER BY sort_order ASC, id ASC"
    );
    jsonSuccess($categories, '获取成功');
}

/**
 * 管理员获取分类列表
 */
function handleCategoryList($admin) {
    $db = Database::getInstance();
    $categories = $db->fetchAll(
        "SELECT * FROM file_categories ORDER BY sort_order ASC, id ASC"
    );
    
    // 统计每个分类的文件数
    foreach ($categories as &$cat) {
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE category_id = ?", [$cat['id']]);
        $cat['file_count'] = (int)$count['count'];
    }
    
    jsonSuccess($categories, '获取成功');
}

/**
 * 添加分类
 */
function handleCategoryAdd($admin) {
    $db = Database::getInstance();
    $input = getJsonInput();
    
    $name = isset($input['name']) ? trim($input['name']) : '';
    $slug = isset($input['slug']) ? trim($input['slug']) : '';
    $icon = isset($input['icon']) ? trim($input['icon']) : '📦';
    $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
    
    if (empty($name)) {
        jsonError('分类名称不能为空');
    }
    if (empty($slug)) {
        jsonError('分类标识不能为空');
    }
    
    // 检查slug是否已存在
    $exists = $db->fetchOne("SELECT id FROM file_categories WHERE slug = ?", [$slug]);
    if ($exists) {
        jsonError('分类标识已存在');
    }
    
    $db->execute(
        "INSERT INTO file_categories (name, slug, icon, sort_order, status, created_at) 
         VALUES (?, ?, ?, ?, 1, ?)",
        [$name, $slug, $icon, $sortOrder, date('Y-m-d H:i:s')]
    );
    
    $categoryId = $db->lastInsertId();
    logAdminAction($admin['user_id'], 'category_add', 'category:' . $categoryId, "添加文件分类: $name");
    
    jsonSuccess(['id' => (int)$categoryId], '添加成功');
}

/**
 * 编辑分类
 */
function handleCategoryEdit($admin) {
    $db = Database::getInstance();
    $input = getJsonInput();
    
    $categoryId = isset($input['id']) ? (int)$input['id'] : 0;
    $name = isset($input['name']) ? trim($input['name']) : '';
    $slug = isset($input['slug']) ? trim($input['slug']) : '';
    $icon = isset($input['icon']) ? trim($input['icon']) : '';
    $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : null;
    $status = isset($input['status']) ? (int)$input['status'] : null;
    
    if (!$categoryId) {
        jsonError('分类ID不能为空');
    }
    
    $category = $db->fetchOne("SELECT * FROM file_categories WHERE id = ?", [$categoryId]);
    if (!$category) {
        jsonError('分类不存在');
    }
    
    $fields = [];
    $params = [];
    
    if ($name !== '') {
        $fields[] = 'name = ?';
        $params[] = $name;
    }
    if ($slug !== '') {
        // 检查slug是否被其他分类使用
        $exists = $db->fetchOne("SELECT id FROM file_categories WHERE slug = ? AND id != ?", [$slug, $categoryId]);
        if ($exists) {
            jsonError('分类标识已被其他分类使用');
        }
        $fields[] = 'slug = ?';
        $params[] = $slug;
    }
    if ($icon !== '') {
        $fields[] = 'icon = ?';
        $params[] = $icon;
    }
    if ($sortOrder !== null) {
        $fields[] = 'sort_order = ?';
        $params[] = $sortOrder;
    }
    if ($status !== null) {
        $fields[] = 'status = ?';
        $params[] = $status;
    }
    
    if (empty($fields)) {
        jsonError('没有需要更新的字段');
    }
    
    $params[] = $categoryId;
    $db->execute("UPDATE file_categories SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    
    logAdminAction($admin['user_id'], 'category_edit', 'category:' . $categoryId, "编辑文件分类 #$categoryId");
    
    jsonSuccess(null, '编辑成功');
}

/**
 * 删除分类
 */
function handleCategoryDelete($admin) {
    $db = Database::getInstance();
    $input = getJsonInput();
    
    $categoryId = isset($input['id']) ? (int)$input['id'] : 0;
    
    if (!$categoryId) {
        jsonError('分类ID不能为空');
    }
    
    $category = $db->fetchOne("SELECT * FROM file_categories WHERE id = ?", [$categoryId]);
    if (!$category) {
        jsonError('分类不存在');
    }
    
    // 检查是否有文件使用该分类
    $fileCount = $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE category_id = ?", [$categoryId]);
    if ($fileCount['count'] > 0) {
        jsonError('该分类下还有文件，无法删除。请先将文件移到其他分类。');
    }
    
    $db->execute("DELETE FROM file_categories WHERE id = ?", [$categoryId]);
    
    logAdminAction($admin['user_id'], 'category_delete', 'category:' . $categoryId, "删除文件分类: {$category['name']}");
    
    jsonSuccess(null, '删除成功');
}

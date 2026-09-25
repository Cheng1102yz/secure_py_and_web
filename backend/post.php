<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 帖子管理 API
 * ============================================================
 * 接口列表：
 *   GET  /post.php?action=list           帖子列表（已通过审核）
 *   GET  /post.php?action=detail         帖子详情
 *   POST /post.php?action=create         发布帖子（需登录，待审核）
 *   POST /post.php?action=like           点赞/取消点赞
 *   POST /post.php?action=comment        发表评论
 *   GET  /post.php?action=comments       评论列表
 *   POST /post.php?action=share          转发
 *   GET  /post.php?action=my_posts       我的帖子
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// ========== 辅助函数（必须在文件开头定义，避免条件函数未定义错误） ==========

/**
 * 格式化文件大小
 * @param int $bytes 字节数
 * @return string 格式化后的大小字符串
 */
function formatFileSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

// 设置CORS头
setCorsHeaders();

// 验证登录（部分接口需要）
$action = getParam('action', '');
$needLogin = in_array($action, ['create', 'like', 'comment', 'share', 'my_posts', 'my_likes', 'my_comments', 'delete_comment', 'upload_video', 'upload_image']);
// 对于列表和详情接口，尝试获取用户信息（如果已登录），但不强制要求登录
$optionalLogin = in_array($action, ['list', 'detail']);

if ($needLogin) {
    $user = authenticate();
    if (!$user) {
        jsonError('请先登录', 401);
    }
} elseif ($optionalLogin) {
    // 尝试获取用户信息，如果未登录则为null
    $user = authenticate();
} else {
    $user = null;
}

// 根据操作类型分发
switch ($action) {
    case 'list':
        handlePostList($user);
        break;
    case 'detail':
        handlePostDetail($user);
        break;
    case 'create':
        handleCreatePost($user);
        break;
    case 'like':
        handleLikePost($user);
        break;
    case 'comment':
        handleCommentPost($user);
        break;
    case 'comments':
        handleCommentList();
        break;
    case 'share':
        handleSharePost($user);
        break;
    case 'my_posts':
        handleMyPosts($user);
        break;
    case 'my_likes':
        handleMyLikes($user);
        break;
    case 'my_comments':
        handleMyComments($user);
        break;
    case 'delete_comment':
        handleDeleteComment($user);
        break;
    case 'upload_video':
        handleUploadVideo($user);
        break;
    case 'post_images':
        handlePostImages();
        break;
    case 'upload_image':
        handleUploadImage($user);
        break;
    case 'user_posts':
        handleUserPosts();
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 帖子列表（已通过审核）
// ============================================================
function handlePostList($user = null) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 10)));
    $offset = ($page - 1) * $pageSize;
    $category = trim(getParam('category', ''));
    $keyword = trim(getParam('keyword', ''));
    
    $where = "WHERE p.status = 1";
    $params = [];
    
    // 分类筛选
    if ($category && $category !== 'all') {
        // 先查询分类ID
        $cat = $db->fetchOne("SELECT id FROM categories WHERE slug = ? OR name = ?", [$category, $category]);
        if ($cat) {
            $where .= " AND p.category_id = ?";
            $params[] = (int)$cat['id'];
        }
    }
    
    if ($keyword) {
        $where .= " AND (p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }
    
    // 查询总数
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM posts p $where", $params);
    
    // 获取当前用户ID（如果已登录）
    $currentUserId = 0;
    if (isset($user) && $user && isset($user['user_id'])) {
        $currentUserId = (int)$user['user_id'];
    }
    
    // 查询列表（置顶优先，然后按创建时间倒序）
    // 【性能优化】列表页不查询images字段（base64图片数据很大），只查询has_images标记
    // 图片数据通过单独接口post_images懒加载
    $posts = $db->fetchAll(
        "SELECT p.id, p.user_id, p.title, p.content, p.status, p.category_id, p.created_at,
                p.like_count, p.comment_count, p.share_count, p.view_count, p.is_top, p.is_featured,
                p.video,
                CASE WHEN p.images IS NOT NULL AND p.images != '' AND p.images != '[]' THEN 1 ELSE 0 END as has_images,
                u.username, u.nickname, u.avatar, u.unique_id, u.is_admin,
                CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END as user_liked
         FROM posts p 
         LEFT JOIN users u ON p.user_id = u.id 
         LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
         $where
         ORDER BY CASE WHEN p.is_top = 1 OR p.is_top = '1' THEN 0 ELSE 1 END, p.created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge([$currentUserId], $params, [$pageSize, $offset])
    );
    
    // 处理数据（不再解码images字段，因为列表页不查询images）
    foreach ($posts as &$post) {
        // 内容摘要
        $post['summary'] = mb_substr(strip_tags($post['content']), 0, 100);
        // 确保user_liked是整数
        $post['user_liked'] = isset($post['user_liked']) ? (int)$post['user_liked'] : 0;
        // 确保计数字段是整数
        $post['like_count'] = (int)($post['like_count'] ?? 0);
        $post['comment_count'] = (int)($post['comment_count'] ?? 0);
        $post['share_count'] = (int)($post['share_count'] ?? 0);
        $post['view_count'] = (int)($post['view_count'] ?? 0);
        // 确保has_images是整数
        $post['has_images'] = isset($post['has_images']) ? (int)$post['has_images'] : 0;
        // 列表页不返回images字段，设置为空数组
        $post['images'] = [];
    }
    
    jsonSuccess([
        'list' => $posts,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 帖子详情
// ============================================================
function handlePostDetail($user = null) {
    $db = Database::getInstance();
    $postId = (int)getParam('id', 0);
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    
    // 获取当前用户ID（如果已登录）
    $currentUserId = 0;
    if (isset($user) && $user && isset($user['user_id'])) {
        $currentUserId = (int)$user['user_id'];
    }
    
    $post = $db->fetchOne(
        "SELECT p.*, u.username, u.nickname, u.avatar, u.unique_id, u.bio, u.is_admin,
                CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END as user_liked
         FROM posts p 
         LEFT JOIN users u ON p.user_id = u.id 
         LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
         WHERE p.id = ?",
        [$currentUserId, $postId]
    );
    
    if (!$post) {
        jsonError('帖子不存在', 404);
    }
    
    if ($post['status'] != 1) {
        jsonError('帖子尚未通过审核或已被驳回', 403);
    }
    
    // 增加浏览量
    $db->execute("UPDATE posts SET view_count = view_count + 1 WHERE id = ?", [$postId]);
    $post['view_count']++;
    
    // 处理图片
    if ($post['images']) {
        $post['images'] = json_decode($post['images'], true) ?: [];
    } else {
        $post['images'] = [];
    }
    
    jsonSuccess($post, '获取成功');
}

// ============================================================
// 发布帖子
// ============================================================
function handleCreatePost($user) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $title = isset($input['title']) ? trim($input['title']) : '';
    $content = isset($input['content']) ? trim($input['content']) : '';
    $images = isset($input['images']) ? $input['images'] : [];
    $video = isset($input['video']) ? trim($input['video']) : '';
    $categorySlug = isset($input['category']) ? trim($input['category']) : '';
    
    if (empty($content)) {
        jsonError('帖子内容不能为空', 400);
    }
    
    if (mb_strlen($content) > 5000) {
        jsonError('帖子内容不能超过5000字', 400);
    }
    
    if ($title && mb_strlen($title) > 100) {
        jsonError('标题不能超过100字', 400);
    }
    
    // 图片最多9张
    if (count($images) > 9) {
        jsonError('最多上传9张图片', 400);
    }
    
    // 查询分类ID
    $categoryId = 0;
    if ($categorySlug) {
        $cat = $db->fetchOne("SELECT id FROM categories WHERE slug = ? OR name = ?", [$categorySlug, $categorySlug]);
        if ($cat) {
            $categoryId = (int)$cat['id'];
        }
    }
    
    $imagesJson = json_encode($images);
    
    // 判断用户身份：管理员（主管理员is_admin=2，副管理员is_admin=1）发帖免审核，直接发布
    $isAdmin = isset($user['is_admin']) && (int)$user['is_admin'] >= 1;
    $postStatus = $isAdmin ? 1 : 0; // 1=已通过审核，0=待审核
    
    // 插入帖子
    // 使用insert方法，直接返回最后插入的ID
    $postId = $db->insert(
        "INSERT INTO posts (user_id, title, content, images, video, status, category_id, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$user['user_id'], $title, $content, $imagesJson, $video, $postStatus, $categoryId, date('Y-m-d H:i:s')]
    );
    
    // 检查是否插入成功
    if (!$postId || $postId == 0) {
        jsonError('帖子发布失败，请稍后重试', 500);
    }
    
    // 更新用户帖子数
    $db->execute("UPDATE users SET post_count = post_count + 1 WHERE id = ?", [$user['user_id']]);
    
    // 发送系统通知（管理员免审核，不需要发送审核中通知）
    if (!$isAdmin) {
        sendNotification($user['user_id'], 'post_pending', '帖子审核中', '您的帖子已提交，等待管理员审核通过后将展示在主页。', $postId);
    }
    
    if ($isAdmin) {
        jsonSuccess([
            'post_id' => (int)$postId,
            'status' => 1,
            'message' => '发布成功，已直接展示在主页'
        ], '发布成功，已直接展示');
    } else {
        jsonSuccess([
            'post_id' => (int)$postId,
            'status' => 0,
            'message' => '发布成功，等待管理员审核'
        ], '发布成功，等待审核');
    }
}

// ============================================================
// 点赞/取消点赞（彻底重写，使用严格并发控制）
// ============================================================
function handleLikePost($user) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    $userId = isset($user['user_id']) ? (int)$user['user_id'] : 0;
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    
    if ($userId <= 0) {
        jsonError('用户未登录或用户ID无效', 401);
    }
    
    // 检查帖子是否存在且已通过审核
    $post = $db->fetchOne("SELECT id, user_id, like_count FROM posts WHERE id = ? AND status = 1", [$postId]);
    if (!$post) {
        jsonError('帖子不存在或未通过审核', 404);
    }
    
    // 使用立即事务确保并发安全
    $db->execute("BEGIN IMMEDIATE TRANSACTION");
    
    try {
        // 检查是否已点赞（在事务内查询，确保一致性）
        $liked = $db->fetchOne(
            "SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?",
            [$postId, $userId]
        );
        
        $inserted = 0;
        $deleted = 0;
        
        if ($liked) {
            // 取消点赞：先删除记录，再更新计数
            $deleted = $db->execute("DELETE FROM post_likes WHERE id = ?", [(int)$liked['id']]);
            
            if ($deleted > 0) {
                // 只有删除成功才减少计数
                $db->execute("UPDATE posts SET like_count = CASE WHEN like_count > 0 THEN like_count - 1 ELSE 0 END WHERE id = ?", [$postId]);
            }
            $isLiked = false;
            $message = '已取消点赞';
        } else {
            // 点赞：先插入记录，再更新计数
            $inserted = $db->execute(
                "INSERT OR IGNORE INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, ?)",
                [$postId, $userId, date('Y-m-d H:i:s')]
            );
            
            if ($inserted > 0) {
                // 只有插入成功才增加计数
                $db->execute("UPDATE posts SET like_count = like_count + 1 WHERE id = ?", [$postId]);
                
                // 发送通知给帖子作者（如果不是自己）
                if ((int)$post['user_id'] !== $userId) {
                    $liker = $db->fetchOne("SELECT nickname, username FROM users WHERE id = ?", [$userId]);
                    sendNotification((int)$post['user_id'], 'post_liked', '收到新的点赞', ($liker['nickname'] ?: $liker['username']) . ' 赞了你的帖子', $postId);
                }
            }
            $isLiked = true;
            $message = '点赞成功';
        }
        
        // 获取最新的like_count（在事务内查询，确保一致性）
        $newCount = $db->fetchOne("SELECT like_count FROM posts WHERE id = ?", [$postId]);
        
        // 提交事务
        $db->execute("COMMIT");
        
        jsonSuccess([
            'liked' => $isLiked,
            'like_count' => (int)$newCount['like_count'],
            'user_id' => $userId,
            'post_id' => $postId,
            'inserted' => $inserted,
            'deleted' => $deleted
        ], $message);
        
    } catch (Exception $e) {
        // 回滚事务
        try {
            $db->execute("ROLLBACK");
        } catch (Exception $e2) {
            // 忽略回滚错误
        }
        jsonError('操作失败: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// 发表评论
// ============================================================
function handleCommentPost($user) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    $content = isset($input['content']) ? trim($input['content']) : '';
    $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : 0;
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    if (empty($content)) {
        jsonError('评论内容不能为空', 400);
    }
    if (mb_strlen($content) > 500) {
        jsonError('评论不能超过500字', 400);
    }
    
    // 检查帖子
    $post = $db->fetchOne("SELECT id, user_id FROM posts WHERE id = ? AND status = 1", [$postId]);
    if (!$post) {
        jsonError('帖子不存在或未通过审核', 404);
    }
    
    // 插入评论
    $db->execute(
        "INSERT INTO post_comments (post_id, user_id, content, parent_id, created_at) 
         VALUES (?, ?, ?, ?, ?)",
        [$postId, $user['user_id'], $content, $parentId, date('Y-m-d H:i:s')]
    ) ;
    
    $commentId = $db->lastInsertId();
    
    // 更新帖子评论数
    $db->execute("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?", [$postId]);
    
    // 发送通知
    if ($post['user_id'] != $user['user_id']) {
        $commenter = $db->fetchOne("SELECT nickname, username FROM users WHERE id = ?", [$user['user_id']]);
        sendNotification($post['user_id'], 'post_comment', '收到新评论', ($commenter['nickname'] ?: $commenter['username']) . ' 评论了你的帖子', $postId);
    }
    
    jsonSuccess([
        'comment_id' => (int)$commentId
    ], '评论成功');
}

// ============================================================
// 评论列表
// ============================================================
function handleCommentList() {
    $db = Database::getInstance();
    $postId = (int)getParam('post_id', 0);
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    
    $comments = $db->fetchAll(
        "SELECT c.*, u.username, u.nickname, u.avatar, u.unique_id
         FROM post_comments c 
         LEFT JOIN users u ON c.user_id = u.id 
         WHERE c.post_id = ? AND c.status = 1
         ORDER BY c.created_at DESC 
         LIMIT ? OFFSET ?",
        [$postId, $pageSize, $offset]
    );
    
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM post_comments WHERE post_id = ? AND status = 1",
        [$postId]
    );
    
    jsonSuccess([
        'list' => $comments,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 转发
// ============================================================
function handleSharePost($user) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    
    if ($postId <= 0) {
        jsonError('无效的帖子ID', 400);
    }
    
    $post = $db->fetchOne("SELECT id FROM posts WHERE id = ? AND status = 1", [$postId]);
    if (!$post) {
        jsonError('帖子不存在或未通过审核', 404);
    }
    
    $db->execute("UPDATE posts SET share_count = share_count + 1 WHERE id = ?", [$postId]);
    
    jsonSuccess(null, '转发成功');
}

// ============================================================
// 我的帖子
// ============================================================
function handleMyPosts($user) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 10)));
    $offset = ($page - 1) * $pageSize;
    $status = getParam('status', '');
    
    $where = "WHERE user_id = ?";
    $params = [$user['user_id']];
    
    if ($status !== '') {
        $where .= " AND status = ?";
        $params[] = (int)$status;
    }
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM posts $where", $params);
    
    $posts = $db->fetchAll(
        "SELECT * FROM posts $where ORDER BY is_top DESC, created_at DESC LIMIT ? OFFSET ?",
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
// 获取指定用户的帖子列表（公开，只返回已通过审核的帖子）
// ============================================================
function handleUserPosts() {
    $db = Database::getInstance();
    
    $userId = (int)getParam('user_id', 0);
    if ($userId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 10)));
    $offset = ($page - 1) * $pageSize;
    
    // 只查询状态为1（已通过审核）的帖子
    $where = "WHERE user_id = ? AND status = 1";
    $params = [$userId];
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM posts $where", $params);
    
    $posts = $db->fetchAll(
        "SELECT * FROM posts $where ORDER BY is_top DESC, created_at DESC LIMIT ? OFFSET ?",
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
// 用户点赞记录列表
// ============================================================
function handleMyLikes($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 10)));
    $offset = ($page - 1) * $pageSize;
    
    // 查询总数
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM post_likes WHERE user_id = ?",
        [$userId]
    );
    
    // 查询点赞的帖子列表（关联posts表和users表）
    $posts = $db->fetchAll(
        "SELECT p.*, u.username as author_username, u.nickname as author_nickname, u.avatar as author_avatar,
                pl.created_at as liked_at
         FROM post_likes pl
         JOIN posts p ON pl.post_id = p.id
         LEFT JOIN users u ON p.user_id = u.id
         WHERE pl.user_id = ? AND p.status = 1
         ORDER BY pl.created_at DESC
         LIMIT ? OFFSET ?",
        [$userId, $pageSize, $offset]
    );
    
    foreach ($posts as &$post) {
        if ($post['images']) {
            $post['images'] = json_decode($post['images'], true) ?: [];
        } else {
            $post['images'] = [];
        }
        $post['summary'] = mb_substr(strip_tags($post['content']), 0, 100);
        $post['user_liked'] = 1; // 用户已点赞
    }
    
    jsonSuccess([
        'list' => $posts,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 我的评论列表（包括回复他人的评论）
// ============================================================
function handleMyComments($user) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    $userId = (int)$user['user_id'];
    
    // 查询总数
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM post_comments c WHERE c.user_id = ? AND c.status = 1",
        [$userId]
    );
    
    // 查询评论列表，关联帖子信息
    // 注意：所有列名加表别名，避免歧义
    $comments = $db->fetchAll(
        "SELECT c.*, p.title as post_title, p.id as post_id, p.status as post_status
         FROM post_comments c
         LEFT JOIN posts p ON c.post_id = p.id
         WHERE c.user_id = ? AND c.status = 1
         ORDER BY c.created_at DESC
         LIMIT " . (int)$pageSize . " OFFSET " . (int)$offset,
        [$userId]
    );
    
    // 处理内容摘要
    foreach ($comments as &$comment) {
        $comment['content_summary'] = mb_substr($comment['content'], 0, 100);
        // 判断是否是回复（parent_id > 0）
        $comment['is_reply'] = $comment['parent_id'] > 0 ? 1 : 0;
        // 如果是回复，获取被回复的评论信息
        if ($comment['parent_id'] > 0) {
            $parentComment = $db->fetchOne(
                "SELECT c.content, u.nickname, u.username 
                 FROM post_comments c 
                 LEFT JOIN users u ON c.user_id = u.id 
                 WHERE c.id = ?",
                [$comment['parent_id']]
            );
            if ($parentComment) {
                $comment['reply_to_content'] = mb_substr($parentComment['content'], 0, 50);
                $comment['reply_to_nickname'] = $parentComment['nickname'] ?: $parentComment['username'];
            }
        }
    }
    
    jsonSuccess([
        'list' => $comments,
        'total' => (int)$total['count'],
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 删除自己的评论
// ============================================================
function handleDeleteComment($user) {
    $db = Database::getInstance();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $commentId = isset($input['comment_id']) ? (int)$input['comment_id'] : 0;
    
    if ($commentId <= 0) {
        jsonError('无效的评论ID', 400);
    }
    
    // 查找评论
    $comment = $db->fetchOne("SELECT * FROM post_comments WHERE id = ?", [$commentId]);
    if (!$comment) {
        jsonError('评论不存在', 404);
    }
    
    // 验证评论所有权
    if ((int)$comment['user_id'] !== (int)$user['user_id']) {
        jsonError('只能删除自己的评论', 403);
    }
    
    // 软删除（更新status为0），保留数据记录
    $result = $db->execute(
        "UPDATE post_comments SET status = 0 WHERE id = ?",
        [$commentId]
    );
    
    if ($result > 0) {
        // 更新帖子评论数（减1）
        $db->execute("UPDATE posts SET comment_count = MAX(comment_count - 1, 0) WHERE id = ?", [$comment['post_id']]);
        
        // 记录操作日志
        logAdminAction($user['user_id'], 'delete_comment', 'comment:' . $commentId, '删除评论: ' . mb_substr($comment['content'], 0, 50));
        
        jsonSuccess(null, '评论删除成功');
    } else {
        jsonError('评论删除失败', 500);
    }
}

// ============================================================
// 视频上传接口
// ============================================================
function handleUploadVideo($user) {
    // 检查是否有文件上传
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = '未接收到视频文件';
        if (isset($_FILES['video'])) {
            $errorCodes = [
                UPLOAD_ERR_INI_SIZE => '视频大小超过服务器限制',
                UPLOAD_ERR_FORM_SIZE => '视频大小超过表单限制',
                UPLOAD_ERR_PARTIAL => '视频上传不完整',
                UPLOAD_ERR_NO_FILE => '未选择视频文件',
                UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录不存在',
                UPLOAD_ERR_CANT_WRITE => '服务器写入失败',
                UPLOAD_ERR_EXTENSION => '服务器扩展阻止了上传'
            ];
            if (isset($errorCodes[$_FILES['video']['error']])) {
                $errorMsg = $errorCodes[$_FILES['video']['error']];
            }
        }
        jsonError($errorMsg, 400);
    }
    
    $videoFile = $_FILES['video'];
    
    // 验证文件大小（最大100MB）
    $maxSize = 100 * 1024 * 1024; // 100MB
    if ($videoFile['size'] > $maxSize) {
        jsonError('视频大小不能超过100MB', 400);
    }
    
    // 验证文件类型（不使用mime_content_type，因为需要fileinfo扩展，部分服务器未启用）
    // 改用文件扩展名 + 浏览器提供的MIME类型双重验证
    $allowedExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
    $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
    
    // 获取文件扩展名
    $originalName = $videoFile['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // 获取浏览器提供的MIME类型
    $browserMimeType = $videoFile['type'] ?? '';
    
    // 验证：扩展名或MIME类型任一符合即可（兼容性更好）
    $isValidExtension = in_array($extension, $allowedExtensions);
    $isValidMimeType = in_array($browserMimeType, $allowedMimeTypes);
    
    if (!$isValidExtension && !$isValidMimeType) {
        jsonError('不支持的视频格式，支持MP4、WebM、OGG、MOV、AVI、MKV等格式', 400);
    }
    
    // 确定最终的文件扩展名（优先使用原始文件扩展名）
    if (!$isValidExtension && $isValidMimeType) {
        // 扩展名不合法但MIME类型合法，根据MIME类型推断扩展名
        $mimeToExt = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv'
        ];
        if (isset($mimeToExt[$browserMimeType])) {
            $extension = $mimeToExt[$browserMimeType];
        }
    }
    
    // 最终使用的文件类型（用于返回给前端）
    $fileType = $browserMimeType ?: ('video/' . $extension);
    
    // 生成唯一文件名
    $userId = (int)$user['user_id'];
    $timestamp = date('Ymd_His');
    $random = substr(md5(uniqid() . $userId), 0, 8);
    $fileName = "video_{$userId}_{$timestamp}_{$random}.{$extension}";
    
    // 上传目录
    $uploadDir = __DIR__ . '/../uploads/videos';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filePath = $uploadDir . '/' . $fileName;
    
    // 移动上传文件
    if (!move_uploaded_file($videoFile['tmp_name'], $filePath)) {
        jsonError('视频保存失败，请重试', 500);
    }
    
    // 返回视频URL（相对路径，前端可以直接使用）
    $videoUrl = '/uploads/videos/' . $fileName;
    
    jsonSuccess([
        'url' => $videoUrl,
        'filename' => $fileName,
        'size' => $videoFile['size'],
        'type' => $fileType,
        'size_formatted' => formatFileSize($videoFile['size'])
    ], '视频上传成功');
}

// 辅助函数：格式化文件大小（已移到文件开头定义）

// ============================================================
// 批量获取帖子图片数据（用于列表页懒加载）
// ============================================================
function handlePostImages() {
    $db = Database::getInstance();
    
    // 支持单个ID或多个ID（逗号分隔）
    $idsParam = trim(getParam('ids', ''));
    if (empty($idsParam)) {
        jsonError('请提供帖子ID', 400);
    }
    
    // 解析ID列表
    $ids = array_filter(array_map('intval', explode(',', $idsParam)));
    if (empty($ids)) {
        jsonError('无效的帖子ID', 400);
    }
    
    // 最多一次查询20个帖子的图片
    $ids = array_slice($ids, 0, 20);
    
    // 构建IN查询
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // 查询图片数据
    $posts = $db->fetchAll(
        "SELECT id, images FROM posts WHERE id IN ($placeholders) AND images IS NOT NULL AND images != '' AND images != '[]'",
        $ids
    );
    
    // 处理结果
    $result = [];
    foreach ($posts as $post) {
        $images = json_decode($post['images'], true);
        if (is_array($images) && !empty($images)) {
            $result[$post['id']] = $images;
        }
    }
    
    jsonSuccess([
        'images' => $result
    ], '获取成功');
}

// ============================================================
// 批量图片上传接口（第二步深度优化：图片从base64改为文件存储）
// ============================================================
function handleUploadImage($user) {
    // 检查是否有文件上传
    if (!isset($_FILES['images'])) {
        jsonError('未接收到图片文件', 400);
    }
    
    $files = $_FILES['images'];
    $uploadedImages = [];
    $errors = [];
    
    // 上传目录
    $uploadDir = __DIR__ . '/../uploads/images';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 支持的图片类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // 处理多个文件上传
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        // 获取文件信息（支持多文件和单文件两种格式）
        if (is_array($files['name'])) {
            $fileName = $files['name'][$i];
            $fileTmp = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];
            $fileError = $files['error'][$i];
        } else {
            $fileName = $files['name'];
            $fileTmp = $files['tmp_name'];
            $fileSize = $files['size'];
            $fileType = $files['type'];
            $fileError = $files['error'];
        }
        
        // 检查上传错误
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "文件 {$fileName} 上传失败";
            continue;
        }
        
        // 验证文件大小
        if ($fileSize > $maxSize) {
            $errors[] = "文件 {$fileName} 超过5MB限制";
            continue;
        }
        
        // 验证文件类型（不使用mime_content_type，因为需要fileinfo扩展）
        // 改用文件扩展名 + 浏览器提供的MIME类型双重验证
        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        
        // 获取文件扩展名
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 验证：扩展名或MIME类型任一符合即可
        $isValidImageExt = in_array($fileExtension, $allowedImageExtensions);
        $isValidImageMime = in_array($fileType, $allowedImageMimeTypes);
        
        if (!$isValidImageExt && !$isValidImageMime) {
            $errors[] = "文件 {$fileName} 格式不支持";
            continue;
        }
        
        // 确定最终的文件扩展名和MIME类型
        $extension = 'jpg';
        $actualType = $fileType;
        
        if ($isValidImageExt) {
            // 使用原始文件扩展名（jpeg统一为jpg）
            $extension = ($fileExtension === 'jpeg') ? 'jpg' : $fileExtension;
        } elseif ($isValidImageMime) {
            // 根据MIME类型推断扩展名
            $mimeToImageExt = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/bmp' => 'bmp'
            ];
            if (isset($mimeToImageExt[$fileType])) {
                $extension = $mimeToImageExt[$fileType];
            }
        }
        
        // 确保actualType有值
        if (!$actualType) {
            $extToMime = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'bmp' => 'image/bmp'
            ];
            $actualType = $extToMime[$extension] ?? 'image/jpeg';
        }
        
        // 生成唯一文件名
        $userId = (int)$user['user_id'];
        $timestamp = date('Ymd_His');
        $random = substr(md5(uniqid() . $userId . $i), 0, 8);
        $newFileName = "img_{$userId}_{$timestamp}_{$random}.{$extension}";
        $filePath = $uploadDir . '/' . $newFileName;
        
        // 移动上传文件
        if (move_uploaded_file($fileTmp, $filePath)) {
            $imageUrl = '/uploads/images/' . $newFileName;
            $uploadedImages[] = [
                'url' => $imageUrl,
                'filename' => $newFileName,
                'size' => $fileSize,
                'type' => $actualType
            ];
        } else {
            $errors[] = "文件 {$fileName} 保存失败";
        }
    }
    
    // 返回结果
    if (empty($uploadedImages)) {
        jsonError('所有图片上传失败：' . implode('; ', $errors), 400);
    }
    
    jsonSuccess([
        'images' => $uploadedImages,
        'success_count' => count($uploadedImages),
        'errors' => $errors
    ], count($uploadedImages) . '张图片上传成功');
}

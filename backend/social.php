<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 社交功能 API
 * ============================================================
 * 接口列表（都需要登录）：
 *   POST /social.php?action=follow         关注/取消关注
 *   GET  /social.php?action=followers      粉丝列表
 *   GET  /social.php?action=following      关注列表
 *   POST /social.php?action=add_friend     添加好友
 *   POST /social.php?action=accept_friend  接受好友请求
 *   GET  /social.php?action=friends         好友列表
 *   GET  /social.php?action=friend_requests 好友请求列表
 *   GET  /social.php?action=messages        消息列表
 *   POST /social.php?action=send_message    发送消息
 *   GET  /social.php?action=notifications   通知列表
 *   POST /social.php?action=read_notification 标记通知已读
 *   GET  /social.php?action=user_profile    用户资料
 *   GET  /social.php?action=search_users    搜索用户
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
    case 'follow':
        handleFollow($user);
        break;
    case 'check_follow':
        handleCheckFollow($user);
        break;
    case 'unfollow':
        handleFollow($user);
        break;
    case 'followers':
        handleFollowers($user);
        break;
    case 'following':
        handleFollowing($user);
        break;
    case 'add_friend':
        handleAddFriend($user);
        break;
    case 'accept_friend':
        handleAcceptFriend($user);
        break;
    case 'friends':
        handleFriends($user);
        break;
    case 'friend_requests':
        handleFriendRequests($user);
        break;
    case 'messages':
        handleMessages($user);
        break;
    case 'send_message':
        handleSendMessage($user);
        break;
    case 'notifications':
        handleNotifications($user);
        break;
    case 'read_notification':
        handleReadNotification($user);
        break;
    case 'user_profile':
        handleUserProfile($user);
        break;
    case 'search_users':
        handleSearchUsers($user);
        break;
    case 'unread_count':
        handleUnreadCount($user);
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 关注/取消关注
// ============================================================
function handleFollow($user) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $targetId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    
    if ($targetId <= 0 || $targetId == $user['user_id']) {
        jsonError('无效的用户ID', 400);
    }
    
    $target = $db->fetchOne("SELECT id, username, nickname FROM users WHERE id = ?", [$targetId]);
    if (!$target) {
        jsonError('用户不存在', 404);
    }
    
    $followed = $db->fetchOne(
        "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?",
        [$user['user_id'], $targetId]
    );
    
    if ($followed) {
        $db->execute("DELETE FROM follows WHERE id = ?", [$followed['id']]);
        $db->execute("UPDATE users SET following_count = GREATEST(following_count - 1, 0) WHERE id = ?", [$user['user_id']]);
        $db->execute("UPDATE users SET follower_count = GREATEST(follower_count - 1, 0) WHERE id = ?", [$targetId]);
        $isFollowing = false;
    } else {
        $db->execute(
            "INSERT INTO follows (follower_id, following_id, created_at) VALUES (?, ?, ?)",
            [$user['user_id'], $targetId, date('Y-m-d H:i:s')]
        );
        $db->execute("UPDATE users SET following_count = following_count + 1 WHERE id = ?", [$user['user_id']]);
        $db->execute("UPDATE users SET follower_count = follower_count + 1 WHERE id = ?", [$targetId]);
        $isFollowing = true;
        
        // 发送通知
        sendNotification($targetId, 'new_follower', '新的粉丝', ($user['nickname'] ?: $user['username']) . ' 关注了你', $user['user_id']);
    }
    
    jsonSuccess(['following' => $isFollowing], $isFollowing ? '关注成功' : '已取消关注');
}

// ============================================================
// 检查关注状态
// ============================================================
function handleCheckFollow($user) {
    $db = Database::getInstance();
    $targetId = (int)getParam('user_id', 0);
    
    if ($targetId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    $followed = $db->fetchOne(
        "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?",
        [$user['user_id'], $targetId]
    );
    
    jsonSuccess([
        'is_following' => $followed ? true : false
    ], '获取成功');
}

// ============================================================
// 粉丝列表
// ============================================================
function handleFollowers($user) {
    $db = Database::getInstance();
    $userId = (int)getParam('user_id', $user['user_id']);
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    
    $followers = $db->fetchAll(
        "SELECT u.id, u.username, u.nickname, u.avatar, u.unique_id, u.bio, u.follower_count, u.following_count
         FROM follows f 
         JOIN users u ON f.follower_id = u.id 
         WHERE f.following_id = ? 
         ORDER BY f.created_at DESC 
         LIMIT ? OFFSET ?",
        [$userId, $pageSize, $offset]
    );
    
    // 检查是否互相关注
    foreach ($followers as &$f) {
        $isFollowing = $db->fetchOne(
            "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?",
            [$user['user_id'], $f['id']]
        );
        $f['is_following'] = $isFollowing ? true : false;
    }
    
    jsonSuccess(['list' => $followers], '获取成功');
}

// ============================================================
// 关注列表
// ============================================================
function handleFollowing($user) {
    $db = Database::getInstance();
    $userId = (int)getParam('user_id', $user['user_id']);
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    
    $following = $db->fetchAll(
        "SELECT u.id, u.username, u.nickname, u.avatar, u.unique_id, u.bio, u.follower_count, u.following_count
         FROM follows f 
         JOIN users u ON f.following_id = u.id 
         WHERE f.follower_id = ? 
         ORDER BY f.created_at DESC 
         LIMIT ? OFFSET ?",
        [$userId, $pageSize, $offset]
    );
    
    foreach ($following as &$f) {
        $f['is_following'] = true;
    }
    
    jsonSuccess(['list' => $following], '获取成功');
}

// ============================================================
// 添加好友
// ============================================================
function handleAddFriend($user) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $friendId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    
    if ($friendId <= 0 || $friendId == $user['user_id']) {
        jsonError('无效的用户ID', 400);
    }
    
    $friend = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$friendId]);
    if (!$friend) {
        jsonError('用户不存在', 404);
    }
    
    // 检查是否已经是好友
    $existing = $db->fetchOne(
        "SELECT id, status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)",
        [$user['user_id'], $friendId, $friendId, $user['user_id']]
    );
    
    if ($existing) {
        if ($existing['status'] == 1) {
            jsonError('你们已经是好友了', 400);
        } elseif ($existing['status'] == 0) {
            jsonError('好友请求已发送，等待对方确认', 400);
        }
    }
    
    $db->execute(
        "INSERT INTO friends (user_id, friend_id, status, created_at) VALUES (?, ?, 0, ?)",
        [$user['user_id'], $friendId, date('Y-m-d H:i:s')]
    );
    
    // 发送通知
    sendNotification($friendId, 'friend_request', '好友请求', ($user['nickname'] ?: $user['username']) . ' 请求添加你为好友', $user['user_id']);
    
    jsonSuccess(null, '好友请求已发送');
}

// ============================================================
// 接受好友请求
// ============================================================
function handleAcceptFriend($user) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;
    $accept = isset($input['accept']) ? (bool)$input['accept'] : true;
    
    if ($requestId <= 0) {
        jsonError('无效的请求ID', 400);
    }
    
    $request = $db->fetchOne(
        "SELECT * FROM friends WHERE id = ? AND friend_id = ? AND status = 0",
        [$requestId, $user['user_id']]
    );
    
    if (!$request) {
        jsonError('好友请求不存在或已处理', 404);
    }
    
    if ($accept) {
        $db->execute("UPDATE friends SET status = 1 WHERE id = ?", [$requestId]);
        // 发送通知
        sendNotification($request['user_id'], 'friend_accepted', '好友请求已通过', ($user['nickname'] ?: $user['username']) . ' 接受了你的好友请求', $user['user_id']);
        jsonSuccess(null, '已接受好友请求');
    } else {
        $db->execute("UPDATE friends SET status = 2 WHERE id = ?", [$requestId]);
        jsonSuccess(null, '已拒绝好友请求');
    }
}

// ============================================================
// 好友列表
// ============================================================
function handleFriends($user) {
    $db = Database::getInstance();
    
    $friends = $db->fetchAll(
        "SELECT u.id, u.username, u.nickname, u.avatar, u.unique_id, u.bio
         FROM friends f 
         JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
         WHERE ((f.user_id = ? OR f.friend_id = ?) AND f.status = 1)
         AND u.id != ?
         ORDER BY u.nickname",
        [$user['user_id'], $user['user_id'], $user['user_id']]
    );
    
    // 去重
    $unique = [];
    foreach ($friends as $f) {
        if (!isset($unique[$f['id']])) {
            $unique[$f['id']] = $f;
        }
    }
    
    jsonSuccess(['list' => array_values($unique)], '获取成功');
}

// ============================================================
// 好友请求列表
// ============================================================
function handleFriendRequests($user) {
    $db = Database::getInstance();
    
    $requests = $db->fetchAll(
        "SELECT f.id, f.created_at, u.id as user_id, u.username, u.nickname, u.avatar, u.unique_id
         FROM friends f 
         JOIN users u ON f.user_id = u.id 
         WHERE f.friend_id = ? AND f.status = 0
         ORDER BY f.created_at DESC",
        [$user['user_id']]
    );
    
    jsonSuccess(['list' => $requests], '获取成功');
}

// ============================================================
// 消息列表（按会话分组）
// ============================================================
function handleMessages($user) {
    $db = Database::getInstance();
    $type = getParam('type', ''); // 1=私聊, 2=群聊, 3=官方消息
    
    // 获取所有会话
    $conversations = [];
    
    // 私聊会话
    $privateMsgs = $db->fetchAll(
        "SELECT m.*, 
                CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as other_id
         FROM messages m 
         WHERE (m.sender_id = ? OR m.receiver_id = ?) AND m.type = 1
         ORDER BY m.created_at DESC",
        [$user['user_id'], $user['user_id'], $user['user_id']]
    );
    
    $privateConversations = [];
    foreach ($privateMsgs as $msg) {
        $otherId = $msg['other_id'];
        if (!isset($privateConversations[$otherId])) {
            $other = $db->fetchOne("SELECT id, username, nickname, avatar, unique_id FROM users WHERE id = ?", [$otherId]);
            $privateConversations[$otherId] = [
                'type' => 1,
                'user' => $other,
                'last_message' => $msg['content'],
                'last_time' => $msg['created_at'],
                'unread' => 0
            ];
        }
        if ($msg['receiver_id'] == $user['user_id'] && $msg['is_read'] == 0) {
            $privateConversations[$otherId]['unread']++;
        }
    }
    
    // 官方消息
    $officialMsgs = $db->fetchAll(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
        [$user['user_id']]
    );
    
    $officialUnread = count(array_filter($officialMsgs, function($m) { return $m['is_read'] == 0; }));
    
    $result = [
        'private' => array_values($privateConversations),
        'official' => [
            'count' => count($officialMsgs),
            'unread' => $officialUnread,
            'list' => $officialMsgs
        ],
        'groups' => [] // 群聊功能预留
    ];
    
    jsonSuccess($result, '获取成功');
}

// ============================================================
// 发送消息
// ============================================================
function handleSendMessage($user) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
    $content = isset($input['content']) ? trim($input['content']) : '';
    $type = isset($input['type']) ? (int)$input['type'] : 1;
    
    if ($receiverId <= 0) {
        jsonError('无效的接收者ID', 400);
    }
    if (empty($content)) {
        jsonError('消息内容不能为空', 400);
    }
    if (mb_strlen($content) > 1000) {
        jsonError('消息不能超过1000字', 400);
    }
    
    $receiver = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$receiverId]);
    if (!$receiver) {
        jsonError('接收者不存在', 404);
    }
    
    $db->execute(
        "INSERT INTO messages (sender_id, receiver_id, type, content, created_at) VALUES (?, ?, ?, ?, ?)",
        [$user['user_id'], $receiverId, $type, $content, date('Y-m-d H:i:s')]
    );
    
    $msgId = $db->lastInsertId();
    
    jsonSuccess(['message_id' => (int)$msgId], '发送成功');
}

// ============================================================
// 通知列表
// ============================================================
function handleNotifications($user) {
    $db = Database::getInstance();
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    $type = getParam('type', '');
    
    $where = "WHERE user_id = ?";
    $params = [$user['user_id']];
    
    if ($type) {
        $where .= " AND type = ?";
        $params[] = $type;
    }
    
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$pageSize, $offset])
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM notifications $where", $params);
    $unread = $db->fetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$user['user_id']]);
    
    jsonSuccess([
        'list' => $notifications,
        'total' => (int)$total['count'],
        'unread' => (int)$unread['count'],
        'page' => $page
    ], '获取成功');
}

// ============================================================
// 标记通知已读
// ============================================================
function handleReadNotification($user) {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = isset($input['id']) ? (int)$input['id'] : 0;
    $all = isset($input['all']) ? (bool)$input['all'] : false;
    
    if ($all) {
        $db->execute("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$user['user_id']]);
        jsonSuccess(null, '已全部标记为已读');
    } else {
        if ($notificationId <= 0) {
            jsonError('无效的通知ID', 400);
        }
        $db->execute("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$notificationId, $user['user_id']]);
        jsonSuccess(null, '已标记为已读');
    }
}

// ============================================================
// 用户资料
// ============================================================
function handleUserProfile($user) {
    $db = Database::getInstance();
    $userId = (int)getParam('user_id', $user['user_id']);
    
    $profile = $db->fetchOne(
        "SELECT id, username, nickname, avatar, unique_id, bio, gender, birthday, location, 
                follower_count, following_count, post_count, created_at, last_login
         FROM users WHERE id = ?",
        [$userId]
    );
    
    if (!$profile) {
        jsonError('用户不存在', 404);
    }
    
    // 检查关注状态
    $isFollowing = $db->fetchOne(
        "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?",
        [$user['user_id'], $userId]
    );
    $profile['is_following'] = $isFollowing ? true : false;
    
    // 检查好友状态
    $isFriend = $db->fetchOne(
        "SELECT id, status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)",
        [$user['user_id'], $userId, $userId, $user['user_id']]
    );
    $profile['friend_status'] = $isFriend ? (int)$isFriend['status'] : -1;
    
    jsonSuccess($profile, '获取成功');
}

// ============================================================
// 搜索用户
// ============================================================
function handleSearchUsers($user) {
    $db = Database::getInstance();
    $keyword = trim(getParam('keyword', ''));
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    $offset = ($page - 1) * $pageSize;
    
    if (empty($keyword)) {
        jsonError('搜索关键词不能为空', 400);
    }
    
    $users = $db->fetchAll(
        "SELECT id, username, nickname, avatar, unique_id, bio, follower_count, following_count
         FROM users 
         WHERE username LIKE ? OR nickname LIKE ? OR unique_id LIKE ?
         ORDER BY follower_count DESC
         LIMIT ? OFFSET ?",
        ["%$keyword%", "%$keyword%", "%$keyword%", $pageSize, $offset]
    );
    
    foreach ($users as &$u) {
        $isFollowing = $db->fetchOne(
            "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?",
            [$user['user_id'], $u['id']]
        );
        $u['is_following'] = $isFollowing ? true : false;
    }
    
    jsonSuccess(['list' => $users], '搜索成功');
}

// ============================================================
// 未读消息数量
// ============================================================
function handleUnreadCount($user) {
    $db = Database::getInstance();
    
    $unreadMessages = $db->fetchOne(
        "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0 AND type = 1",
        [$user['user_id']]
    );
    
    $unreadNotifications = $db->fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$user['user_id']]
    );
    
    $friendRequests = $db->fetchOne(
        "SELECT COUNT(*) as count FROM friends WHERE friend_id = ? AND status = 0",
        [$user['user_id']]
    );
    
    jsonSuccess([
        'messages' => (int)$unreadMessages['count'],
        'notifications' => (int)$unreadNotifications['count'],
        'friend_requests' => (int)$friendRequests['count'],
        'total' => (int)$unreadMessages['count'] + (int)$unreadNotifications['count'] + (int)$friendRequests['count']
    ], '获取成功');
}

<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 群聊 API
 * ============================================================
 * 接口列表：
 *   POST /group_chat.php?action=create       创建群聊
 *   GET  /group_chat.php?action=list         群聊列表
 *   GET  /group_chat.php?action=detail       群聊详情（群信息+成员列表）
 *   GET  /group_chat.php?action=messages     群消息列表
 *   POST /group_chat.php?action=send         发送群消息
 *   POST /group_chat.php?action=invite       邀请成员加入群聊
 *   POST /group_chat.php?action=leave        退出群聊
 *   POST /group_chat.php?action=dismiss      解散群聊（群主）
 *   POST /group_chat.php?action=update       更新群信息
 *   GET  /group_chat.php?action=members      群成员列表
 *   POST /group_chat.php?action=read         标记群消息已读
 *   GET  /group_chat.php?action=unread_count 群未读消息数
 * 
 * 角色说明：
 *   owner  - 群主（创建者，可解散群、管理成员）
 *   admin  - 群管理员（可邀请/移除成员）
 *   member - 普通成员
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 设置CORS头
setCorsHeaders();

// 获取操作类型
$action = getParam('action', '');

// 所有接口都需要登录
$user = authenticate();
if (!$user) {
    jsonError('请先登录', 401);
}

// 根据操作类型分发
switch ($action) {
    case 'create':
        handleCreateGroup($user);
        break;
    case 'list':
        handleGroupList($user);
        break;
    case 'detail':
        handleGroupDetail($user);
        break;
    case 'messages':
        handleGroupMessages($user);
        break;
    case 'send':
        handleSendGroupMessage($user);
        break;
    case 'invite':
        handleInviteMember($user);
        break;
    case 'leave':
        handleLeaveGroup($user);
        break;
    case 'dismiss':
        handleDismissGroup($user);
        break;
    case 'update':
        handleUpdateGroup($user);
        break;
    case 'members':
        handleGroupMembers($user);
        break;
    case 'read':
        handleReadGroupMessages($user);
        break;
    case 'unread_count':
        handleGroupUnreadCount($user);
        break;
    case 'kick_member':
        handleKickMember($user);
        break;
    case 'set_avatar':
        handleSetGroupAvatar($user);
        break;
    case 'set_role':
        handleSetMemberRole($user);
        break;
    case 'get_announcement':
        handleGetAnnouncement($user);
        break;
    case 'set_announcement':
        handleSetAnnouncement($user);
        break;
    case 'confirm_announcement':
        handleConfirmAnnouncement($user);
        break;
    case 'set_nickname':
        handleSetGroupNickname($user);
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 辅助函数：判断两个用户是否互为好友
// ============================================================
function areFriends($userId1, $userId2) {
    $db = Database::getInstance();
    // 检查friends表中是否有双向好友关系（status=1表示已接受）
    $friend = $db->fetchOne(
        "SELECT id FROM friends 
         WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) 
         AND status = 1",
        [$userId1, $userId2, $userId2, $userId1]
    );
    return $friend ? true : false;
}

// ============================================================
// 辅助函数：获取用户在群中的角色
// ============================================================
function getMemberRole($groupId, $userId) {
    $db = Database::getInstance();
    $member = $db->fetchOne(
        "SELECT role FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    return $member ? $member['role'] : null;
}

// ============================================================
// 辅助函数：获取群中管理员数量
// ============================================================
function getAdminCount($groupId) {
    $db = Database::getInstance();
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM chat_group_members WHERE group_id = ? AND role = 'admin'",
        [$groupId]
    );
    return (int)($result['count'] ?? 0);
}

// ============================================================
// 创建群聊
// ============================================================
function handleCreateGroup($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $name = isset($input['name']) ? trim($input['name']) : '';
    $memberIds = isset($input['member_ids']) ? $input['member_ids'] : [];
    $description = isset($input['description']) ? trim($input['description']) : '';
    
    // 验证群名称
    if (empty($name)) {
        jsonError('群名称不能为空', 400);
    }
    if (mb_strlen($name) > 50) {
        jsonError('群名称不能超过50字', 400);
    }
    
    // 验证成员数量（至少需要2人，包括创建者）
    if (count($memberIds) < 1) {
        jsonError('至少需要邀请1位成员创建群聊', 400);
    }
    if (count($memberIds) > 499) {
        jsonError('群成员最多500人', 400);
    }
    
    // 验证成员ID是否有效（排除自己）
    $validMemberIds = [];
    foreach ($memberIds as $mid) {
        $mid = (int)$mid;
        if ($mid > 0 && $mid !== $userId) {
            $validMemberIds[] = $mid;
        }
    }
    
    if (empty($validMemberIds)) {
        jsonError('请选择有效的成员', 400);
    }
    
    // 去重
    $validMemberIds = array_unique($validMemberIds);
    
    // 验证成员是否都存在
    $placeholders = implode(',', array_fill(0, count($validMemberIds), '?'));
    $existingUsers = $db->fetchAll("SELECT id FROM users WHERE id IN ($placeholders)", $validMemberIds);
    if (count($existingUsers) !== count($validMemberIds)) {
        jsonError('部分用户不存在', 400);
    }
    
    // 【需求9】验证所有被邀请成员都与创建者互为好友
    $nonFriends = [];
    foreach ($validMemberIds as $mid) {
        if (!areFriends($userId, $mid)) {
            $nonFriends[] = $mid;
        }
    }
    if (!empty($nonFriends)) {
        jsonError('只有互为好友才能发起群聊，部分用户与您不是好友关系', 400);
    }
    
    // 创建群聊
    $now = date('Y-m-d H:i:s');
    $groupId = $db->insert(
        "INSERT INTO chat_groups (name, owner_id, description, member_count, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$name, $userId, $description, count($validMemberIds) + 1, $now, $now]
    );
    
    if (!$groupId) {
        jsonError('创建群聊失败，请稍后重试', 500);
    }
    
    // 添加创建者为群主
    $db->execute(
        "INSERT INTO chat_group_members (group_id, user_id, role, joined_at, last_read_at)
         VALUES (?, ?, 'owner', ?, ?)",
        [$groupId, $userId, $now, $now]
    );
    
    // 添加邀请的成员
    foreach ($validMemberIds as $mid) {
        $db->execute(
            "INSERT INTO chat_group_members (group_id, user_id, role, joined_at, last_read_at)
             VALUES (?, ?, 'member', ?, ?)",
            [$groupId, $mid, $now, $now]
        );
    }
    
    // 发送系统消息
    $systemContent = "群聊创建成功，欢迎加入「{$name}」！";
    $db->execute(
        "INSERT INTO chat_group_messages (group_id, sender_id, content, type, created_at)
         VALUES (?, 0, ?, 'system', ?)",
        [$groupId, $systemContent, $now]
    );
    
    // 获取群信息
    $group = $db->fetchOne("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
    
    // 【需求1】发送通知给被邀请的成员（除了创建者）
    $creatorInfo = $db->fetchOne("SELECT nickname, username FROM users WHERE id = ?", [$userId]);
    $creatorName = $creatorInfo['nickname'] ?: $creatorInfo['username'];
    foreach ($validMemberIds as $mid) {
        sendNotification(
            $mid,
            'group_invite',
            '群聊邀请',
            "{$creatorName} 邀请你加入群聊「{$name}」",
            $groupId
        );
    }
    
    jsonSuccess([
        'group_id' => (int)$groupId,
        'group' => $group,
        'message' => '群聊创建成功'
    ], '群聊创建成功');
}

// ============================================================
// 群聊列表
// ============================================================
function handleGroupList($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    // 查询用户加入的所有群聊
    $groups = $db->fetchAll(
        "SELECT g.*, gm.role, gm.joined_at, gm.last_read_at
         FROM chat_groups g
         INNER JOIN chat_group_members gm ON g.id = gm.group_id
         WHERE gm.user_id = ?
         ORDER BY g.updated_at DESC",
        [$userId]
    );
    
    $result = [];
    foreach ($groups as $group) {
        // 查询最后一条消息
        $lastMessage = $db->fetchOne(
            "SELECT gm.*, u.nickname, u.username
             FROM chat_group_messages gm
             LEFT JOIN users u ON gm.sender_id = u.id
             WHERE gm.group_id = ?
             ORDER BY gm.created_at DESC
             LIMIT 1",
            [$group['id']]
        );
        
        // 查询未读消息数
        $unread = $db->fetchOne(
            "SELECT COUNT(*) as count 
             FROM chat_group_messages 
             WHERE group_id = ? AND created_at > ?",
            [$group['id'], $group['last_read_at']]
        );
        
        // 查询成员数量
        $memberCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM chat_group_members WHERE group_id = ?",
            [$group['id']]
        );
        
        $result[] = [
            'group_id' => (int)$group['id'],
            'name' => $group['name'],
            'avatar' => $group['avatar'],
            'owner_id' => (int)$group['owner_id'],
            'description' => $group['description'],
            'member_count' => (int)($memberCount['count'] ?? 0),
            'role' => $group['role'],
            'last_message' => $lastMessage ? [
                'id' => (int)$lastMessage['id'],
                'sender_id' => (int)$lastMessage['sender_id'],
                'sender_name' => $lastMessage['nickname'] ?: $lastMessage['username'] ?: '系统',
                'content' => $lastMessage['content'],
                'type' => $lastMessage['type'],
                'created_at' => $lastMessage['created_at']
            ] : null,
            'unread_count' => (int)($unread['count'] ?? 0),
            'last_read_at' => $group['last_read_at'],
            'created_at' => $group['created_at'],
            'updated_at' => $group['updated_at']
        ];
    }
    
    jsonSuccess([
        'list' => $result,
        'total' => count($result)
    ], '获取成功');
}

// ============================================================
// 群聊详情（群信息+成员列表）
// ============================================================
function handleGroupDetail($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    $groupId = (int)getParam('group_id', 0);
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 获取群信息
    $group = $db->fetchOne("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
    if (!$group) {
        jsonError('群聊不存在', 404);
    }
    
    // 获取成员列表
    $members = $db->fetchAll(
        "SELECT gm.*, u.username, u.nickname, u.avatar, u.unique_id, u.is_admin
         FROM chat_group_members gm
         LEFT JOIN users u ON gm.user_id = u.id
         WHERE gm.group_id = ?
         ORDER BY CASE gm.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END, gm.joined_at ASC",
        [$groupId]
    );
    
    $memberList = [];
    foreach ($members as $m) {
        // 群昵称：如果设置了群昵称则使用群昵称，否则使用用户昵称
        $groupNickname = !empty($m['nickname']) ? $m['nickname'] : null;
        $displayName = !empty($m['nickname']) ? $m['nickname'] : ($m['nickname'] ?: $m['username']);
        
        $memberList[] = [
            'user_id' => (int)$m['user_id'],
            'username' => $m['username'],
            'nickname' => $m['nickname'], // 用户原始昵称
            'group_nickname' => $groupNickname, // 群昵称
            'display_name' => $displayName, // 显示名称（群昵称优先）
            'avatar' => $m['avatar'],
            'unique_id' => $m['unique_id'],
            'is_admin' => (int)$m['is_admin'],
            'role' => $m['role'],
            'joined_at' => $m['joined_at']
        ];
    }
    
    // 检查当前用户是否已确认最新群公告
    $isAnnouncementConfirmed = false;
    if ($group['announcement_updated_at']) {
        $readRecord = $db->fetchOne(
            "SELECT read_at FROM chat_group_announcement_read WHERE group_id = ? AND user_id = ?",
            [$groupId, $userId]
        );
        if ($readRecord && strtotime($readRecord['read_at']) >= strtotime($group['announcement_updated_at'])) {
            $isAnnouncementConfirmed = true;
        }
    }
    
    jsonSuccess([
        'group' => [
            'group_id' => (int)$group['id'],
            'name' => $group['name'],
            'avatar' => $group['avatar'],
            'owner_id' => (int)$group['owner_id'],
            'description' => $group['description'],
            'announcement' => $group['announcement'] ?: '',
            'announcement_updated_at' => $group['announcement_updated_at'],
            'member_count' => count($memberList),
            'created_at' => $group['created_at'],
            'updated_at' => $group['updated_at']
        ],
        'my_role' => $member['role'],
        'my_group_nickname' => !empty($member['nickname']) ? $member['nickname'] : null,
        'announcement_confirmed' => $isAnnouncementConfirmed,
        'members' => $memberList
    ], '获取成功');
}

// ============================================================
// 群消息列表
// ============================================================
function handleGroupMessages($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    $groupId = (int)getParam('group_id', 0);
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 查询消息（倒序，最新的在前面）
    $offset = ($page - 1) * $pageSize;
    $messages = $db->fetchAll(
        "SELECT m.*, u.username, u.nickname, u.avatar, u.unique_id, u.is_admin
         FROM chat_group_messages m
         LEFT JOIN users u ON m.sender_id = u.id
         WHERE m.group_id = ?
         ORDER BY m.created_at DESC
         LIMIT ? OFFSET ?",
        [$groupId, $pageSize, $offset]
    );
    
    // 反转数组，让旧的消息在前面
    $messages = array_reverse($messages);
    
    // 标记为已读
    $now = date('Y-m-d H:i:s');
    $db->execute(
        "UPDATE chat_group_members SET last_read_at = ? WHERE group_id = ? AND user_id = ?",
        [$now, $groupId, $userId]
    );
    
    // 更新群的最后活跃时间
    $db->execute(
        "UPDATE chat_groups SET updated_at = ? WHERE id = ?",
        [$now, $groupId]
    );
    
    jsonSuccess([
        'messages' => $messages,
        'page' => $page,
        'page_size' => $pageSize,
        'group_id' => $groupId
    ], '获取成功');
}

// ============================================================
// 发送群消息
// ============================================================
function handleSendGroupMessage($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $content = isset($input['content']) ? trim($input['content']) : '';
    $type = isset($input['type']) ? trim($input['type']) : 'text';
    
    // 验证
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    if (empty($content)) {
        jsonError('消息内容不能为空', 400);
    }
    
    $contentLength = function_exists('mb_strlen') ? mb_strlen($content) : strlen($content);
    if ($contentLength > 2000) {
        jsonError('消息内容不能超过2000字', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 验证群是否存在
    $group = $db->fetchOne("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
    if (!$group) {
        jsonError('群聊不存在', 404);
    }
    
    // 插入消息
    $now = date('Y-m-d H:i:s');
    $messageId = $db->insert(
        "INSERT INTO chat_group_messages (group_id, sender_id, content, type, created_at)
         VALUES (?, ?, ?, ?, ?)",
        [$groupId, $userId, $content, $type, $now]
    );
    
    if (!$messageId) {
        jsonError('发送失败，请稍后重试', 500);
    }
    
    // 更新群的最后活跃时间
    $db->execute(
        "UPDATE chat_groups SET updated_at = ? WHERE id = ?",
        [$now, $groupId]
    );
    
    // 获取发送者信息
    $sender = $db->fetchOne(
        "SELECT id, username, nickname, avatar, unique_id, is_admin FROM users WHERE id = ?",
        [$userId]
    );
    
    // 返回消息
    $message = [
        'id' => (int)$messageId,
        'group_id' => $groupId,
        'sender_id' => $userId,
        'content' => $content,
        'type' => $type,
        'created_at' => $now,
        'username' => $sender['username'],
        'nickname' => $sender['nickname'],
        'avatar' => $sender['avatar'],
        'unique_id' => $sender['unique_id'],
        'is_admin' => (int)$sender['is_admin']
    ];
    
    jsonSuccess([
        'message' => $message,
        'group_id' => $groupId
    ], '发送成功');
}

// ============================================================
// 邀请成员加入群聊
// ============================================================
function handleInviteMember($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $memberIds = isset($input['member_ids']) ? $input['member_ids'] : [];
    
    // 验证
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    if (empty($memberIds)) {
        jsonError('请选择要邀请的成员', 400);
    }
    
    // 验证用户是否在群里，且有权限邀请（群主或管理员）
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    if (!in_array($member['role'], ['owner', 'admin'])) {
        jsonError('只有群主或管理员可以邀请成员', 403);
    }
    
    // 验证群是否存在
    $group = $db->fetchOne("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
    if (!$group) {
        jsonError('群聊不存在', 404);
    }
    
    // 处理成员ID
    $validMemberIds = [];
    foreach ($memberIds as $mid) {
        $mid = (int)$mid;
        if ($mid > 0 && $mid !== $userId) {
            $validMemberIds[] = $mid;
        }
    }
    $validMemberIds = array_unique($validMemberIds);
    
    if (empty($validMemberIds)) {
        jsonError('请选择有效的成员', 400);
    }
    
    // 检查是否已经在群里
    $placeholders = implode(',', array_fill(0, count($validMemberIds), '?'));
    $existingMembers = $db->fetchAll(
        "SELECT user_id FROM chat_group_members WHERE group_id = ? AND user_id IN ($placeholders)",
        array_merge([$groupId], $validMemberIds)
    );
    $existingMemberIds = array_column($existingMembers, 'user_id');
    
    $newMemberIds = array_diff($validMemberIds, $existingMemberIds);
    
    if (empty($newMemberIds)) {
        jsonError('这些用户已经在群里了', 400);
    }
    
    // 验证用户是否存在
    $placeholders = implode(',', array_fill(0, count($newMemberIds), '?'));
    $users = $db->fetchAll("SELECT id, nickname FROM users WHERE id IN ($placeholders)", $newMemberIds);
    if (count($users) !== count($newMemberIds)) {
        jsonError('部分用户不存在', 400);
    }
    
    // 【需求9】验证所有被邀请成员都与邀请者互为好友
    $nonFriends = [];
    foreach ($newMemberIds as $mid) {
        if (!areFriends($userId, $mid)) {
            $nonFriends[] = $mid;
        }
    }
    if (!empty($nonFriends)) {
        jsonError('只有互为好友才能邀请进群，部分用户与您不是好友关系', 400);
    }
    
    // 添加成员
    $now = date('Y-m-d H:i:s');
    $addedNames = [];
    foreach ($newMemberIds as $mid) {
        $db->execute(
            "INSERT INTO chat_group_members (group_id, user_id, role, joined_at, last_read_at)
             VALUES (?, ?, 'member', ?, ?)",
            [$groupId, $mid, $now, $now]
        );
        $userInfo = $db->fetchOne("SELECT nickname FROM users WHERE id = ?", [$mid]);
        $addedNames[] = $userInfo['nickname'] ?: '用户' . $mid;
    }
    
    // 更新成员数量
    $memberCount = $db->fetchOne("SELECT COUNT(*) as count FROM chat_group_members WHERE group_id = ?", [$groupId]);
    $db->execute("UPDATE chat_groups SET member_count = ?, updated_at = ? WHERE id = ?", [$memberCount['count'], $now, $groupId]);
    
    // 发送系统消息
    $systemContent = implode('、', $addedNames) . ' 加入了群聊';
    $db->execute(
        "INSERT INTO chat_group_messages (group_id, sender_id, content, type, created_at)
         VALUES (?, 0, ?, 'system', ?)",
        [$groupId, $systemContent, $now]
    );
    
    jsonSuccess([
        'group_id' => $groupId,
        'added_count' => count($newMemberIds),
        'added_names' => $addedNames,
        'member_count' => (int)$memberCount['count']
    ], '邀请成功');
}

// ============================================================
// 退出群聊
// ============================================================
function handleLeaveGroup($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 群主不能直接退出，需要先转让或解散
    if ($member['role'] === 'owner') {
        jsonError('群主不能退出群聊，请先转让群主或解散群聊', 400);
    }
    
    // 获取用户信息
    $userInfo = $db->fetchOne("SELECT nickname FROM users WHERE id = ?", [$userId]);
    $userName = $userInfo['nickname'] ?: '用户' . $userId;
    
    // 移除成员
    $db->execute(
        "DELETE FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    
    // 更新成员数量
    $now = date('Y-m-d H:i:s');
    $memberCount = $db->fetchOne("SELECT COUNT(*) as count FROM chat_group_members WHERE group_id = ?", [$groupId]);
    $db->execute("UPDATE chat_groups SET member_count = ?, updated_at = ? WHERE id = ?", [$memberCount['count'], $now, $groupId]);
    
    // 发送系统消息
    $systemContent = "{$userName} 退出了群聊";
    $db->execute(
        "INSERT INTO chat_group_messages (group_id, sender_id, content, type, created_at)
         VALUES (?, 0, ?, 'system', ?)",
        [$groupId, $systemContent, $now]
    );
    
    jsonSuccess(null, '已退出群聊');
}

// ============================================================
// 解散群聊（群主）
// ============================================================
function handleDismissGroup($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证群是否存在
    $group = $db->fetchOne("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
    if (!$group) {
        jsonError('群聊不存在', 404);
    }
    
    // 验证是否是群主
    if ((int)$group['owner_id'] !== $userId) {
        jsonError('只有群主可以解散群聊', 403);
    }
    
    // 删除群成员
    $db->execute("DELETE FROM chat_group_members WHERE group_id = ?", [$groupId]);
    
    // 删除群消息
    $db->execute("DELETE FROM chat_group_messages WHERE group_id = ?", [$groupId]);
    
    // 删除群
    $db->execute("DELETE FROM chat_groups WHERE id = ?", [$groupId]);
    
    jsonSuccess(null, '群聊已解散');
}

// ============================================================
// 更新群信息
// ============================================================
function handleUpdateGroup($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $name = isset($input['name']) ? trim($input['name']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    $avatar = isset($input['avatar']) ? trim($input['avatar']) : '';
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里，且有权限修改（群主或管理员）
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    if (!in_array($member['role'], ['owner', 'admin'])) {
        jsonError('只有群主或管理员可以修改群信息', 403);
    }
    
    // 验证群是否存在
    $group = $db->fetchOne("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
    if (!$group) {
        jsonError('群聊不存在', 404);
    }
    
    // 构建更新字段
    $updateFields = [];
    $params = [];
    
    if ($name) {
        if (mb_strlen($name) > 50) {
            jsonError('群名称不能超过50字', 400);
        }
        $updateFields[] = "name = ?";
        $params[] = $name;
    }
    
    if ($description !== '') {
        if (mb_strlen($description) > 200) {
            jsonError('群描述不能超过200字', 400);
        }
        $updateFields[] = "description = ?";
        $params[] = $description;
    }
    
    if ($avatar !== '') {
        $updateFields[] = "avatar = ?";
        $params[] = $avatar;
    }
    
    if (empty($updateFields)) {
        jsonError('没有需要更新的内容', 400);
    }
    
    $updateFields[] = "updated_at = ?";
    $params[] = date('Y-m-d H:i:s');
    $params[] = $groupId;
    
    $db->execute(
        "UPDATE chat_groups SET " . implode(', ', $updateFields) . " WHERE id = ?",
        $params
    );
    
    // 获取更新后的群信息
    $updatedGroup = $db->fetchOne("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
    
    jsonSuccess([
        'group' => $updatedGroup
    ], '更新成功');
}

// ============================================================
// 群成员列表
// ============================================================
function handleGroupMembers($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    $groupId = (int)getParam('group_id', 0);
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 获取成员列表
    $members = $db->fetchAll(
        "SELECT gm.*, u.username, u.nickname, u.avatar, u.unique_id, u.is_admin
         FROM chat_group_members gm
         LEFT JOIN users u ON gm.user_id = u.id
         WHERE gm.group_id = ?
         ORDER BY CASE gm.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END, gm.joined_at ASC",
        [$groupId]
    );
    
    $memberList = [];
    foreach ($members as $m) {
        $memberList[] = [
            'user_id' => (int)$m['user_id'],
            'username' => $m['username'],
            'nickname' => $m['nickname'],
            'avatar' => $m['avatar'],
            'unique_id' => $m['unique_id'],
            'is_admin' => (int)$m['is_admin'],
            'role' => $m['role'],
            'joined_at' => $m['joined_at']
        ];
    }
    
    jsonSuccess([
        'members' => $memberList,
        'total' => count($memberList),
        'my_role' => $member['role']
    ], '获取成功');
}

// ============================================================
// 标记群消息已读
// ============================================================
function handleReadGroupMessages($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 更新最后阅读时间
    $now = date('Y-m-d H:i:s');
    $db->execute(
        "UPDATE chat_group_members SET last_read_at = ? WHERE group_id = ? AND user_id = ?",
        [$now, $groupId, $userId]
    );
    
    jsonSuccess(null, '已标记为已读');
}

// ============================================================
// 群未读消息数
// ============================================================
function handleGroupUnreadCount($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    // 查询用户加入的所有群聊
    $groups = $db->fetchAll(
        "SELECT gm.group_id, gm.last_read_at
         FROM chat_group_members gm
         WHERE gm.user_id = ?",
        [$userId]
    );
    
    $totalUnread = 0;
    $byGroup = [];
    
    foreach ($groups as $group) {
        $unread = $db->fetchOne(
            "SELECT COUNT(*) as count 
             FROM chat_group_messages 
             WHERE group_id = ? AND created_at > ?",
            [$group['group_id'], $group['last_read_at']]
        );
        
        $count = (int)($unread['count'] ?? 0);
        $totalUnread += $count;
        $byGroup[] = [
            'group_id' => (int)$group['group_id'],
            'unread_count' => $count
        ];
    }
    
    jsonSuccess([
        'total' => $totalUnread,
        'by_group' => $byGroup
    ], '获取成功');
}

// ============================================================
// 踢出成员（群主和管理员可以踢出普通成员，管理员不能操作群主）
// ============================================================
function handleKickMember($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    
    // 验证
    if ($groupId <= 0 || $targetUserId <= 0) {
        jsonError('无效的群ID或用户ID', 400);
    }
    if ($targetUserId === $userId) {
        jsonError('不能踢出自己', 400);
    }
    
    // 验证当前用户是否在群里
    $myMember = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$myMember) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 验证权限：只有群主和管理员可以踢出成员
    if (!in_array($myMember['role'], ['owner', 'admin'])) {
        jsonError('只有群主或管理员可以踢出成员', 403);
    }
    
    // 验证目标用户是否在群里
    $targetMember = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $targetUserId]
    );
    if (!$targetMember) {
        jsonError('该用户不在此群聊中', 404);
    }
    
    // 【需求5】管理员不能操作群主
    if ($targetMember['role'] === 'owner') {
        jsonError('不能踢出群主', 403);
    }
    
    // 【需求5】管理员不能操作其他管理员
    if ($myMember['role'] === 'admin' && $targetMember['role'] === 'admin') {
        jsonError('管理员不能踢出其他管理员', 403);
    }
    
    // 踢出成员
    $db->execute(
        "DELETE FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $targetUserId]
    );
    
    // 更新成员数量
    $memberCount = $db->fetchOne("SELECT COUNT(*) as count FROM chat_group_members WHERE group_id = ?", [$groupId]);
    $db->execute("UPDATE chat_groups SET member_count = ?, updated_at = ? WHERE id = ?", [$memberCount['count'], date('Y-m-d H:i:s'), $groupId]);
    
    // 获取被踢出用户信息
    $targetUser = $db->fetchOne("SELECT nickname, username FROM users WHERE id = ?", [$targetUserId]);
    $targetName = $targetUser['nickname'] ?: $targetUser['username'];
    
    // 发送系统消息
    $systemContent = "{$targetName} 被移出群聊";
    $db->execute(
        "INSERT INTO chat_group_messages (group_id, sender_id, content, type, created_at)
         VALUES (?, 0, ?, 'system', ?)",
        [$groupId, $systemContent, date('Y-m-d H:i:s')]
    );
    
    // 发送通知给被踢出的用户
    $group = $db->fetchOne("SELECT name FROM chat_groups WHERE id = ?", [$groupId]);
    sendNotification(
        $targetUserId,
        'group_kick',
        '被移出群聊',
        "你被移出群聊「{$group['name']}」",
        $groupId
    );
    
    jsonSuccess(null, '已踢出成员');
}

// ============================================================
// 设置群头像（群主和管理员）
// ============================================================
function handleSetGroupAvatar($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $avatar = isset($input['avatar']) ? trim($input['avatar']) : '';
    
    // 验证
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    if (empty($avatar)) {
        jsonError('头像不能为空', 400);
    }
    
    // 验证当前用户是否在群里
    $myMember = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$myMember) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 验证权限：只有群主和管理员可以设置群头像
    if (!in_array($myMember['role'], ['owner', 'admin'])) {
        jsonError('只有群主或管理员可以设置群头像', 403);
    }
    
    // 更新群头像
    $db->execute(
        "UPDATE chat_groups SET avatar = ?, updated_at = ? WHERE id = ?",
        [$avatar, date('Y-m-d H:i:s'), $groupId]
    );
    
    jsonSuccess(['avatar' => $avatar], '群头像设置成功');
}

// ============================================================
// 任命/解职管理员（仅群主，一个群最多两个管理员）
// ============================================================
function handleSetMemberRole($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $role = isset($input['role']) ? trim($input['role']) : 'member'; // admin 或 member
    
    // 验证
    if ($groupId <= 0 || $targetUserId <= 0) {
        jsonError('无效的群ID或用户ID', 400);
    }
    if (!in_array($role, ['admin', 'member'])) {
        jsonError('无效的角色', 400);
    }
    if ($targetUserId === $userId) {
        jsonError('不能修改自己的角色', 400);
    }
    
    // 验证当前用户是否是群主
    $myMember = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$myMember || $myMember['role'] !== 'owner') {
        jsonError('只有群主可以任命或解职管理员', 403);
    }
    
    // 验证目标用户是否在群里
    $targetMember = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $targetUserId]
    );
    if (!$targetMember) {
        jsonError('该用户不在此群聊中', 404);
    }
    
    // 【需求5】不能操作群主
    if ($targetMember['role'] === 'owner') {
        jsonError('不能修改群主的角色', 403);
    }
    
    // 【需求5】一个群最多两个管理员
    if ($role === 'admin') {
        $adminCount = getAdminCount($groupId);
        if ($adminCount >= 2) {
            jsonError('一个群最多只能有两个管理员', 400);
        }
    }
    
    // 更新角色
    $db->execute(
        "UPDATE chat_group_members SET role = ? WHERE group_id = ? AND user_id = ?",
        [$role, $groupId, $targetUserId]
    );
    
    // 获取目标用户信息
    $targetUser = $db->fetchOne("SELECT nickname, username FROM users WHERE id = ?", [$targetUserId]);
    $targetName = $targetUser['nickname'] ?: $targetUser['username'];
    
    // 发送系统消息
    $roleText = $role === 'admin' ? '被任命为管理员' : '被解除管理员职务';
    $systemContent = "{$targetName} {$roleText}";
    $db->execute(
        "INSERT INTO chat_group_messages (group_id, sender_id, content, type, created_at)
         VALUES (?, 0, ?, 'system', ?)",
        [$groupId, $systemContent, date('Y-m-d H:i:s')]
    );
    
    jsonSuccess(['role' => $role], $role === 'admin' ? '已任命为管理员' : '已解除管理员职务');
}

// ============================================================
// 获取群公告（返回公告内容和当前用户是否已确认）
// ============================================================
function handleGetAnnouncement($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    $groupId = (int)getParam('group_id', 0);
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 获取群公告
    $group = $db->fetchOne("SELECT announcement, announcement_updated_at FROM chat_groups WHERE id = ?", [$groupId]);
    
    // 检查用户是否已确认最新公告
    $isConfirmed = false;
    if ($group['announcement_updated_at']) {
        $readRecord = $db->fetchOne(
            "SELECT read_at FROM chat_group_announcement_read WHERE group_id = ? AND user_id = ?",
            [$groupId, $userId]
        );
        if ($readRecord && strtotime($readRecord['read_at']) >= strtotime($group['announcement_updated_at'])) {
            $isConfirmed = true;
        }
    }
    
    jsonSuccess([
        'announcement' => $group['announcement'] ?: '',
        'updated_at' => $group['announcement_updated_at'],
        'is_confirmed' => $isConfirmed
    ], '获取成功');
}

// ============================================================
// 设置群公告（群主和管理员）
// ============================================================
function handleSetAnnouncement($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $announcement = isset($input['announcement']) ? trim($input['announcement']) : '';
    
    // 验证
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    if (mb_strlen($announcement) > 500) {
        jsonError('群公告不能超过500字', 400);
    }
    
    // 验证当前用户是否在群里
    $myMember = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$myMember) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 验证权限：只有群主和管理员可以设置群公告
    if (!in_array($myMember['role'], ['owner', 'admin'])) {
        jsonError('只有群主或管理员可以设置群公告', 403);
    }
    
    // 更新群公告
    $now = date('Y-m-d H:i:s');
    $db->execute(
        "UPDATE chat_groups SET announcement = ?, announcement_updated_at = ?, updated_at = ? WHERE id = ?",
        [$announcement, $now, $now, $groupId]
    );
    
    // 清除所有成员的公告确认记录（因为公告更新了，需要重新确认）
    $db->execute("DELETE FROM chat_group_announcement_read WHERE group_id = ?", [$groupId]);
    
    // 发送系统消息
    $systemContent = "群公告已更新，请查看";
    $db->execute(
        "INSERT INTO chat_group_messages (group_id, sender_id, content, type, created_at)
         VALUES (?, 0, ?, 'system', ?)",
        [$groupId, $systemContent, $now]
    );
    
    jsonSuccess([
        'announcement' => $announcement,
        'updated_at' => $now
    ], '群公告已更新');
}

// ============================================================
// 确认群公告（成员确认已阅读）
// ============================================================
function handleConfirmAnnouncement($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 记录确认（使用REPLACE INTO避免重复）
    $now = date('Y-m-d H:i:s');
    $db->execute(
        "INSERT OR REPLACE INTO chat_group_announcement_read (group_id, user_id, read_at)
         VALUES (?, ?, ?)",
        [$groupId, $userId, $now]
    );
    
    jsonSuccess(null, '已确认群公告');
}

// ============================================================
// 修改自己在群中的昵称
// ============================================================
function handleSetGroupNickname($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $nickname = isset($input['nickname']) ? trim($input['nickname']) : '';
    
    // 验证
    if ($groupId <= 0) {
        jsonError('无效的群ID', 400);
    }
    if (mb_strlen($nickname) > 20) {
        jsonError('群昵称不能超过20字', 400);
    }
    
    // 验证用户是否在群里
    $member = $db->fetchOne(
        "SELECT * FROM chat_group_members WHERE group_id = ? AND user_id = ?",
        [$groupId, $userId]
    );
    if (!$member) {
        jsonError('你不在此群聊中', 403);
    }
    
    // 更新群昵称
    $db->execute(
        "UPDATE chat_group_members SET nickname = ? WHERE group_id = ? AND user_id = ?",
        [$nickname, $groupId, $userId]
    );
    
    jsonSuccess(['nickname' => $nickname], '群昵称修改成功');
}

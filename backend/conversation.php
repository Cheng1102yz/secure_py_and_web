<?php
/**
 * ============================================================
 * SecureVault 社交平台 - 私聊 API
 * ============================================================
 * 接口列表：
 *   GET  /conversation.php?action=list           会话列表
 *   GET  /conversation.php?action=detail         会话详情（消息列表）
 *   POST /conversation.php?action=send           发送消息
 *   POST /conversation.php?action=read           标记消息已读
 *   GET  /conversation.php?action=unread_count   未读消息数
 *   POST /conversation.php?action=create_or_get  创建或获取会话
 * 
 * 陌生人限制（参考抖音）：
 *   - 非好友之间只能发文本消息
 *   - 陌生人只能发一条消息（接收方回复后可继续）
 *   - 好友之间无限制
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
    case 'list':
        handleConversationList($user);
        break;
    case 'detail':
        handleConversationDetail($user);
        break;
    case 'send':
        handleSendMessage($user);
        break;
    case 'read':
        handleReadMessage($user);
        break;
    case 'unread_count':
        handleUnreadCount($user);
        break;
    case 'create_or_get':
        handleCreateOrGetConversation($user);
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 辅助函数：判断两个用户是否是好友（互相关注）
// ============================================================
function isFriends($userId1, $userId2) {
    $db = Database::getInstance();
    // 检查是否互相关注（注意：表名是 follows，不是 user_follows）
    $follow1 = $db->fetchOne("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?", [$userId1, $userId2]);
    $follow2 = $db->fetchOne("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?", [$userId2, $userId1]);
    return $follow1 && $follow2;
}

// ============================================================
// 辅助函数：检查陌生人是否已经发过消息
// ============================================================
function strangerHasSentMessage($conversationId, $senderId) {
    $db = Database::getInstance();
    // 检查该用户是否已经在这个会话中发过消息
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM messages WHERE conversation_id = ? AND sender_id = ?", [$conversationId, $senderId]);
    return $count && (int)$count['count'] > 0;
}

// ============================================================
// 辅助函数：检查接收方是否已经回复过
// ============================================================
function receiverHasReplied($conversationId, $receiverId) {
    $db = Database::getInstance();
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM messages WHERE conversation_id = ? AND sender_id = ?", [$conversationId, $receiverId]);
    return $count && (int)$count['count'] > 0;
}

// ============================================================
// 会话列表
// ============================================================
function handleConversationList($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    // 查询用户的所有会话
    $conversations = $db->fetchAll(
        "SELECT c.*, 
                u1.id as user1_id, u1.username as user1_username, u1.nickname as user1_nickname, u1.avatar as user1_avatar,
                u2.id as user2_id, u2.username as user2_username, u2.nickname as user2_nickname, u2.avatar as user2_avatar
         FROM conversations c
         LEFT JOIN users u1 ON c.user1_id = u1.id
         LEFT JOIN users u2 ON c.user2_id = u2.id
         WHERE c.user1_id = ? OR c.user2_id = ?
         ORDER BY c.last_message_time DESC",
        [$userId, $userId]
    );
    
    // 处理会话数据
    $result = [];
    foreach ($conversations as $conv) {
        // 确定对方用户
        $otherUser = ($conv['user1_id'] == $userId) ? [
            'id' => $conv['user2_id'],
            'username' => $conv['user2_username'],
            'nickname' => $conv['user2_nickname'],
            'avatar' => $conv['user2_avatar']
        ] : [
            'id' => $conv['user1_id'],
            'username' => $conv['user1_username'],
            'nickname' => $conv['user1_nickname'],
            'avatar' => $conv['user1_avatar']
        ];
        
        // 查询未读消息数
        $unread = $db->fetchOne(
            "SELECT COUNT(*) as count FROM messages WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0",
            [$conv['id'], $userId]
        );
        
        // 判断是否是好友
        $isFriend = isFriends($userId, $otherUser['id']);
        
        $result[] = [
            'conversation_id' => $conv['id'],
            'other_user' => $otherUser,
            'last_message' => $conv['last_message'],
            'last_message_time' => $conv['last_message_time'],
            'last_sender_id' => $conv['last_sender_id'],
            'unread_count' => (int)($unread['count'] ?? 0),
            'is_friend' => $isFriend ? 1 : 0
        ];
    }
    
    jsonSuccess([
        'list' => $result,
        'total' => count($result)
    ], '获取成功');
}

// ============================================================
// 会话详情（消息列表）
// ============================================================
function handleConversationDetail($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    $conversationId = (int)getParam('conversation_id', 0);
    $page = max(1, (int)getParam('page', 1));
    $pageSize = min(50, max(1, (int)getParam('page_size', 20)));
    
    if ($conversationId <= 0) {
        jsonError('无效的会话ID', 400);
    }
    
    // 验证用户是否属于这个会话
    $conv = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    if (!$conv) {
        jsonError('会话不存在', 404);
    }
    if ($conv['user1_id'] != $userId && $conv['user2_id'] != $userId) {
        jsonError('无权访问此会话', 403);
    }
    
    // 查询消息（倒序，最新的在前面）
    $offset = ($page - 1) * $pageSize;
    $messages = $db->fetchAll(
        "SELECT m.*, u.nickname, u.username, u.avatar
         FROM messages m
         LEFT JOIN users u ON m.sender_id = u.id
         WHERE m.conversation_id = ?
         ORDER BY m.created_at DESC
         LIMIT ? OFFSET ?",
        [$conversationId, $pageSize, $offset]
    );
    
    // 反转数组，让旧的消息在前面
    $messages = array_reverse($messages);
    
    // 查询对方用户信息
    $otherUserId = ($conv['user1_id'] == $userId) ? $conv['user2_id'] : $conv['user1_id'];
    $otherUser = $db->fetchOne("SELECT id, username, nickname, avatar FROM users WHERE id = ?", [$otherUserId]);
    
    // 判断是否是好友
    $isFriend = isFriends($userId, $otherUserId);
    
    // 检查陌生人是否已经发过消息（用于前端限制输入）
    $strangerCanSend = true;
    if (!$isFriend) {
        // 如果当前用户已经发过消息，且对方没有回复，则不能再发
        if (strangerHasSentMessage($conversationId, $userId) && !receiverHasReplied($conversationId, $otherUserId)) {
            $strangerCanSend = false;
        }
    }
    
    jsonSuccess([
        'messages' => $messages,
        'other_user' => $otherUser,
        'is_friend' => $isFriend ? 1 : 0,
        'stranger_can_send' => $strangerCanSend ? 1 : 0,
        'page' => $page,
        'page_size' => $pageSize
    ], '获取成功');
}

// ============================================================
// 发送消息
// ============================================================
function handleSendMessage($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
    $receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
    $content = isset($input['content']) ? trim($input['content']) : '';
    $type = isset($input['type']) ? trim($input['type']) : 'text';
    
    // 验证内容
    if (empty($content)) {
        jsonError('消息内容不能为空', 400);
    }
    
    // 使用兼容的长度检查（避免mbstring扩展问题）
    $contentLength = function_exists('mb_strlen') ? mb_strlen($content) : strlen($content);
    if ($contentLength > 2000) {
        jsonError('消息内容不能超过2000字', 400);
    }
    
    // 获取或创建会话
    if ($conversationId > 0) {
        $conv = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
        if (!$conv) {
            jsonError('会话不存在', 404);
        }
        // 确定接收方
        $receiverId = ($conv['user1_id'] == $userId) ? $conv['user2_id'] : $conv['user1_id'];
    } elseif ($receiverId > 0) {
        // 检查接收方是否存在
        $receiver = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$receiverId]);
        if (!$receiver) {
            jsonError('接收方不存在', 404);
        }
        if ($receiverId == $userId) {
            jsonError('不能给自己发消息', 400);
        }
        // 获取或创建会话
        $conv = getOrCreateConversation($userId, $receiverId);
        if (!$conv) {
            jsonError('创建会话失败，请稍后重试', 500);
        }
        $conversationId = $conv['id'];
    } else {
        jsonError('请提供会话ID或接收方ID', 400);
    }
    
    // 判断是否是好友
    $isFriend = isFriends($userId, $receiverId);
    
    // 陌生人限制
    if (!$isFriend) {
        // 1. 只能发文本消息
        if ($type !== 'text') {
            jsonError('陌生人之间只能发送文本消息，加好友后可发送图片、文件等', 403);
        }
        
        // 2. 只能发一条消息（除非接收方已经回复）
        if (strangerHasSentMessage($conversationId, $userId) && !receiverHasReplied($conversationId, $receiverId)) {
            jsonError('您已经发送了一条消息，等待对方回复后可继续发送，或加为好友无限制聊天', 403);
        }
    }
    
    // 插入消息
    $messageId = $db->insert(
        "INSERT INTO messages (conversation_id, sender_id, receiver_id, content, type, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, ?)",
        [$conversationId, $userId, $receiverId, $content, $type, date('Y-m-d H:i:s')]
    );
    
    if (!$messageId) {
        // 插入失败，检查数据库表是否存在
        $messagesTable = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='messages'");
        $conversationsTable = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='conversations'");
        
        $errorMsg = '消息发送失败';
        if (!$messagesTable) {
            $errorMsg = '数据库表 messages 不存在，请运行数据库迁移脚本';
        } elseif (!$conversationsTable) {
            $errorMsg = '数据库表 conversations 不存在，请运行数据库迁移脚本';
        } else {
            // 表都存在，可能是字段问题，检查messages表结构
            $columns = $db->fetchAll("PRAGMA table_info(messages)");
            $columnNames = array_column($columns, 'name');
            $requiredColumns = ['id', 'conversation_id', 'sender_id', 'receiver_id', 'content', 'type', 'is_read', 'created_at'];
            $missingColumns = array_diff($requiredColumns, $columnNames);
            if (!empty($missingColumns)) {
                $errorMsg = 'messages表缺少字段: ' . implode(', ', $missingColumns) . '，请运行数据库修复脚本';
            }
        }
        
        jsonError($errorMsg, 500);
    }
    
    // 更新会话的最后一条消息
    $lastMessage = function_exists('mb_substr') ? mb_substr($content, 0, 50) : substr($content, 0, 50);
    $db->execute(
        "UPDATE conversations SET last_message = ?, last_message_time = ?, last_sender_id = ? WHERE id = ?",
        [$lastMessage, date('Y-m-d H:i:s'), $userId, $conversationId]
    );
    
    // 返回消息
    $message = $db->fetchOne("SELECT * FROM messages WHERE id = ?", [$messageId]);
    
    jsonSuccess([
        'message' => $message,
        'conversation_id' => $conversationId
    ], '发送成功');
}

// ============================================================
// 辅助函数：获取或创建会话
// ============================================================
function getOrCreateConversation($userId1, $userId2) {
    $db = Database::getInstance();
    
    // 确保user1_id < user2_id，避免重复会话
    $minId = min($userId1, $userId2);
    $maxId = max($userId1, $userId2);
    
    // 查找现有会话
    $conv = $db->fetchOne("SELECT * FROM conversations WHERE user1_id = ? AND user2_id = ?", [$minId, $maxId]);
    
    if ($conv) {
        return $conv;
    }
    
    // 创建新会话
    $convId = $db->insert(
        "INSERT INTO conversations (user1_id, user2_id, created_at) VALUES (?, ?, ?)",
        [$minId, $maxId, date('Y-m-d H:i:s')]
    );
    
    if (!$convId) {
        // 插入失败，可能是并发问题，再查一次
        usleep(100000); // 等待100ms
        $conv = $db->fetchOne("SELECT * FROM conversations WHERE user1_id = ? AND user2_id = ?", [$minId, $maxId]);
        if ($conv) {
            return $conv;
        }
        return null;
    }
    
    return $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$convId]);
}

// ============================================================
// 标记消息已读
// ============================================================
function handleReadMessage($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
    
    if ($conversationId <= 0) {
        jsonError('无效的会话ID', 400);
    }
    
    // 验证用户是否属于这个会话
    $conv = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    if (!$conv) {
        jsonError('会话不存在', 404);
    }
    if ($conv['user1_id'] != $userId && $conv['user2_id'] != $userId) {
        jsonError('无权访问此会话', 403);
    }
    
    // 标记所有未读消息为已读
    $db->execute(
        "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0",
        [$conversationId, $userId]
    );
    
    jsonSuccess(null, '已标记为已读');
}

// ============================================================
// 未读消息数
// ============================================================
function handleUnreadCount($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    // 查询总未读消息数
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0",
        [$userId]
    );
    
    // 按会话分组的未读消息数
    $byConversation = $db->fetchAll(
        "SELECT conversation_id, COUNT(*) as count 
         FROM messages 
         WHERE receiver_id = ? AND is_read = 0
         GROUP BY conversation_id",
        [$userId]
    );
    
    jsonSuccess([
        'total' => (int)($total['count'] ?? 0),
        'by_conversation' => $byConversation
    ], '获取成功');
}

// ============================================================
// 创建或获取会话
// ============================================================
function handleCreateOrGetConversation($user) {
    $db = Database::getInstance();
    $userId = (int)$user['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $otherUserId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    
    if ($otherUserId <= 0) {
        jsonError('无效的用户ID', 400);
    }
    
    if ($otherUserId == $userId) {
        jsonError('不能和自己创建会话', 400);
    }
    
    // 检查用户是否存在
    $otherUser = $db->fetchOne("SELECT id, username, nickname, avatar FROM users WHERE id = ?", [$otherUserId]);
    if (!$otherUser) {
        jsonError('用户不存在', 404);
    }
    
    // 获取或创建会话
    $conv = getOrCreateConversation($userId, $otherUserId);
    
    // 判断是否是好友
    $isFriend = isFriends($userId, $otherUserId);
    
    jsonSuccess([
        'conversation_id' => $conv['id'],
        'other_user' => $otherUser,
        'is_friend' => $isFriend ? 1 : 0
    ], '获取成功');
}

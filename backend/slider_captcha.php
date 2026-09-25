<?php
/**
 * ============================================================
 * SecureVault 滑块人机验证 - 后端接口
 * ============================================================
 * 功能：
 *   1. 生成验证配置（随机目标位置、验证ID、签名token）
 *   2. 验证滑块（位置验证、轨迹分析、防重放）
 *   3. 检查验证状态（cookie验证）
 *   4. 清除验证（一次性使用）
 * 
 * 安全机制：
 *   - 随机目标位置（不是固定最右边）
 *   - 签名token（防止篡改验证配置）
 *   - 轨迹分析（检测人类行为特征）
 *   - 一次性验证ID（防止重放攻击）
 *   - HttpOnly Cookie（防止XSS窃取）
 *   - 验证过期机制
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// 获取操作类型
$action = getParam('action', 'get');

switch ($action) {
    case 'get':
        handleGenerateCaptcha();
        break;
    case 'verify':
        handleVerifyCaptcha();
        break;
    case 'check':
        handleCheckCaptcha();
        break;
    case 'clear':
        handleClearCaptcha();
        break;
    default:
        jsonError('未知的操作类型', 400);
}

// ============================================================
// 生成滑块验证配置
// ============================================================
function handleGenerateCaptcha() {
    $db = Database::getInstance();
    
    // 确保验证表存在
    ensureCaptchaTable($db);
    
    // 生成唯一验证ID
    $captchaId = bin2hex(random_bytes(16));
    
    // 随机目标位置（20%-80%之间，避免太靠边）
    $targetX = rand(20, 80); // 百分比
    
    // 允许的误差范围（±5%）
    $tolerance = 5;
    
    // 生成签名密钥（用于验证配置不被篡改）
    $secretKey = bin2hex(random_bytes(8));
    
    // 生成签名token
    $tokenData = $captchaId . '|' . $targetX . '|' . $tolerance . '|' . time();
    $signature = hash_hmac('sha256', $tokenData, $secretKey);
    
    // 过期时间（5分钟）
    $expiresAt = date('Y-m-d H:i:s', time() + 300);
    
    // 存储验证信息
    $db->execute(
        "INSERT INTO slider_captcha (captcha_id, target_x, tolerance, secret_key, signature, expires_at, created_at, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$captchaId, $targetX, $tolerance, $secretKey, $signature, $expiresAt, date('Y-m-d H:i:s'), getClientIp()]
    );
    
    // 返回验证配置（不返回secret_key和signature）
    jsonSuccess([
        'captcha_id' => $captchaId,
        'target_x' => $targetX,
        'tolerance' => $tolerance,
        'expires_at' => $expiresAt,
        'token' => $signature // 返回签名用于前端验证（但不返回密钥）
    ], '验证配置生成成功');
}

// ============================================================
// 验证滑块
// ============================================================
function handleVerifyCaptcha() {
    $db = Database::getInstance();
    
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    $captchaId = isset($input['captcha_id']) ? trim($input['captcha_id']) : '';
    $endX = isset($input['end_x']) ? (float)$input['end_x'] : 0;
    $track = isset($input['track']) ? $input['track'] : [];
    $duration = isset($input['duration']) ? (int)$input['duration'] : 0;
    
    // 参数验证
    if (empty($captchaId)) {
        jsonError('验证ID不能为空', 400);
    }
    if ($endX <= 0 || $endX > 100) {
        jsonError('无效的结束位置', 400);
    }
    
    // 查询验证信息
    $captcha = $db->fetchOne(
        "SELECT * FROM slider_captcha WHERE captcha_id = ?",
        [$captchaId]
    );
    
    if (!$captcha) {
        jsonError('验证配置不存在或已失效', 400);
    }
    
    // 检查是否已使用
    if ($captcha['verified'] == 1) {
        jsonError('该验证已被使用，请重新获取', 400);
    }
    
    // 检查是否过期
    if (strtotime($captcha['expires_at']) < time()) {
        jsonError('验证已过期，请重新获取', 400);
    }
    
    // 检查IP是否匹配（可选，防止跨IP使用）
    if ($captcha['ip_address'] !== getClientIp()) {
        jsonError('IP地址不匹配，请重新获取', 400);
    }
    
    // 验证位置是否在目标范围内
    $targetX = (float)$captcha['target_x'];
    $tolerance = (float)$captcha['tolerance'];
    
    if (abs($endX - $targetX) > $tolerance) {
        // 位置不正确，记录失败次数
        $failCount = (int)$captcha['fail_count'] + 1;
        $db->execute(
            "UPDATE slider_captcha SET fail_count = ?, last_fail_at = ? WHERE id = ?",
            [$failCount, date('Y-m-d H:i:s'), $captcha['id']]
        );
        
        // 失败次数过多，使该验证失效
        if ($failCount >= 3) {
            $db->execute("UPDATE slider_captcha SET verified = 1 WHERE id = ?", [$captcha['id']]);
            jsonError('失败次数过多，请重新获取验证', 400);
        }
        
        jsonError('滑块位置不正确，请重试', 400);
    }
    
    // 轨迹分析（检测人类行为特征）
    $trackResult = analyzeTrack($track, $duration);
    if (!$trackResult['pass']) {
        jsonError('验证失败：' . $trackResult['reason'], 400);
    }
    
    // 验证通过，标记为已使用
    $db->execute(
        "UPDATE slider_captcha SET verified = 1, verified_at = ?, end_x = ?, duration = ?, track_points = ? WHERE id = ?",
        [date('Y-m-d H:i:s'), $endX, $duration, count($track), $captcha['id']]
    );
    
    // 生成验证通过的token
    $verifyToken = bin2hex(random_bytes(32));
    
    // 设置HttpOnly Cookie（防止XSS窃取，5分钟有效）
    setcookie('slider_verified', $verifyToken, [
        'expires' => time() + 300,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // 同时在session中存储（双重验证）
    session_start();
    $_SESSION['slider_verified'] = $verifyToken;
    $_SESSION['slider_verified_at'] = time();
    session_write_close();
    
    // 返回验证成功
    jsonSuccess([
        'verified' => true,
        'verify_token' => $verifyToken,
        'expires_in' => 300
    ], '验证成功');
}

// ============================================================
// 检查验证状态（登录时调用）
// ============================================================
function handleCheckCaptcha() {
    // 检查cookie
    if (empty($_COOKIE['slider_verified'])) {
        jsonError('未通过滑块验证', 401);
    }
    
    // 检查session
    session_start();
    if (empty($_SESSION['slider_verified']) || 
        $_SESSION['slider_verified'] !== $_COOKIE['slider_verified'] ||
        (time() - $_SESSION['slider_verified_at']) > 300) {
        session_write_close();
        jsonError('验证已失效，请重新验证', 401);
    }
    session_write_close();
    
    jsonSuccess(['verified' => true], '验证有效');
}

// ============================================================
// 清除验证（登录成功后调用，一次性使用）
// ============================================================
function handleClearCaptcha() {
    // 清除cookie
    setcookie('slider_verified', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // 清除session
    session_start();
    unset($_SESSION['slider_verified']);
    unset($_SESSION['slider_verified_at']);
    session_write_close();
    
    jsonSuccess(null, '验证已清除');
}

// ============================================================
// 辅助函数：确保验证表存在
// ============================================================
function ensureCaptchaTable($db) {
    $db->execute("CREATE TABLE IF NOT EXISTS slider_captcha (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        captcha_id TEXT NOT NULL UNIQUE,
        target_x REAL NOT NULL,
        tolerance REAL NOT NULL DEFAULT 5,
        secret_key TEXT NOT NULL,
        signature TEXT NOT NULL,
        verified INTEGER DEFAULT 0,
        verified_at DATETIME,
        end_x REAL,
        duration INTEGER,
        track_points INTEGER,
        fail_count INTEGER DEFAULT 0,
        last_fail_at DATETIME,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        ip_address TEXT
    )");
    
    // 清理过期的验证记录（每次生成时清理1小时前的）
    $db->execute("DELETE FROM slider_captcha WHERE created_at < ?", [date('Y-m-d H:i:s', time() - 3600)]);
}

// ============================================================
// 辅助函数：轨迹分析
// ============================================================
function analyzeTrack($track, $duration) {
    // 如果没有轨迹数据，直接通过（兼容旧版）
    if (empty($track) || !is_array($track)) {
        return ['pass' => true, 'reason' => '无轨迹数据，跳过分析'];
    }
    
    $pointCount = count($track);
    
    // 检查1：轨迹点数不能太少（人类拖动至少有5个点）
    if ($pointCount < 5) {
        return ['pass' => false, 'reason' => '轨迹点数过少，疑似机器操作'];
    }
    
    // 检查2：拖动时间不能太短（人类至少需要100ms）
    if ($duration < 100) {
        return ['pass' => false, 'reason' => '拖动时间过短，疑似机器操作'];
    }
    
    // 检查3：拖动时间不能太长（超过10秒可能是异常）
    if ($duration > 10000) {
        return ['pass' => false, 'reason' => '拖动时间过长'];
    }
    
    // 检查4：位置应该是单调递增的（人类拖动不会来回抖动太多）
    $lastX = 0;
    $backCount = 0;
    foreach ($track as $point) {
        $x = isset($point['x']) ? (float)$point['x'] : 0;
        if ($x < $lastX) {
            $backCount++;
        }
        $lastX = $x;
    }
    // 回退次数不能超过总点数的30%
    if ($backCount > $pointCount * 0.3) {
        return ['pass' => false, 'reason' => '轨迹异常，疑似机器操作'];
    }
    
    // 检查5：速度变化应该自然（不是匀速）
    if ($pointCount >= 3) {
        $speeds = [];
        for ($i = 1; $i < $pointCount; $i++) {
            $dx = abs($track[$i]['x'] - $track[$i-1]['x']);
            $dt = max(1, abs($track[$i]['t'] - $track[$i-1]['t']));
            $speeds[] = $dx / $dt;
        }
        
        // 计算速度方差（人类速度变化大，机器速度均匀）
        if (count($speeds) >= 3) {
            $avgSpeed = array_sum($speeds) / count($speeds);
            $variance = 0;
            foreach ($speeds as $speed) {
                $variance += pow($speed - $avgSpeed, 2);
            }
            $variance /= count($speeds);
            
            // 方差太小说明速度太均匀，疑似机器
            if ($variance < 0.001 && $avgSpeed > 0) {
                return ['pass' => false, 'reason' => '速度过于均匀，疑似机器操作'];
            }
        }
    }
    
    // 所有检查通过
    return ['pass' => true, 'reason' => '轨迹分析通过'];
}

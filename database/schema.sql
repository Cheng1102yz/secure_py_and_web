-- ============================================================
-- SecureVault 用户系统数据库设计
-- 支持 MySQL 和 SQLite（本脚本为 MySQL 版本）
-- SQLite 版本请参考 schema_sqlite.sql
-- ============================================================

-- 用户表：存储所有注册用户的基本信息
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '用户唯一ID',
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名（唯一）',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希（bcrypt/Argon2）',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱（可选）',
    `nickname` VARCHAR(50) DEFAULT NULL COMMENT '昵称（可选）',
    `status` TINYINT DEFAULT 1 COMMENT '账号状态：0=禁用，1=正常，2=待验证',
    `is_admin` TINYINT DEFAULT 0 COMMENT '是否管理员：0=普通用户，1=管理员',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `last_login` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
    INDEX `idx_username` (`username`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 登录会话表：存储用户的登录状态，用于单点登录和在线检测
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '会话ID',
    `user_id` INT NOT NULL COMMENT '关联用户ID',
    `token` VARCHAR(128) NOT NULL UNIQUE COMMENT '登录令牌（随机字符串）',
    `device_info` VARCHAR(255) DEFAULT NULL COMMENT '设备信息（操作系统/软件版本）',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT '登录IP',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `expires_at` DATETIME NOT NULL COMMENT '过期时间',
    `is_active` TINYINT DEFAULT 1 COMMENT '是否有效：0=已失效，1=有效',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录会话表';

-- 登录日志表：记录所有登录尝试，用于安全审计
CREATE TABLE IF NOT EXISTS `login_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '日志ID',
    `user_id` INT DEFAULT NULL COMMENT '用户ID（登录成功时记录）',
    `username` VARCHAR(50) NOT NULL COMMENT '尝试登录的用户名',
    `login_time` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT '登录IP',
    `device_info` VARCHAR(255) DEFAULT NULL COMMENT '设备信息',
    `status` TINYINT NOT NULL COMMENT '登录结果：0=失败，1=成功',
    `fail_reason` VARCHAR(100) DEFAULT NULL COMMENT '失败原因',
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_login_time` (`login_time`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志表';

-- 软件信息表：管理软件版本和下载信息
CREATE TABLE IF NOT EXISTS `software` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '软件ID',
    `name` VARCHAR(100) NOT NULL COMMENT '软件名称',
    `version` VARCHAR(20) NOT NULL COMMENT '版本号',
    `description` TEXT COMMENT '版本描述',
    `download_url` VARCHAR(500) DEFAULT NULL COMMENT '下载链接',
    `file_size` VARCHAR(20) DEFAULT NULL COMMENT '文件大小',
    `is_active` TINYINT DEFAULT 1 COMMENT '是否发布：0=未发布，1=已发布',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
    INDEX `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='软件信息表';

-- 操作日志表：记录管理员的操作，用于审计
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '日志ID',
    `admin_id` INT NOT NULL COMMENT '管理员用户ID',
    `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
    `target` VARCHAR(100) DEFAULT NULL COMMENT '操作对象',
    `detail` TEXT COMMENT '操作详情',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT '操作IP',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_admin_id` (`admin_id`),
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员操作日志表';

-- ============================================================
-- 初始化数据：创建默认管理员账号
-- 默认管理员：admin / Admin@123456
-- 密码哈希使用 PHP password_hash() 生成
-- ============================================================
INSERT INTO `users` (`username`, `password_hash`, `nickname`, `status`, `is_admin`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员', 1, 1)
ON DUPLICATE KEY UPDATE `username` = `username`;

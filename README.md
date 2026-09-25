# SecureVault 用户系统 - 网站后端与管理后台

## 📋 项目简介

SecureVault 用户系统是一个完整的用户认证与管理系统，包含：
- **Web前端**：登录页、注册页、管理员后台
- **PHP后端**：用户认证、用户管理、软件管理、日志审计API
- **桌面端集成**：PyQt5桌面软件通过API调用实现联网登录

## 📁 目录结构

```
web/
├── frontend/              # 前端文件
│   ├── index.html         # 登录页面
│   ├── register.html      # 注册页面
│   ├── admin.html         # 管理员后台
│   ├── css/
│   │   └── style.css      # 全局样式
│   └── js/
│       ├── auth.js        # 登录注册逻辑
│       └── admin.js       # 管理后台逻辑
├── backend/               # PHP后端
│   ├── config.php         # 配置文件（数据库、安全设置）
│   ├── db.php             # 数据库连接类（PDO，支持SQLite/MySQL）
│   ├── helpers.php        # 通用工具函数（响应、验证、认证）
│   ├── auth.php           # 认证API（登录、注册、登出、状态）
│   ├── user.php           # 用户API（个人资料、登录历史）
│   └── admin.php          # 管理员API（用户管理、软件管理、日志）
├── database/              # 数据库
│   ├── schema.sql         # MySQL建表脚本
│   ├── schema_sqlite.sql  # SQLite建表脚本
│   └── securevault.db     # SQLite数据库文件（自动生成）
├── start_server.bat       # Windows一键启动脚本
└── README.md              # 本文档
```

## 🚀 快速开始

### 1. 安装PHP

**Windows系统：**
1. 下载PHP：https://windows.php.net/download/ （建议下载 Thread Safe 版本的 Zip 包）
2. 解压到 `C:\php`
3. 将 `C:\php` 添加到系统环境变量 `PATH`
4. 复制 `C:\php\php.ini-development` 为 `C:\php\php.ini`
5. 编辑 `php.ini`，取消以下扩展前面的分号（;）：
   ```ini
   extension=pdo_sqlite
   extension=pdo_mysql    ; 如果使用MySQL
   extension=openssl
   ```
6. 重启命令行，运行 `php -v` 验证安装

### 2. 启动服务器

双击运行 `start_server.bat`，或在命令行中执行：

```bash
cd C:\Users\Administrator\Desktop\web
php -S localhost:8000 -t .
```

### 3. 访问网站

- **登录页面**：http://localhost:8000/frontend/index.html
- **注册页面**：http://localhost:8000/frontend/register.html
- **管理后台**：http://localhost:8000/frontend/admin.html

### 4. 默认管理员账号

```
用户名：admin
密码：Admin@123456
```

> ⚠️ 首次登录后请立即修改管理员密码！

## ⚙️ 配置说明

### 数据库配置

编辑 `backend/config.php`：

**使用SQLite（默认，无需额外安装）：**
```php
define('DB_TYPE', 'sqlite');
define('SQLITE_PATH', __DIR__ . '/../database/securevault.db');
```

**使用MySQL（生产环境推荐）：**
```php
define('DB_TYPE', 'mysql');
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', 3306);
define('MYSQL_DBNAME', 'securevault');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', 'your_password');
```

然后导入 `database/schema.sql` 到MySQL数据库。

### 安全配置

```php
define('SECRET_KEY', 'your-secret-key-here');  // 请修改为随机字符串
define('SESSION_EXPIRE', 604800);                // 会话有效期（7天）
define('MAX_LOGIN_ATTEMPTS', 5);                  // 最大登录失败次数
define('LOGIN_LOCK_TIME', 300);                   // 锁定时间（5分钟）
```

## 🔌 API接口文档

### 认证接口 (auth.php)

| 接口 | 方法 | 参数 | 说明 |
|------|------|------|------|
| `?action=register` | POST | username, password, nickname?, email? | 用户注册 |
| `?action=login` | POST | username, password | 用户登录 |
| `?action=logout` | POST | （需要token） | 退出登录 |
| `?action=status` | GET | （需要token） | 检查登录状态 |
| `?action=change_pwd` | POST | old_password, new_password | 修改密码 |

### 用户接口 (user.php)

| 接口 | 方法 | 参数 | 说明 |
|------|------|------|------|
| `?action=profile` | GET | （需要token） | 获取个人资料 |
| `?action=update` | POST | nickname?, email? | 更新个人资料 |
| `?action=login_history` | GET | page, page_size | 登录历史 |
| `?action=active_sessions` | GET | - | 活跃会话列表 |
| `?action=revoke_session` | POST | session_id | 撤销会话 |

### 管理员接口 (admin.php)

| 接口 | 方法 | 参数 | 说明 |
|------|------|------|------|
| `?action=dashboard` | GET | - | 仪表盘统计 |
| `?action=users` | GET | page, page_size, keyword?, status? | 用户列表 |
| `?action=user_status` | POST | user_id, status | 修改用户状态 |
| `?action=user_delete` | POST | user_id | 删除用户 |
| `?action=user_reset_pwd` | POST | user_id, new_password | 重置密码 |
| `?action=online_users` | GET | - | 在线用户 |
| `?action=login_logs` | GET | page, page_size, keyword? | 登录日志 |
| `?action=admin_logs` | GET | page, page_size | 操作日志 |
| `?action=software_list` | GET | - | 软件版本列表 |
| `?action=software_add` | POST | name, version, ... | 添加软件版本 |
| `?action=software_update` | POST | id, name, version, ... | 更新软件版本 |
| `?action=software_delete` | POST | id | 删除软件版本 |
| `?action=latest_version` | GET | - | 获取最新版本（公开） |

### 统一响应格式

```json
{
    "success": true,
    "code": 200,
    "message": "操作成功",
    "data": { ... },
    "timestamp": 1234567890
}
```

### 认证方式

在请求头中添加：
```
Authorization: Bearer {token}
```

## 🖥️ 桌面端集成

### 配置API地址

编辑桌面端 `utils/api_client.py`：

```python
# 本地测试
API_BASE = "http://localhost:8000/backend"

# 生产环境（部署到服务器后）
API_BASE = "https://your-domain.com/backend"
```

### 桌面端文件说明

- `utils/api_client.py` - API调用客户端（HTTP请求、令牌管理）
- `utils/user_manager.py` - 用户管理模块（调用API实现登录注册）
- `ui/login_dialog.py` - 登录注册对话框UI

## 🌐 部署到生产环境

### 1. 准备服务器

- 一台支持PHP的Web服务器（推荐Linux + Nginx/Apache + PHP 7.4+）
- MySQL数据库（生产环境推荐）

### 2. 上传文件

将 `web/` 目录下的所有文件上传到服务器网站根目录。

### 3. 配置数据库

1. 创建MySQL数据库 `securevault`
2. 导入 `database/schema.sql`
3. 修改 `backend/config.php` 中的数据库配置

### 4. 配置Web服务器

**Nginx配置示例：**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/securevault;
    
    location / {
        try_files $uri $uri/ =404;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. 安全设置

- 修改 `SECRET_KEY` 为随机字符串
- 修改默认管理员密码
- 配置HTTPS（使用Let's Encrypt免费证书）
- 设置正确的文件权限（配置文件不可公开访问）
- 定期备份数据库

## 📊 功能特性

### 用户功能
- ✅ 用户注册（用户名、昵称、邮箱）
- ✅ 用户登录（密码验证、会话管理）
- ✅ 退出登录
- ✅ 自动登录（令牌持久化）
- ✅ 修改密码
- ✅ 个人资料管理
- ✅ 登录历史查看
- ✅ 多设备会话管理

### 管理员功能
- ✅ 仪表盘数据统计（用户数、在线数、登录数、增长趋势）
- ✅ 用户管理（查看、搜索、禁用、启用、删除、重置密码）
- ✅ 在线用户监控（查看活跃会话、强制下线）
- ✅ 登录日志审计（所有登录记录、失败原因）
- ✅ 软件版本管理（添加、编辑、删除、发布状态）
- ✅ 管理员操作日志（所有管理操作记录）

### 安全特性
- ✅ 密码使用 bcrypt/Argon2 哈希存储
- ✅ 随机令牌会话管理
- ✅ 登录失败锁定机制
- ✅ SQL注入防护（PDO预处理语句）
- ✅ XSS防护（输出转义）
- ✅ CSRF防护（可扩展）
- ✅ HTTPS支持
- ✅ 操作日志审计

## 🔧 常见问题

### Q: 启动服务器后访问页面404？
A: 确保启动命令的根目录正确，应该是 `web/` 目录，而不是 `web/frontend/`。

### Q: 登录时提示"网络连接失败"？
A: 
1. 检查PHP服务器是否正常运行
2. 检查桌面端 `api_client.py` 中的 `API_BASE` 地址是否正确
3. 检查防火墙是否阻止了8000端口

### Q: 数据库文件在哪里？
A: SQLite数据库文件在 `database/securevault.db`，首次运行时自动创建。

### Q: 如何重置管理员密码？
A: 删除 `database/securevault.db` 文件，系统会重新初始化并创建默认管理员账号。

### Q: 支持多用户同时在线吗？
A: 支持，每个用户可以有多个活跃会话（多设备登录），管理员可以在后台查看和管理。

## 📝 更新日志

### v2.0.0 (2026-08-25)
- 全新用户系统，支持联网登录注册
- PHP后端API，支持SQLite/MySQL双数据库
- 现代化Web前端（登录页、注册页、管理后台）
- 完整的管理员功能（用户管理、日志审计、软件管理）
- 桌面端集成，支持自动登录
- 详细的安全防护和日志审计

## 📄 许可证

本项目仅供学习和合法用途使用，请勿用于非法用途。

---

**work_by_cyz** | SecureVault v2.0

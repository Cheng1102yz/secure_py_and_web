@echo off
chcp 65001 >nul
title SecureVault 用户系统 - PHP服务器

echo ============================================================
echo   SecureVault 用户系统 - PHP内置服务器
echo ============================================================
echo.

REM 检查PHP是否安装
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ 错误：未检测到PHP，请先安装PHP
    echo.
    echo 下载地址：https://windows.php.net/download/
    echo 安装教程：
    echo   1. 下载PHP Zip包（建议Thread Safe版本）
    echo   2. 解压到 C:\php
    echo   3. 将 C:\php 添加到系统环境变量PATH
    echo   4. 复制 php.ini-development 为 php.ini
    echo   5. 编辑 php.ini，取消 extension=pdo_sqlite 和 extension=pdo_mysql 前面的分号
    echo   6. 重新运行此脚本
    echo.
    pause
    exit /b 1
)

echo ✅ PHP已安装
php -v
echo.

REM 获取脚本所在目录
set SCRIPT_DIR=%~dp0
set WEB_ROOT=%SCRIPT_DIR%frontend
set BACKEND_DIR=%SCRIPT_DIR%backend

echo 网站根目录: %WEB_ROOT%
echo 后端目录: %BACKEND_DIR%
echo.

REM 检查前端目录是否存在
if not exist "%WEB_ROOT%" (
    echo ❌ 错误：前端目录不存在
    pause
    exit /b 1
)

echo ============================================================
echo   服务器启动中...
echo   前端地址: http://localhost:8000
echo   登录页面: http://localhost:8000/index.html
echo   管理后台: http://localhost:8000/admin.html
echo   后端API:  http://localhost:8000/../backend/auth.php
echo.
echo   默认管理员账号: admin / Admin@123456
echo ============================================================
echo.
echo 按 Ctrl+C 停止服务器
echo.

REM 启动PHP内置服务器
REM 注意：PHP内置服务器不支持上级目录访问，所以需要把根目录设为web目录
REM 后端文件需要通过路由或复制到frontend目录下访问
REM 这里使用一个简单的方法：将web根目录设为项目根目录，前端在frontend子目录
cd /d "%SCRIPT_DIR%"
php -S localhost:8000 -t "%SCRIPT_DIR%"

pause

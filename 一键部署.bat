@echo off
chcp 65001 >nul
title SecureVault 一键部署工具 - Apache + PHP
color 0A

echo ============================================================
echo    SecureVault 一键部署工具
echo    Apache + PHP 环境配置
echo ============================================================
echo.

:: 检查管理员权限
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 请以管理员身份运行此脚本！
    echo 右键点击此文件，选择"以管理员身份运行"
    pause
    exit /b 1
)

echo [√] 管理员权限检查通过
echo.

:: ============================================================
:: 配置区域 - 请根据实际情况修改以下路径
:: ============================================================

:: Apache安装路径
set APACHE_PATH=C:\Apache24

:: PHP安装路径
set PHP_PATH=C:\php-8.5.9-Win32-vs17-x64

:: 网站根目录（web项目所在路径）
set WEB_PATH=C:\Users\Administrator\Desktop\web

:: Apache服务名称
set SERVICE_NAME=Apache2.4

:: 监听端口
set PORT=80

:: ============================================================
:: 检查路径是否存在
:: ============================================================

echo [1/6] 检查路径配置...
echo.

if not exist "%APACHE_PATH%\bin\httpd.exe" (
    echo [错误] Apache未找到: %APACHE_PATH%\bin\httpd.exe
    echo 请先下载Apache并解压到 %APACHE_PATH%
    echo 下载地址: https://www.apachelounge.com/download/
    pause
    exit /b 1
)
echo [√] Apache路径正确

if not exist "%PHP_PATH%\php.exe" (
    echo [错误] PHP未找到: %PHP_PATH%\php.exe
    echo 请先下载PHP并解压到 %PHP_PATH%
    pause
    exit /b 1
)
echo [√] PHP路径正确

if not exist "%PHP_PATH%\php8apache2_4.dll" (
    echo [错误] PHP Apache模块未找到: %PHP_PATH%\php8apache2_4.dll
    echo 请确保下载的是Thread Safe版本的PHP
    pause
    exit /b 1
)
echo [√] PHP Apache模块存在

if not exist "%WEB_PATH%" (
    echo [错误] 网站目录未找到: %WEB_PATH%
    echo 请检查WEB_PATH配置
    pause
    exit /b 1
)
echo [√] 网站目录正确

echo.

:: ============================================================
:: 停止占用端口的进程
:: ============================================================

echo [2/6] 检查端口占用...
echo.

for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":%PORT% " ^| findstr "LISTENING"') do (
    echo 发现端口 %PORT% 被进程 %%a 占用，正在停止...
    taskkill /F /PID %%a >nul 2>&1
)

echo [√] 端口 %PORT% 检查完成
echo.

:: ============================================================
:: 备份并配置Apache
:: ============================================================

echo [3/6] 配置Apache...
echo.

set CONF_FILE=%APACHE_PATH%\conf\httpd.conf

:: 备份原始配置
if exist "%CONF_FILE%.bak" (
    echo [i] 备份文件已存在，跳过备份
) else (
    copy "%CONF_FILE%" "%CONF_FILE%.bak" >nul
    echo [√] 已备份原始配置到 httpd.conf.bak
)

:: 使用PowerShell修改配置文件（更可靠）
powershell -Command "$conf = Get-Content '%CONF_FILE%' -Raw; $conf = $conf -replace 'Define SRVROOT \"[^\"]*\"', 'Define SRVROOT \"%APACHE_PATH:\=/%\"'; $conf = $conf -replace '^Listen \d+', 'Listen %PORT%'; $conf = $conf -replace '#ServerName www\.example\.com:\d+', 'ServerName localhost:%PORT%'; $conf = $conf -replace 'DocumentRoot \"\$\{SRVROOT\}/htdocs\"', 'DocumentRoot \"%WEB_PATH:\=/%\"'; $conf = $conf -replace '<Directory \"\$\{SRVROOT\}/htdocs\">', '<Directory \"%WEB_PATH:\=/%\">'; $conf = $conf -replace 'AllowOverride None', 'AllowOverride All'; $conf = $conf -replace '#LoadModule rewrite_module modules/mod_rewrite\.so', 'LoadModule rewrite_module modules/mod_rewrite.so'; Set-Content -Path '%CONF_FILE%' -Value $conf -Encoding UTF8"

echo [√] Apache基础配置完成

:: ============================================================
:: 添加PHP模块配置
:: ============================================================

echo.
echo [4/6] 配置PHP模块...
echo.

:: 检查是否已添加PHP配置
findstr /C:"LoadModule php_module" "%CONF_FILE%" >nul
if %errorlevel% equ 0 (
    echo [i] PHP模块配置已存在，跳过添加
) else (
    :: 添加PHP配置到文件末尾
    echo. >> "%CONF_FILE%"
    echo # ============================================================ >> "%CONF_FILE%"
    echo # PHP 8.x 配置 >> "%CONF_FILE%"
    echo # ============================================================ >> "%CONF_FILE%"
    echo LoadModule php_module "%PHP_PATH:\=/%/php8apache2_4.dll" >> "%CONF_FILE%"
    echo AddHandler application/x-httpd-php .php >> "%CONF_FILE%"
    echo PHPIniDir "%PHP_PATH:\=/%" >> "%CONF_FILE%"
    echo. >> "%CONF_FILE%"
    echo ^<IfModule dir_module^> >> "%CONF_FILE%"
    echo     DirectoryIndex index.html index.php >> "%CONF_FILE%"
    echo ^</IfModule^> >> "%CONF_FILE%"
    echo [√] PHP模块配置已添加
)

:: ============================================================
:: 验证Apache配置语法
:: ============================================================

echo.
echo [5/6] 验证配置语法...
echo.

"%APACHE_PATH%\bin\httpd.exe" -t
if %errorlevel% neq 0 (
    echo.
    echo [错误] Apache配置语法错误！
    echo 请检查上面的错误信息
    pause
    exit /b 1
)

echo [√] 配置语法验证通过
echo.

:: ============================================================
:: 安装并启动Apache服务
:: ============================================================

echo [6/6] 安装并启动Apache服务...
echo.

:: 检查服务是否已存在
sc query "%SERVICE_NAME%" >nul 2>&1
if %errorlevel% equ 0 (
    echo [i] 服务 %SERVICE_NAME% 已存在，先停止...
    net stop "%SERVICE_NAME%" >nul 2>&1
    timeout /t 2 /nobreak >nul
)

:: 安装服务
"%APACHE_PATH%\bin\httpd.exe" -k install -n "%SERVICE_NAME%"
if %errorlevel% neq 0 (
    echo [错误] Apache服务安装失败！
    pause
    exit /b 1
)

echo [√] Apache服务安装成功

:: 启动服务
net start "%SERVICE_NAME%"
if %errorlevel% neq 0 (
    echo [错误] Apache服务启动失败！
    echo 请检查端口 %PORT% 是否被其他程序占用
    pause
    exit /b 1
)

echo [√] Apache服务启动成功
echo.

:: ============================================================
:: 测试网站
:: ============================================================

echo ============================================================
echo    部署完成！正在测试网站...
echo ============================================================
echo.

timeout /t 2 /nobreak >nul

:: 测试首页
curl -s -o nul -w "首页测试: HTTP %%{http_code}\n" "http://localhost:%PORT%/"
curl -s -o nul -w "登录页测试: HTTP %%{http_code}\n" "http://localhost:%PORT%/frontend/login.html"
curl -s -o nul -w "PHP测试: HTTP %%{http_code}\n" "http://localhost:%PORT%/backend/test.php"

echo.
echo ============================================================
echo    部署成功！
echo ============================================================
echo.
echo 网站访问地址:
echo   主页: http://localhost:%PORT%/frontend/index.html
echo   登录: http://localhost:%PORT%/frontend/login.html
echo   注册: http://localhost:%PORT%/frontend/register.html
echo   后台: http://localhost:%PORT%/frontend/admin.html
echo.
echo 服务管理命令:
echo   启动: net start %SERVICE_NAME%
echo   停止: net stop %SERVICE_NAME%
echo   重启: net stop %SERVICE_NAME% ^&^& net start %SERVICE_NAME%
echo.
echo 配置文件: %CONF_FILE%
echo 网站目录: %WEB_PATH%
echo.

:: 询问是否打开浏览器
set /p open_browser=是否打开浏览器测试网站？(Y/N): 
if /i "%open_browser%"=="Y" (
    start http://localhost:%PORT%/frontend/index.html
)

echo.
echo 按任意键退出...
pause >nul
exit /b 0

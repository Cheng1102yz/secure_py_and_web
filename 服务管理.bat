@echo off
chcp 65001 >nul
title SecureVault Apache服务管理
color 0B

:: 配置
set APACHE_PATH=C:\Apache24
set SERVICE_NAME=Apache2.4
set PORT=80

:menu
cls
echo ============================================================
echo    SecureVault Apache服务管理
echo ============================================================
echo.
echo   当前服务状态:
sc query "%SERVICE_NAME%" | findstr "STATE"
echo.
echo ============================================================
echo.
echo   [1] 启动服务
echo   [2] 停止服务
echo   [3] 重启服务
echo   [4] 查看配置语法
echo   [5] 测试网站
echo   [6] 打开浏览器
echo   [7] 卸载服务
echo   [0] 退出
echo.
echo ============================================================
set /p choice=请选择操作 (0-7): 

if "%choice%"=="1" goto start
if "%choice%"=="2" goto stop
if "%choice%"=="3" goto restart
if "%choice%"=="4" goto testconf
if "%choice%"=="5" goto testsite
if "%choice%"=="6" goto openbrowser
if "%choice%"=="7" goto uninstall
if "%choice%"=="0" goto exit

echo 无效选择，请重新输入！
timeout /t 2 /nobreak >nul
goto menu

:start
echo.
echo 正在启动Apache服务...
net start "%SERVICE_NAME%"
echo.
echo 按任意键返回菜单...
pause >nul
goto menu

:stop
echo.
echo 正在停止Apache服务...
net stop "%SERVICE_NAME%"
echo.
echo 按任意键返回菜单...
pause >nul
goto menu

:restart
echo.
echo 正在重启Apache服务...
net stop "%SERVICE_NAME%"
timeout /t 2 /nobreak >nul
net start "%SERVICE_NAME%"
echo.
echo 按任意键返回菜单...
pause >nul
goto menu

:testconf
echo.
echo 正在验证配置语法...
echo.
"%APACHE_PATH%\bin\httpd.exe" -t
echo.
echo 按任意键返回菜单...
pause >nul
goto menu

:testsite
echo.
echo 正在测试网站...
echo.
curl -s -o nul -w "首页: HTTP %%{http_code}\n" "http://localhost:%PORT%/"
curl -s -o nul -w "登录页: HTTP %%{http_code}\n" "http://localhost:%PORT%/frontend/login.html"
curl -s -o nul -w "PHP测试: HTTP %%{http_code}\n" "http://localhost:%PORT%/backend/test.php"
echo.
echo 按任意键返回菜单...
pause >nul
goto menu

:openbrowser
start http://localhost:%PORT%/frontend/index.html
goto menu

:uninstall
echo.
echo ============================================================
echo   警告：即将卸载Apache服务！
echo   此操作不会删除Apache文件，只是移除Windows服务
echo ============================================================
echo.
set /p confirm=确认卸载？(Y/N): 
if /i not "%confirm%"=="Y" goto menu

echo.
echo 正在停止服务...
net stop "%SERVICE_NAME%" >nul 2>&1
timeout /t 2 /nobreak >nul

echo 正在卸载服务...
"%APACHE_PATH%\bin\httpd.exe" -k uninstall -n "%SERVICE_NAME%"

echo.
echo 服务已卸载！
echo.
echo 按任意键返回菜单...
pause >nul
goto menu

:exit
exit /b 0

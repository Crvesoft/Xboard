<?php
/**
 * index.php集成代码
 * 在原有PHP程序首页增加OAuth登录认证
 * 
 * 使用说明：将此代码放在原有index.php文件的最顶部
 */

// 启动会话
session_start();

// 定义常量，表示已加载OAuth
define('OAUTH_LOADED', true);

// 引入配置文件
require_once 'dlconfig.php';

// 检查用户是否已登录
function checkOAuthLogin() {
    global $oauth_config;
    
    // 检查cookie是否存在
    if (!isset($_COOKIE[$oauth_config['cookie_name']])) {
        return false;
    }
    
    return true;
}

// 显示登录页面
function showLoginPage() {
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录认证</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fff;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            position: relative;
        }
        .login-container {
            text-align: center; /* 确保内容居中 */
            padding: 30px;
            border-radius: 5px;
        }
        .login-container img {
            display: block;
            margin: 0 auto; /* 图片水平居中 */
            width: 150px; /* 设置图片宽度 */
            height: auto; /* 保持图片纵横比 */
            margin-bottom: 20px; /* 使按钮和图片间有间距 */
        }
        .login-button {
            display: inline-block;
            margin-top: 0; /* 防止不必要间隙 */
            padding: 12px 24px;
            background-color: #337ab7;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .login-button:hover {
            background-color: #286090;
        }
        .github-link {
            position: absolute;
            bottom: 20px;
            right: 20px;
            text-decoration: none;
            font-size: 16px;
            color: #333;
            display: flex;
            align-items: center;
        }
        .github-link img {
            width: 24px; /* 设置 GitHub 图标大小 */
            height: 24px;
            margin-right: 8px;
        }
        .github-link:hover {
            color: #337ab7;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo 图片 -->
        <img src="https://assets.qninq.cn/qning/tYM0Zofg.webp" alt="Logo">
        
        <!-- 登录按钮 -->
        <a href="dlapi.php" class="login-button">Linux.Do登录</a>
    </div>
    
    <!-- GitHub 超链接 -->
    <a href="https://github.com/wang4386/php-oauth-Linux.Do" class="github-link" target="_blank">
        <img src="https://assets.qninq.cn/qning/PmajGbs1.webp" alt="GitHub Logo">
        GitHub
    </a>
</body>
</html>';
    exit;
}

// 主逻辑：检查登录状态，未登录则显示登录页面
if (!checkOAuthLogin()) {
    showLoginPage();
}

// 如果已登录，继续执行原有index.php的代码
// 原有index.php的代码从这里开始
?>

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function (Request $request) {
    if (admin_setting('app_url') && admin_setting('safe_mode_enable', 0)) {
        if ($request->server('HTTP_HOST') !== parse_url(admin_setting('app_url'))['host']) {
            abort(403);
        }
    }

    $theme = admin_setting('frontend_theme', 'Xboard');
    $themeService = new ThemeService();

    try {
        // 检查主题是否存在，不存在则尝试切换到默认主题
        if (!$themeService->exists($theme)) {
            if ($theme !== 'Xboard') {
                Log::warning('Theme not found, switching to default theme', ['theme' => $theme]);
                $theme = 'Xboard';
                admin_setting(['frontend_theme' => $theme]);
            }
            $themeService->switch($theme);
        }

        // 检查主题视图文件是否存在
        if (!$themeService->getThemeViewPath($theme)) {
            throw new Exception('主题视图文件不存在');
        }

        // 检查主题是否已复制到public目录
        $publicThemePath = public_path('theme/' . $theme);
        if (!File::exists($publicThemePath)) {
            $themePath = $themeService->getThemePath($theme);
            if (!$themePath || !File::copyDirectory($themePath, $publicThemePath)) {
                throw new Exception('主题初始化失败');
            }
            Log::info('Theme initialized in public directory', ['theme' => $theme]);
        }

        $renderParams = [
            'title' => admin_setting('app_name', 'Xboard'),
            'theme' => $theme,
            'version' => app(UpdateService::class)->getCurrentVersion(),
            'description' => admin_setting('app_description', 'Xboard is best'),
            'logo' => admin_setting('logo'),
            'theme_config' => $themeService->getConfig($theme)
        ];
        return view('theme::' . $theme . '.dashboard', $renderParams);
    } catch (Exception $e) {
        Log::error('Theme rendering failed', [
            'theme' => $theme,
            'error' => $e->getMessage()
        ]);
        abort(500, '主题加载失败');
    }
});

//TODO:: 兼容
Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))), function () {
    return view('admin', [
        'title' => admin_setting('app_name', 'XBoard'),
        'theme_sidebar' => admin_setting('frontend_theme_sidebar', 'light'),
        'theme_header' => admin_setting('frontend_theme_header', 'dark'),
        'theme_color' => admin_setting('frontend_theme_color', 'default'),
        'background_url' => admin_setting('frontend_background_url'),
        'version' => app(UpdateService::class)->getCurrentVersion(),
        'logo' => admin_setting('logo'),
        'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
});

Route::get('/' . (admin_setting('subscribe_path', 's')) . '/{token}', [\App\Http\Controllers\V1\Client\ClientController::class, 'subscribe'])
    ->middleware('client')
    ->name('client.subscribe');


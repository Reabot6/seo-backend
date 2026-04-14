<?php
use think\facade\Route;

// Import all controllers
use app\controller\SettingsController;
use app\controller\SchedulerController;
use app\controller\ArticleGeneratorController;
use app\controller\OriginalityController;
use app\controller\PublisherController;
use app\controller\IndexerController;
use app\controller\TelegramController;
use app\controller\AuthController;
use app\controller\SitesController;
use app\controller\ArticlesController;
use app\controller\MediaController;
use app\controller\DashboardController;

// ─────────────────────────────────────────
// CORS PREFLIGHT
// ─────────────────────────────────────────
Route::options(':path', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
})->pattern(['path' => '.*']);

// ─────────────────────────────────────────
// ALL ROUTES WITH CORS MIDDLEWARE
// ─────────────────────────────────────────
Route::get('api/test-db', function() {
    try {
        $result = \think\facade\Db::query('SELECT 1 as test');
        return json(['status' => 'connected', 'result' => $result]);
    } catch (\Exception $e) {
        return json(['status' => 'error', 'message' => $e->getMessage()]);
    }
});
Route::group('', function () {

    Route::get('api/article/view', [ArticlesController::class, 'show']);
    Route::delete('api/article/delete', [ArticlesController::class, 'destroyById']);

    // AUTH
    Route::post('api/auth/login',           [AuthController::class, 'login']);
    Route::post('api/auth/verify-2fa',      [AuthController::class, 'verify2fa']);
    Route::post('api/auth/setup-2fa',       [AuthController::class, 'setup2fa']);
    Route::post('api/auth/confirm-2fa',     [AuthController::class, 'confirm2fa']);
    Route::post('api/auth/disable-2fa',     [AuthController::class, 'disable2fa']);
    Route::post('api/auth/logout',          [AuthController::class, 'logout']);
    Route::get('api/auth/me',               [AuthController::class, 'me']);
    Route::post('api/auth/change-password', [AuthController::class, 'changePassword']);

    Route::get('/api/debug-env', function() {
    return json([
        'host'     => env('MYSQL_ADDON_HOST'),
        'db'       => env('MYSQL_ADDON_DB'),
        'user'     => env('MYSQL_ADDON_USER'),
        'port'     => env('MYSQL_ADDON_PORT'),
        'password' => env('MYSQL_ADDON_PASSWORD') ? 'SET' : 'NOT SET',
    ]);
});

    // SETTINGS
    Route::get('api/settings/language',        [SettingsController::class, 'language']);
    Route::get('api/settings/group/indexing',  [SettingsController::class, 'indexingSettings']);
    Route::get('api/settings/group/ai',        [SettingsController::class, 'aiSettings']);
    Route::get('api/settings/group/telegram',  [SettingsController::class, 'telegramSettings']);
    Route::get('api/settings/group/scheduler', [SettingsController::class, 'schedulerSettings']);
    Route::get('api/settings',                 [SettingsController::class, 'index']);
    Route::post('api/settings/single',         [SettingsController::class, 'saveSingle']);
    Route::post('api/settings',                [SettingsController::class, 'save']);

    // SCHEDULER
    Route::get('api/scheduler/run',            [SchedulerController::class, 'run']);

    // ARTICLE GENERATOR
    Route::get('api/article/test-groq',        [ArticleGeneratorController::class, 'testGroq']);
    Route::post('api/article/generate',        [ArticleGeneratorController::class, 'generate']);

    // ORIGINALITY
    Route::post('api/originality/check',       [OriginalityController::class, 'check']);

    // PUBLISHER
    Route::post('api/publisher/publish',       [PublisherController::class, 'publish']);

    // INDEXER
    Route::post('api/indexer/index',           [IndexerController::class, 'index']);

    // TELEGRAM
    Route::post('api/telegram/send',           [TelegramController::class, 'send']);
    Route::post('api/telegram/test',           [TelegramController::class, 'sendTest']);

    // SITES
    Route::get('api/sites/:id/articles',     [SitesController::class, 'articles']);
    Route::get('api/sites',                  [SitesController::class, 'index']);
    Route::post('api/sites',                 [SitesController::class, 'store']);
    Route::put('api/sites/:id',              [SitesController::class, 'update']);
    Route::delete('api/sites/:id',           [SitesController::class, 'destroy']);
    Route::get('api/site-links/:id',         [SitesController::class, 'getLinks']);
    Route::post('api/site-links/:id',        [SitesController::class, 'addLink']);
    Route::delete('api/site-link/del/:id',   [SitesController::class, 'deleteLink']);

    // ARTICLES
    Route::get('api/articles',                    [ArticlesController::class, 'index']);
    Route::get('api/articles/<id:\d+>',           [ArticlesController::class, 'show']);
    Route::get('api/articles/<id:\d+>/debug',     [ArticlesController::class, 'debug']);
    Route::delete('api/articles/<id:\d+>',        [ArticlesController::class, 'destroy']);

    // MEDIA
    Route::get('api/media/stats',          [MediaController::class, 'stats']);
    Route::get('api/media',                [MediaController::class, 'index']);
    Route::post('api/media/upload',        [MediaController::class, 'upload']);
    Route::delete('api/media/:id',         [MediaController::class, 'destroy']);

    // DASHBOARD
    Route::get('api/dashboard/stats',           [DashboardController::class, 'stats']);
    Route::get('api/dashboard/recent-articles', [DashboardController::class, 'recentArticles']);
    Route::get('api/dashboard/logs',            [DashboardController::class, 'logs']);
    Route::get('api/dashboard/chart',           [DashboardController::class, 'chart']);

})->middleware(\app\middleware\CorsMiddleware::class);
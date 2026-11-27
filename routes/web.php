<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BreakGlassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReferralController;
use Illuminate\Support\Facades\Route;

// Public: no patient data, no session needed
Route::get('/health', [HomeController::class, 'health'])->name('health');
Route::get('/up', [HomeController::class, 'health']);
Route::get('/offline', [HomeController::class, 'offline'])->name('offline');
Route::post('/locale', [HomeController::class, 'setGuestLocale'])->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

// Signed in, MFA not yet verified
Route::middleware('auth')->group(function () {
    Route::get('/mfa', [AuthController::class, 'showMfa'])->name('mfa');
    Route::post('/mfa', [AuthController::class, 'verifyMfa'])->middleware('throttle:mfa');
    Route::post('/mfa/recovery', [AuthController::class, 'recovery'])->middleware('throttle:mfa')->name('mfa.recovery');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'mfa'])->group(function () {
    Route::get('/', [HomeController::class, 'home'])->name('home');
    Route::post('/session/keepalive', [AuthController::class, 'keepalive'])->name('keepalive');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::post('/password', [AuthController::class, 'updatePassword'])->name('password.update')->middleware('throttle:mfa');
    Route::get('/reauth', [AuthController::class, 'showReauth'])->name('reauth');
    Route::post('/reauth', [AuthController::class, 'reauth'])->middleware('throttle:mfa');
    Route::get('/notifications', [HomeController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read', [HomeController::class, 'readNotifications'])->name('notifications.read');
    Route::get('/settings', [HomeController::class, 'settings'])->name('settings');
    Route::post('/settings', [HomeController::class, 'updateSettings']);

    // Directory (all roles may look; only own coordinators/admins verify)
    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory')->middleware('throttle:search');
    Route::get('/directory/{facility}', [DirectoryController::class, 'show'])->name('directory.show');
    Route::post('/directory/{facility}/verify', [DirectoryController::class, 'verify'])->name('directory.verify');
    Route::post('/directory/{facility}/services', [DirectoryController::class, 'addService'])->name('directory.service');
    Route::post('/directory/{facility}/report', [DirectoryController::class, 'report'])->name('directory.report')->middleware('role:clinician,coordinator,facility_admin');
    Route::get('/api/v1/directory', [DirectoryController::class, 'snapshot'])->middleware('throttle:api')->name('api.directory');

    // Aggregates
    Route::middleware('role:coordinator,facility_admin,network_analyst,platform_admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
        Route::post('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export')->middleware(['reauth', 'throttle:sensitive']);
    });

    // Clinical work: facility clinicians and coordinators only
    Route::middleware('role:clinician,coordinator')->group(function () {
        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals');
        Route::get('/referrals/new', [ReferralController::class, 'create'])->name('referrals.new');
        Route::post('/referrals', [ReferralController::class, 'store'])->name('referrals.store');
        Route::get('/inbox', [ReferralController::class, 'inbox'])->name('inbox');
        Route::get('/referrals/{id}', [ReferralController::class, 'show'])->name('referrals.show')->where('id', '[0-9a-z]{26}');
        Route::prefix('/referrals/{id}')->where(['id' => '[0-9a-z]{26}'])->group(function () {
            Route::post('/send', [ReferralController::class, 'send'])->name('referrals.send');
            Route::post('/accept', [ReferralController::class, 'accept'])->name('referrals.accept');
            Route::post('/decline', [ReferralController::class, 'decline'])->name('referrals.decline');
            Route::post('/schedule', [ReferralController::class, 'schedule'])->name('referrals.schedule');
            Route::post('/attend', [ReferralController::class, 'attend'])->name('referrals.attend');
            Route::post('/complete', [ReferralController::class, 'complete'])->name('referrals.complete');
            Route::post('/cancel', [ReferralController::class, 'cancel'])->name('referrals.cancel');
            Route::post('/withdraw-consent', [ReferralController::class, 'withdraw'])->name('referrals.withdraw');
            Route::post('/messages', [ReferralController::class, 'message'])->name('referrals.message');
            Route::post('/reveal', [ReferralController::class, 'reveal'])->name('referrals.reveal')->middleware('throttle:reveal');
            Route::post('/hide', [ReferralController::class, 'hide'])->name('referrals.hide');
            Route::post('/documents', [ReferralController::class, 'upload'])->name('referrals.upload')->middleware('throttle:sensitive');
            Route::get('/documents/{doc}', [ReferralController::class, 'download'])->name('referrals.document')->where('doc', '[0-9a-z]{26}')->middleware('throttle:reveal');
            Route::post('/break-glass', [BreakGlassController::class, 'request'])->name('breakglass.request')->middleware(['reauth', 'throttle:sensitive']);
        });
        Route::get('/api/v1/referrals/{id}/fhir', [ReferralController::class, 'fhir'])->name('api.fhir')->where('id', '[0-9a-z]{26}')->middleware('throttle:api');
        Route::get('/api/v1/referrals/{id}/documents/{doc}', [ReferralController::class, 'download'])->where(['id' => '[0-9a-z]{26}', 'doc' => '[0-9a-z]{26}'])->middleware('throttle:reveal');
        Route::get('/data-requests', [Admin\DataRequestController::class, 'index'])->name('data-requests');
        Route::post('/data-requests', [Admin\DataRequestController::class, 'store'])->name('data-requests.store');
    });
    Route::get('/access', [AuditController::class, 'access'])->name('access')->middleware('role:clinician,coordinator,facility_admin');

    // Facility admin
    Route::middleware('role:facility_admin')->group(function () {
        Route::get('/data-requests/review', [Admin\DataRequestController::class, 'index'])->name('data-requests.review');
        Route::post('/data-requests/{dataRequest}/approve', [Admin\DataRequestController::class, 'approve'])->name('data-requests.approve')->middleware(['reauth', 'throttle:sensitive']);
        Route::post('/data-requests/{dataRequest}/reject', [Admin\DataRequestController::class, 'reject'])->name('data-requests.reject')->middleware('reauth');
    });

    // Admin: users (facility admin: own staff; platform admin: everyone)
    Route::middleware('role:facility_admin,platform_admin')->prefix('/admin')->group(function () {
        Route::get('/users', [Admin\UserController::class, 'index'])->name('admin.users');
        Route::middleware(['reauth', 'throttle:sensitive'])->group(function () {
            Route::post('/users', [Admin\UserController::class, 'store'])->name('admin.users.store');
            Route::post('/users/{user}', [Admin\UserController::class, 'update'])->name('admin.users.update');
            Route::post('/users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('admin.users.suspend');
            Route::post('/users/{user}/mfa-reset', [Admin\UserController::class, 'resetMfa'])->name('admin.users.mfa');
            Route::post('/users/{user}/password-reset', [Admin\UserController::class, 'resetPassword'])->name('admin.users.password');
            Route::post('/break-glass/{grant}/review', [BreakGlassController::class, 'review'])->name('admin.breakglass.review');
        });
        Route::get('/break-glass', [BreakGlassController::class, 'index'])->name('admin.breakglass');
    });

    // Platform admin
    Route::middleware('role:platform_admin')->prefix('/admin')->group(function () {
        Route::get('/facilities', [Admin\FacilityController::class, 'index'])->name('admin.facilities');
        Route::get('/audit', [AuditController::class, 'explorer'])->name('admin.audit');
        Route::get('/retention', [Admin\SettingsController::class, 'show'])->name('admin.retention');
        Route::get('/forms', [Admin\SettingsController::class, 'forms'])->name('admin.forms');
        Route::middleware(['reauth', 'throttle:sensitive'])->group(function () {
            Route::post('/facilities', [Admin\FacilityController::class, 'store'])->name('admin.facilities.store');
            Route::post('/facilities/import', [Admin\FacilityController::class, 'import'])->name('admin.facilities.import');
            Route::post('/facilities/{facility}', [Admin\FacilityController::class, 'update'])->name('admin.facilities.update');
            Route::post('/audit/export', [AuditController::class, 'export'])->name('admin.audit.export');
            Route::post('/retention', [Admin\SettingsController::class, 'update'])->name('admin.retention.update');
            Route::post('/retention/purge', [Admin\SettingsController::class, 'purge'])->name('admin.retention.purge');
            Route::post('/holds', [Admin\SettingsController::class, 'hold'])->name('admin.holds');
            Route::post('/holds/{hold}/release', [Admin\SettingsController::class, 'release'])->name('admin.holds.release');
            Route::post('/forms', [Admin\SettingsController::class, 'storeForm'])->name('admin.forms.store');
            Route::post('/forms/{schema}/activate', [Admin\SettingsController::class, 'activateForm'])->name('admin.forms.activate');
        });
    });
});

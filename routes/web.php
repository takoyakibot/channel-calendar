<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\ChannelController as AdminChannelController;
use App\Http\Controllers\Admin\FetchController as AdminFetchController;
use App\Http\Controllers\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\CalendarController;
use App\Models\Group;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [CalendarController::class, 'index']);
Route::view('/privacy', 'legal.privacy');
Route::view('/terms', 'legal.terms');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::resource('channels', AdminChannelController::class)->except(['show']);
    Route::resource('groups', AdminGroupController::class)->except(['show']);
    Route::get('settings', [AdminSettingController::class, 'edit']);
    Route::put('settings', [AdminSettingController::class, 'update']);
    Route::post('settings/test', [AdminSettingController::class, 'test']);
    Route::post('streams/fetch', [AdminFetchController::class, 'fetch']);
    Route::get('users', [AdminUserController::class, 'index']);
    Route::patch('users/{user}/toggle-ban', [AdminUserController::class, 'toggleBan']);
    Route::get('activity-logs', [AdminActivityLogController::class, 'index']);
});

require __DIR__.'/auth.php';

// Public group calendar, one or more slug segments (/aaaa, /aaaa/bbbb, ...).
// Registered last so every named route above wins. Reserved first segments are
// excluded so e.g. POST /register stays a 404 instead of a 405 on this GET route.
Route::get('/{path}', [CalendarController::class, 'show'])
    ->where('path', '(?!(?:' . implode('|', Group::RESERVED_SLUGS) . ')(?:/|$))' . Group::SLUG_PATTERN . '(?:/' . Group::SLUG_PATTERN . ')*');

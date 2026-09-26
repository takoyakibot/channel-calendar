<?php

use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ManualScheduleController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\TweetPreviewController;
use App\Http\Controllers\Api\VideoPostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/streams', [StreamController::class, 'index']);
Route::get('/channels', [ChannelController::class, 'index']);
Route::get('/manual-schedules', [ManualScheduleController::class, 'index']);
Route::get('/video-posts', [VideoPostController::class, 'index']);

// Clips / guest appearances: open to everyone (see issue #51), rate limited per
// IP. "web" so the session (CSRF, and the user when logged in) is available.
Route::middleware('web')->group(function () {
    Route::get('/video-posts/preview', [VideoPostController::class, 'preview'])->middleware('throttle:20,1');
    Route::post('/video-posts', [VideoPostController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('web', 'auth')->group(function () {
    Route::post('/manual-schedules', [ManualScheduleController::class, 'store']);
    Route::delete('/manual-schedules/{manualSchedule}', [ManualScheduleController::class, 'destroy']);
    Route::get('/preferences', [PreferenceController::class, 'show']);
    Route::put('/preferences', [PreferenceController::class, 'update']);
    Route::get('/tweet-preview', [TweetPreviewController::class, 'show'])->middleware('throttle:30,1');
});

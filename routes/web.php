<?php

use App\Http\Controllers\Admin\ChannelController as AdminChannelController;
use App\Http\Controllers\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ProfileController;
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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::resource('channels', AdminChannelController::class)->except(['show']);
    Route::resource('groups', AdminGroupController::class)->except(['show']);
});

require __DIR__.'/auth.php';

// Public group calendar. Registered last so every named route above wins.
// Reserved slugs are excluded from the pattern so e.g. POST /register stays a
// 404 instead of becoming a 405 against this GET route.
Route::get('/{group}', [CalendarController::class, 'show'])
    ->where('group', '(?!(?:' . implode('|', Group::RESERVED_SLUGS) . ')$)[a-z0-9-]+');

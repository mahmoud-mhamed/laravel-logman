<?php

use Illuminate\Support\Facades\Route;
use Mhamed\Logman\LogMan\LogManController;

Route::group([
    'prefix' => config('logman.viewer.route_prefix', 'logman'),
    'middleware' => 'logman',
], function () {
    Route::get('/', [LogManController::class, 'index'])->name('logman.index');
    Route::get('/analysis', [LogManController::class, 'dashboard'])->name('logman.dashboard');
    Route::get('/download', [LogManController::class, 'download'])->name('logman.download');
    Route::post('/delete', [LogManController::class, 'delete'])->name('logman.delete');
    Route::post('/delete-multiple', [LogManController::class, 'deleteMultiple'])->name('logman.delete-multiple');
    Route::post('/clear', [LogManController::class, 'clear'])->name('logman.clear');
    Route::post('/clear-cache', [LogManController::class, 'clearCache'])->name('logman.clear-cache');

    // Review
    Route::post('/review', [LogManController::class, 'review'])->name('logman.review');
    Route::post('/unreview', [LogManController::class, 'unreview'])->name('logman.unreview');

    // Mute
    Route::get('/mutes', [LogManController::class, 'mutes'])->name('logman.mutes');
    Route::post('/mute', [LogManController::class, 'mute'])->name('logman.mute');
    Route::post('/unmute', [LogManController::class, 'unmute'])->name('logman.unmute');
    Route::post('/extend-mute', [LogManController::class, 'extendMute'])->name('logman.extend-mute');
    Route::post('/unmute-all', [LogManController::class, 'unmuteAll'])->name('logman.unmute-all');
    Route::post('/unmute-multiple', [LogManController::class, 'unmuteMultiple'])->name('logman.unmute-multiple');

    // Throttle
    Route::get('/throttles', [LogManController::class, 'throttles'])->name('logman.throttles');
    Route::post('/throttle', [LogManController::class, 'throttle'])->name('logman.throttle');
    Route::post('/unthrottle', [LogManController::class, 'unthrottle'])->name('logman.unthrottle');
    Route::post('/unthrottle-all', [LogManController::class, 'unthrottleAll'])->name('logman.unthrottle-all');
    Route::post('/unthrottle-multiple', [LogManController::class, 'unthrottleMultiple'])->name('logman.unthrottle-multiple');

    // Send to channel
    Route::post('/send-to-channel', [LogManController::class, 'sendToChannel'])->name('logman.send-to-channel');

    // Grouped errors
    Route::get('/grouped', [LogManController::class, 'grouped'])->name('logman.grouped');

    // Export
    Route::get('/export', [LogManController::class, 'export'])->name('logman.export');

    // Bookmarks
    Route::post('/bookmark', [LogManController::class, 'bookmark'])->name('logman.bookmark');
    Route::post('/unbookmark', [LogManController::class, 'unbookmark'])->name('logman.unbookmark');
    Route::post('/clear-bookmarks', [LogManController::class, 'clearBookmarks'])->name('logman.clear-bookmarks');
    Route::get('/bookmarks', [LogManController::class, 'bookmarks'])->name('logman.bookmarks');

    // Config & About
    Route::get('/config', [LogManController::class, 'config'])->name('logman.config');
    Route::get('/about', [LogManController::class, 'about'])->name('logman.about');
});

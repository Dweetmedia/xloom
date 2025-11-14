<?php

use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordingController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Google Drive OAuth routes
    Route::prefix('auth/google')->name('google.')->group(function () {
        Route::get('/redirect', [GoogleDriveController::class, 'redirectToGoogle'])->name('redirect');
        Route::get('/callback', [GoogleDriveController::class, 'handleGoogleCallback'])->name('callback');
        Route::post('/disconnect', [GoogleDriveController::class, 'disconnect'])->name('disconnect');
        Route::get('/status', [GoogleDriveController::class, 'status'])->name('status');
    });

    // Recording routes
    Route::resource('recordings', RecordingController::class);
    Route::get('/recordings/{recording}/status', [RecordingController::class, 'status'])->name('recordings.status');
});

require __DIR__.'/auth.php';

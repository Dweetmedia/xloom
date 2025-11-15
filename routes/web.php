<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordingController;
use App\Models\Recording;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
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
    $recentRecordings = Recording::where('user_id', Auth::id())
        ->latest()
        ->limit(5)
        ->get()
        ->map(function ($recording) {
            return [
                'id' => $recording->id,
                'title' => $recording->title,
                'status' => $recording->status,
                'created_at' => $recording->created_at?->toIso8601String(),
                'recorded_at' => $recording->recorded_at?->toIso8601String(),
            ];
        });

    return Inertia::render('Dashboard', [
        'recentRecordings' => $recentRecordings,
    ]);
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

    // Analytics routes
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/data', [AnalyticsController::class, 'data'])->name('data');
    });
});

require __DIR__.'/auth.php';

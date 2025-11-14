<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class GoogleDriveController extends Controller
{
    public function __construct(
        private GoogleDriveService $googleDriveService
    ) {
        $this->middleware('auth');
    }

    /**
     * Redirect to Google OAuth consent screen.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        $authUrl = $this->googleDriveService->getAuthUrl();
        return redirect()->away($authUrl);
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('dashboard')
                ->with('error', 'Google Drive connection was cancelled.');
        }

        $code = $request->get('code');

        if (!$code) {
            return redirect()->route('dashboard')
                ->with('error', 'Authorization code not provided.');
        }

        try {
            // Exchange authorization code for tokens
            $token = $this->googleDriveService->authenticate($code);

            // Save tokens to user
            $user = Auth::user();
            $this->googleDriveService->saveTokens($user, $token);

            // Create main "Recorded Videos" folder
            $this->googleDriveService->setUser($user);
            $this->googleDriveService->getOrCreateMainFolder();

            return redirect()->route('dashboard')
                ->with('success', 'Google Drive connected successfully!');
        } catch (\Exception $e) {
            Log::error('Google Drive OAuth callback failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect Google Drive. Please try again.');
        }
    }

    /**
     * Disconnect Google Drive.
     */
    public function disconnect(): RedirectResponse
    {
        try {
            $user = Auth::user();
            $this->googleDriveService->disconnect($user);

            return redirect()->route('dashboard')
                ->with('success', 'Google Drive disconnected successfully.');
        } catch (\Exception $e) {
            Log::error('Google Drive disconnect failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to disconnect Google Drive.');
        }
    }

    /**
     * Show Google Drive connection status.
     */
    public function status(): Response
    {
        $user = Auth::user();

        return Inertia::render('GoogleDrive/Status', [
            'connected' => $user->hasGoogleDriveConnected(),
            'folderCreated' => $user->google_drive_folder_id !== null,
        ]);
    }
}

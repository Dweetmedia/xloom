<?php

namespace App\Services;

use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    private GoogleClient $client;
    private ?GoogleDrive $drive = null;
    private User $user;

    /**
     * GoogleDriveService constructor.
     */
    public function __construct(?User $user = null)
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->setScopes([
            GoogleDrive::DRIVE_FILE,
            GoogleDrive::DRIVE_METADATA,
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        if ($user) {
            $this->setUser($user);
        }
    }

    /**
     * Set the user and configure the client with their tokens.
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        if ($user->google_access_token) {
            try {
                $accessToken = Crypt::decryptString($user->google_access_token);
                $this->client->setAccessToken($accessToken);

                // Check if token is expired and refresh if needed
                if ($this->client->isAccessTokenExpired() && $user->google_refresh_token) {
                    $this->refreshAccessToken();
                }

                $this->drive = new GoogleDrive($this->client);
            } catch (\Exception $e) {
                Log::error('Failed to set Google Drive access token', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this;
    }

    /**
     * Get the authorization URL for Google OAuth.
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token.
     */
    public function authenticate(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \Exception('Failed to authenticate: ' . $token['error_description'] ?? $token['error']);
        }

        return $token;
    }

    /**
     * Save the Google Drive tokens to the user.
     */
    public function saveTokens(User $user, array $token): void
    {
        $user->update([
            'google_access_token' => Crypt::encryptString(json_encode([
                'access_token' => $token['access_token'],
                'expires_in' => $token['expires_in'],
                'created' => $token['created'] ?? time(),
            ])),
            'google_refresh_token' => isset($token['refresh_token'])
                ? Crypt::encryptString($token['refresh_token'])
                : $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
            'google_drive_connected' => true,
        ]);
    }

    /**
     * Refresh the access token using the refresh token.
     */
    private function refreshAccessToken(): void
    {
        try {
            $refreshToken = Crypt::decryptString($this->user->google_refresh_token);
            $this->client->refreshToken($refreshToken);
            $newToken = $this->client->getAccessToken();

            $this->saveTokens($this->user, $newToken);
            $this->user->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to refresh Google Drive token', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create or get the main "Recorded Videos" folder.
     */
    public function getOrCreateMainFolder(): string
    {
        if (!$this->drive) {
            throw new \Exception('Google Drive service not initialized');
        }

        // Check if user already has a folder ID
        if ($this->user->google_drive_folder_id) {
            // Verify the folder still exists
            try {
                $this->drive->files->get($this->user->google_drive_folder_id);
                return $this->user->google_drive_folder_id;
            } catch (\Exception $e) {
                // Folder doesn't exist, create a new one
                Log::warning('Google Drive folder not found, creating new one', [
                    'user_id' => $this->user->id,
                    'old_folder_id' => $this->user->google_drive_folder_id,
                ]);
            }
        }

        // Create the main folder
        $fileMetadata = new DriveFile([
            'name' => 'Recorded Videos',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        $folder = $this->drive->files->create($fileMetadata, [
            'fields' => 'id',
        ]);

        $folderId = $folder->id;

        // Save the folder ID to the user
        $this->user->update(['google_drive_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * Create a date-time based subfolder for recordings.
     */
    public function createRecordingFolder(?string $parentFolderId = null): string
    {
        if (!$this->drive) {
            throw new \Exception('Google Drive service not initialized');
        }

        $parentFolderId = $parentFolderId ?? $this->getOrCreateMainFolder();

        // Create folder name based on current date and time
        $folderName = now()->format('Y-m-d_H-i-s');

        $fileMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentFolderId],
        ]);

        $folder = $this->drive->files->create($fileMetadata, [
            'fields' => 'id',
        ]);

        return $folder->id;
    }

    /**
     * Upload a file to Google Drive.
     */
    public function uploadFile(
        string $filePath,
        string $fileName,
        string $folderId,
        string $mimeType = 'video/mp4'
    ): array {
        if (!$this->drive) {
            throw new \Exception('Google Drive service not initialized');
        }

        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $content = file_get_contents($filePath);

        $file = $this->drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink, webContentLink, size',
        ]);

        return [
            'file_id' => $file->id,
            'web_view_link' => $file->webViewLink,
            'web_content_link' => $file->webContentLink ?? null,
            'size' => $file->size ?? filesize($filePath),
        ];
    }

    /**
     * Set file permissions to make it shareable.
     */
    public function setFilePermission(
        string $fileId,
        string $type = 'anyone',
        string $role = 'reader'
    ): void {
        if (!$this->drive) {
            throw new \Exception('Google Drive service not initialized');
        }

        $permission = new Permission([
            'type' => $type, // 'user', 'group', 'domain', 'anyone'
            'role' => $role, // 'reader', 'writer', 'commenter'
        ]);

        $this->drive->permissions->create($fileId, $permission);
    }

    /**
     * Get a shareable link for a file.
     */
    public function getShareableLink(string $fileId): string
    {
        if (!$this->drive) {
            throw new \Exception('Google Drive service not initialized');
        }

        $file = $this->drive->files->get($fileId, [
            'fields' => 'webViewLink',
        ]);

        return $file->webViewLink;
    }

    /**
     * Delete a file from Google Drive.
     */
    public function deleteFile(string $fileId): void
    {
        if (!$this->drive) {
            throw new \Exception('Google Drive service not initialized');
        }

        $this->drive->files->delete($fileId);
    }

    /**
     * Disconnect Google Drive for the user.
     */
    public function disconnect(User $user): void
    {
        $user->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_drive_connected' => false,
        ]);
    }

    /**
     * Check if the service is ready to use.
     */
    public function isReady(): bool
    {
        return $this->drive !== null;
    }
}

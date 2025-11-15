<?php

namespace App\Jobs;

use App\Events\RecordingStatusUpdated;
use App\Models\Recording;
use App\Services\FFmpegService;
use App\Services\GoogleDriveService;
use App\Services\ThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Recording $recording
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(
        FFmpegService $ffmpegService,
        GoogleDriveService $googleDriveService,
        ThumbnailService $thumbnailService
    ): void {
        try {
            Log::info('Starting recording processing', [
                'recording_id' => $this->recording->id,
                'user_id' => $this->recording->user_id,
            ]);

            // Step 1: Convert WebM to MP4
            $this->recording->update(['status' => 'converting']);
            event(new RecordingStatusUpdated($this->recording));

            $mp4Filename = pathinfo($this->recording->original_filename, PATHINFO_FILENAME) . '.mp4';
            $mp4Path = storage_path('app/temp/recordings/' . Str::uuid() . '.mp4');

            $ffmpegService->convertWebMToMp4($this->recording->local_path, $mp4Path);

            Log::info('Video conversion completed', [
                'recording_id' => $this->recording->id,
                'mp4_path' => $mp4Path,
            ]);

            // Step 2: Get video duration
            $duration = $ffmpegService->getVideoDuration($mp4Path);
            $this->recording->update(['duration' => $duration]);

            // Step 3: Generate thumbnail
            $thumbnailPath = $thumbnailService->generateThumbnail($mp4Path, $this->recording->id);

            if ($thumbnailPath) {
                Log::info('Thumbnail generated', [
                    'recording_id' => $this->recording->id,
                    'thumbnail_path' => $thumbnailPath,
                ]);
            }

            // Step 4: Upload to Google Drive
            $this->recording->update(['status' => 'uploading_to_drive']);
            event(new RecordingStatusUpdated($this->recording));

            $user = $this->recording->user;
            $googleDriveService->setUser($user);

            // Create recording folder
            $folderId = $googleDriveService->createRecordingFolder();

            // Upload the MP4 file
            $uploadResult = $googleDriveService->uploadFile(
                $mp4Path,
                $mp4Filename,
                $folderId,
                'video/mp4'
            );

            Log::info('Video uploaded to Google Drive', [
                'recording_id' => $this->recording->id,
                'file_id' => $uploadResult['file_id'],
            ]);

            // Upload thumbnail if generated
            $thumbnailFileId = null;
            if ($thumbnailPath && file_exists($thumbnailPath)) {
                try {
                    $thumbnailResult = $googleDriveService->uploadFile(
                        $thumbnailPath,
                        pathinfo($this->recording->original_filename, PATHINFO_FILENAME) . '_thumbnail.jpg',
                        $folderId,
                        'image/jpeg'
                    );
                    $thumbnailFileId = $thumbnailResult['file_id'];

                    Log::info('Thumbnail uploaded to Google Drive', [
                        'recording_id' => $this->recording->id,
                        'thumbnail_file_id' => $thumbnailFileId,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to upload thumbnail, continuing anyway', [
                        'recording_id' => $this->recording->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Set file permission based on visibility
            if ($this->recording->visibility !== 'private') {
                $googleDriveService->setFilePermission(
                    $uploadResult['file_id'],
                    'anyone',
                    'reader'
                );

                if ($thumbnailFileId) {
                    $googleDriveService->setFilePermission(
                        $thumbnailFileId,
                        'anyone',
                        'reader'
                    );
                }
            }

            // Step 5: Update recording with Google Drive info
            $updateData = [
                'google_drive_file_id' => $uploadResult['file_id'],
                'google_drive_folder_id' => $folderId,
                'google_drive_url' => $uploadResult['web_view_link'],
                'share_link' => $uploadResult['web_view_link'],
                'file_size' => $uploadResult['size'],
                'status' => 'completed',
                'processed_at' => now(),
            ];

            if ($thumbnailFileId) {
                $updateData['thumbnail_google_drive_id'] = $thumbnailFileId;
            }

            $this->recording->update($updateData);
            event(new RecordingStatusUpdated($this->recording));

            // Step 6: Clean up local files
            $this->cleanupLocalFiles($mp4Path, $thumbnailPath);

            Log::info('Recording processing completed successfully', [
                'recording_id' => $this->recording->id,
            ]);

            // Send success notification
            $user->notify(new \App\Notifications\RecordingProcessed($this->recording));

        } catch (\Exception $e) {
            Log::error('Failed to process recording', [
                'recording_id' => $this->recording->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->recording->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            event(new RecordingStatusUpdated($this->recording));

            // Send failure notification
            $this->recording->user->notify(new \App\Notifications\RecordingFailed($this->recording));

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Clean up local files after processing.
     */
    private function cleanupLocalFiles(?string $mp4Path = null, ?string $thumbnailPath = null): void
    {
        try {
            // Delete original WebM file
            if ($this->recording->local_path && file_exists($this->recording->local_path)) {
                unlink($this->recording->local_path);
                Log::info('Deleted original file', [
                    'recording_id' => $this->recording->id,
                    'path' => $this->recording->local_path,
                ]);
            }

            // Delete converted MP4 file
            if ($mp4Path && file_exists($mp4Path)) {
                unlink($mp4Path);
                Log::info('Deleted converted MP4 file', [
                    'recording_id' => $this->recording->id,
                    'path' => $mp4Path,
                ]);
            }

            // Delete thumbnail file
            if ($thumbnailPath && file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
                Log::info('Deleted thumbnail file', [
                    'recording_id' => $this->recording->id,
                    'path' => $thumbnailPath,
                ]);
            }

            $this->recording->update(['local_path' => null]);
        } catch (\Exception $e) {
            Log::warning('Failed to clean up some local files', [
                'recording_id' => $this->recording->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessRecording job failed permanently', [
            'recording_id' => $this->recording->id,
            'error' => $exception->getMessage(),
        ]);

        $this->recording->update([
            'status' => 'failed',
            'error_message' => 'Processing failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);

        // Send failure notification
        $this->recording->user->notify(new \App\Notifications\RecordingFailed($this->recording));
    }
}

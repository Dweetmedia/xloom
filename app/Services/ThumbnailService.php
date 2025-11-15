<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ThumbnailService
{
    /**
     * Generate a thumbnail from a video file.
     *
     * @param string $videoPath Path to the video file
     * @param int|string $recordingId Recording ID for naming
     * @param int $timeInSeconds Time in seconds to capture thumbnail (default: 1)
     * @return string|null Path to generated thumbnail or null on failure
     */
    public function generateThumbnail(string $videoPath, int|string $recordingId, int $timeInSeconds = 1): ?string
    {
        try {
            if (!file_exists($videoPath)) {
                Log::error('Video file not found for thumbnail generation', [
                    'video_path' => $videoPath,
                    'recording_id' => $recordingId,
                ]);
                return null;
            }

            // Create thumbnails directory if it doesn't exist
            $thumbnailDir = storage_path('app/temp/thumbnails');
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Generate thumbnail filename
            $thumbnailFilename = 'thumbnail_' . $recordingId . '_' . Str::uuid() . '.jpg';
            $thumbnailPath = $thumbnailDir . '/' . $thumbnailFilename;

            // FFmpeg command to extract a frame at specified time
            $command = sprintf(
                'ffmpeg -i %s -ss %d -vframes 1 -vf "scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2" %s 2>&1',
                escapeshellarg($videoPath),
                $timeInSeconds,
                escapeshellarg($thumbnailPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($thumbnailPath)) {
                Log::error('Failed to generate thumbnail', [
                    'recording_id' => $recordingId,
                    'video_path' => $videoPath,
                    'command' => $command,
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);
                return null;
            }

            Log::info('Thumbnail generated successfully', [
                'recording_id' => $recordingId,
                'thumbnail_path' => $thumbnailPath,
                'file_size' => filesize($thumbnailPath),
            ]);

            return $thumbnailPath;
        } catch (\Exception $e) {
            Log::error('Exception during thumbnail generation', [
                'recording_id' => $recordingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Generate multiple thumbnails at different timestamps.
     *
     * @param string $videoPath Path to the video file
     * @param int|string $recordingId Recording ID for naming
     * @param int $duration Video duration in seconds
     * @param int $count Number of thumbnails to generate
     * @return array Array of thumbnail paths
     */
    public function generateMultipleThumbnails(
        string $videoPath,
        int|string $recordingId,
        int $duration,
        int $count = 5
    ): array {
        $thumbnails = [];
        $interval = max(1, floor($duration / ($count + 1)));

        for ($i = 1; $i <= $count; $i++) {
            $timestamp = $interval * $i;
            $thumbnail = $this->generateThumbnail($videoPath, $recordingId . '_' . $i, $timestamp);

            if ($thumbnail) {
                $thumbnails[] = $thumbnail;
            }
        }

        return $thumbnails;
    }

    /**
     * Generate a thumbnail sprite sheet (multiple thumbnails in one image).
     *
     * @param string $videoPath Path to the video file
     * @param int|string $recordingId Recording ID for naming
     * @param int $duration Video duration in seconds
     * @param int $columns Number of columns in sprite
     * @param int $rows Number of rows in sprite
     * @return string|null Path to generated sprite sheet or null on failure
     */
    public function generateSpriteSheet(
        string $videoPath,
        int|string $recordingId,
        int $duration,
        int $columns = 5,
        int $rows = 5
    ): ?string {
        try {
            if (!file_exists($videoPath)) {
                Log::error('Video file not found for sprite generation', [
                    'video_path' => $videoPath,
                    'recording_id' => $recordingId,
                ]);
                return null;
            }

            // Create thumbnails directory if it doesn't exist
            $thumbnailDir = storage_path('app/temp/thumbnails');
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Generate sprite filename
            $spriteFilename = 'sprite_' . $recordingId . '_' . Str::uuid() . '.jpg';
            $spritePath = $thumbnailDir . '/' . $spriteFilename;

            $totalFrames = $columns * $rows;
            $interval = max(1, floor($duration / ($totalFrames + 1)));

            // FFmpeg command to create thumbnail sprite sheet
            // This captures frames at intervals and tiles them
            $command = sprintf(
                'ffmpeg -i %s -vf "select=\'not(mod(n\,%d))\',scale=160:90,tile=%dx%d" -frames:v 1 %s 2>&1',
                escapeshellarg($videoPath),
                max(1, floor($duration / $totalFrames * 25)), // Assuming 25 fps
                $columns,
                $rows,
                escapeshellarg($spritePath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($spritePath)) {
                Log::error('Failed to generate sprite sheet', [
                    'recording_id' => $recordingId,
                    'video_path' => $videoPath,
                    'command' => $command,
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);
                return null;
            }

            Log::info('Sprite sheet generated successfully', [
                'recording_id' => $recordingId,
                'sprite_path' => $spritePath,
                'file_size' => filesize($spritePath),
            ]);

            return $spritePath;
        } catch (\Exception $e) {
            Log::error('Exception during sprite sheet generation', [
                'recording_id' => $recordingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Delete thumbnail files.
     *
     * @param string|array $thumbnailPaths Path(s) to thumbnail file(s)
     * @return void
     */
    public function deleteThumbnails(string|array $thumbnailPaths): void
    {
        $paths = is_array($thumbnailPaths) ? $thumbnailPaths : [$thumbnailPaths];

        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                try {
                    unlink($path);
                    Log::info('Thumbnail deleted', ['path' => $path]);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete thumbnail', [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class FFmpegService
{
    /**
     * Convert a WebM video to MP4.
     *
     * @param string $inputPath Path to the input WebM file
     * @param string $outputPath Path to save the output MP4 file
     * @return bool True if conversion was successful
     * @throws \Exception
     */
    public function convertWebMToMp4(string $inputPath, string $outputPath): bool
    {
        if (!file_exists($inputPath)) {
            throw new \Exception("Input file not found: {$inputPath}");
        }

        // Ensure FFmpeg is installed
        if (!$this->isFFmpegInstalled()) {
            throw new \Exception('FFmpeg is not installed or not available in the system PATH');
        }

        // Create the output directory if it doesn't exist
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // FFmpeg command to convert WebM to MP4
        // Using H.264 codec for video and AAC for audio for maximum compatibility
        $command = [
            'ffmpeg',
            '-i', $inputPath,                    // Input file
            '-c:v', 'libx264',                   // Video codec
            '-preset', 'medium',                 // Encoding preset (balance between speed and quality)
            '-crf', '23',                        // Constant Rate Factor (lower = better quality, 23 is default)
            '-c:a', 'aac',                       // Audio codec
            '-b:a', '128k',                      // Audio bitrate
            '-movflags', '+faststart',           // Enable streaming
            '-y',                                // Overwrite output file if exists
            $outputPath,                         // Output file
        ];

        try {
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('FFmpeg conversion failed', [
                    'input' => $inputPath,
                    'output' => $outputPath,
                    'error' => $process->getErrorOutput(),
                ]);
                throw new ProcessFailedException($process);
            }

            // Verify the output file was created
            if (!file_exists($outputPath)) {
                throw new \Exception('FFmpeg process completed but output file was not created');
            }

            Log::info('Video converted successfully', [
                'input' => $inputPath,
                'output' => $outputPath,
                'size' => filesize($outputPath),
            ]);

            return true;
        } catch (ProcessFailedException $e) {
            Log::error('FFmpeg process failed', [
                'error' => $e->getMessage(),
                'input' => $inputPath,
                'output' => $outputPath,
            ]);
            throw $e;
        }
    }

    /**
     * Get video duration in seconds.
     *
     * @param string $filePath Path to the video file
     * @return int Duration in seconds
     * @throws \Exception
     */
    public function getVideoDuration(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        if (!$this->isFFmpegInstalled()) {
            throw new \Exception('FFmpeg is not installed');
        }

        $command = [
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $filePath,
        ];

        try {
            $process = new Process($command);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $duration = (float) trim($process->getOutput());
            return (int) round($duration);
        } catch (\Exception $e) {
            Log::error('Failed to get video duration', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get video metadata.
     *
     * @param string $filePath Path to the video file
     * @return array Video metadata
     */
    public function getVideoMetadata(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $command = [
            'ffprobe',
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $filePath,
        ];

        try {
            $process = new Process($command);
            $process->run();

            if (!$process->isSuccessful()) {
                return [];
            }

            $output = $process->getOutput();
            $metadata = json_decode($output, true);

            return $metadata ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get video metadata', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if FFmpeg is installed and available.
     *
     * @return bool
     */
    public function isFFmpegInstalled(): bool
    {
        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get FFmpeg version.
     *
     * @return string|null
     */
    public function getFFmpegVersion(): ?string
    {
        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                if (preg_match('/ffmpeg version ([\d.]+)/', $output, $matches)) {
                    return $matches[1];
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

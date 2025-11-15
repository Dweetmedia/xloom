<?php

namespace App\Notifications;

use App\Models\Recording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordingProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Recording $recording
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('recordings.show', $this->recording->id);

        return (new MailMessage)
            ->subject('Your Recording is Ready! 🎬')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Great news! Your recording "' . $this->recording->title . '" has been processed successfully.')
            ->line('Your video has been converted and uploaded to Google Drive.')
            ->lineIf($this->recording->duration, 'Duration: ' . $this->formatDuration($this->recording->duration))
            ->lineIf($this->recording->file_size, 'File Size: ' . $this->formatFileSize($this->recording->file_size))
            ->action('View Recording', $url)
            ->line('You can now view, share, or download your recording.')
            ->salutation('Happy recording!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'recording_id' => $this->recording->id,
            'title' => $this->recording->title,
            'status' => 'completed',
            'message' => 'Your recording "' . $this->recording->title . '" has been processed successfully.',
            'action_url' => route('recordings.show', $this->recording->id),
        ];
    }

    /**
     * Format duration in seconds to human-readable format.
     */
    private function formatDuration(?int $seconds): string
    {
        if (!$seconds) {
            return 'N/A';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Format file size in bytes to human-readable format.
     */
    private function formatFileSize(?int $bytes): string
    {
        if (!$bytes) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

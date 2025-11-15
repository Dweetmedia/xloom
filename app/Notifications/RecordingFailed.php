<?php

namespace App\Notifications;

use App\Models\Recording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordingFailed extends Notification implements ShouldQueue
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
        $url = route('recordings.index');
        $supportEmail = config('mail.support_email', 'support@xloom.com');

        return (new MailMessage)
            ->subject('Recording Processing Failed ⚠️')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We encountered an issue while processing your recording "' . $this->recording->title . '".')
            ->line('Error: ' . ($this->recording->error_message ?? 'Unknown error occurred'))
            ->line('Our team has been notified and is investigating the issue.')
            ->action('View All Recordings', $url)
            ->line('You can try uploading the recording again. If the problem persists, please contact our support team.')
            ->line('Support Email: ' . $supportEmail)
            ->salutation('We apologize for the inconvenience.');
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
            'status' => 'failed',
            'error_message' => $this->recording->error_message,
            'message' => 'Failed to process recording "' . $this->recording->title . '".',
            'action_url' => route('recordings.index'),
        ];
    }
}

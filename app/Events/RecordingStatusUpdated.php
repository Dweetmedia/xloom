<?php

namespace App\Events;

use App\Models\Recording;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecordingStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Recording $recording
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recording->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'recording.status.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->recording->id,
            'title' => $this->recording->title,
            'status' => $this->recording->status,
            'error_message' => $this->recording->error_message,
            'progress' => $this->getProgressPercentage(),
            'google_drive_url' => $this->recording->google_drive_url,
            'share_link' => $this->recording->share_link,
            'duration' => $this->recording->duration,
            'file_size' => $this->recording->file_size,
            'updated_at' => $this->recording->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the progress percentage based on status.
     */
    private function getProgressPercentage(): int
    {
        return match ($this->recording->status) {
            'uploading' => 10,
            'converting' => 40,
            'uploading_to_drive' => 70,
            'completed' => 100,
            'failed' => 0,
            default => 0,
        };
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
        'google_drive_folder_id',
        'google_drive_connected',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_access_token',
        'google_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_token_expires_at' => 'datetime',
            'google_drive_connected' => 'boolean',
        ];
    }

    /**
     * Get the recordings for the user.
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class);
    }

    /**
     * Check if the user has connected their Google Drive.
     */
    public function hasGoogleDriveConnected(): bool
    {
        return $this->google_drive_connected &&
               $this->google_access_token !== null;
    }

    /**
     * Check if the Google Drive token is expired.
     */
    public function isGoogleTokenExpired(): bool
    {
        if (!$this->google_token_expires_at) {
            return true;
        }

        return now()->greaterThan($this->google_token_expires_at);
    }
}

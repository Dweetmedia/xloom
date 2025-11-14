# XLoom - Screen Recording SaaS Application

A modern Laravel 12-based SaaS application that allows users to record their screens, convert recordings to MP4 using FFmpeg, and store them in their own Google Drive accounts with organized folder structures.

## Features

- **User Authentication**: Secure user registration and login using Laravel Breeze with React/Inertia
- **Google Drive Integration**: OAuth-based connection to user's Google Drive account
- **Screen Recording**: Browser-based screen recording using MediaRecorder API
- **Video Conversion**: Automatic WebM to MP4 conversion using FFmpeg for maximum compatibility
- **Organized Storage**: Recordings stored in date-time-based subfolders within "Recorded Videos" folder
- **Permission Management**: Control video visibility (private, anyone with link, public)
- **Modern UI**: Clean, responsive React-based interface with Tailwind CSS
- **Shareable Links**: Easy sharing of recordings with customizable permissions

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: React with Inertia.js
- **Styling**: Tailwind CSS
- **Database**: MySQL
- **Video Processing**: FFmpeg
- **Cloud Storage**: Google Drive API
- **Authentication**: Laravel Breeze

## Requirements

### Server Requirements

- PHP >= 8.2
- MySQL >= 8.0
- Composer
- Node.js >= 18.x
- NPM or Yarn
- FFmpeg (for video conversion)

### PHP Extensions Required

- BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PDO (MySQL), Tokenizer, XML

## Quick Start

### 1. Clone and Install

```bash
git clone <repository-url>
cd xloom
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Environment

Update `.env` with your database and Google OAuth credentials:

```env
DB_DATABASE=xloom
DB_USERNAME=root
DB_PASSWORD=your_password

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

### 3. Setup Database

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE xloom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate
```

### 4. Install FFmpeg

**Ubuntu/Debian:**
```bash
sudo apt update && sudo apt install ffmpeg
```

**macOS:**
```bash
brew install ffmpeg
```

**Verify:**
```bash
ffmpeg -version
```

### 5. Build and Run

```bash
# Build frontend assets
npm run build

# Start development server
php artisan serve

# In another terminal, for hot reload:
npm run dev
```

Access at `http://localhost:8000`

## Google Cloud Setup

1. **Create Project** at [Google Cloud Console](https://console.cloud.google.com/)
2. **Enable** Google Drive API
3. **Configure OAuth Consent Screen**:
   - User type: External
   - Add scopes: `drive.file`, `drive.metadata`
4. **Create OAuth 2.0 Credentials**:
   - Type: Web application
   - Add redirect URI: `http://localhost:8000/auth/google/callback`
5. **Copy** Client ID and Secret to `.env`

## Project Structure

```
xloom/
├── app/
│   ├── Http/Controllers/
│   │   ├── GoogleDriveController.php    # OAuth & Drive management
│   │   └── RecordingController.php      # Recording CRUD operations
│   ├── Models/
│   │   ├── User.php                     # User model with Google Drive fields
│   │   └── Recording.php                # Recording model
│   ├── Policies/
│   │   └── RecordingPolicy.php          # Authorization logic
│   └── Services/
│       ├── GoogleDriveService.php       # Google Drive API integration
│       └── FFmpegService.php            # Video conversion service
├── database/migrations/
│   ├── *_add_google_drive_fields_to_users_table.php
│   └── *_create_recordings_table.php
└── routes/web.php                       # Application routes
```

## Usage

### Connect Google Drive

1. Register/Login
2. Click "Connect Google Drive"
3. Authorize the app
4. App creates "Recorded Videos" folder

### Record Screen

1. Navigate to "New Recording"
2. Click "Start Recording"
3. Select screen/window
4. Stop when done
5. Add title/description
6. Upload

### Processing Pipeline

1. Upload WebM to server
2. Convert to MP4 via FFmpeg
3. Create date-time folder in Google Drive
4. Upload MP4 to Drive
5. Set permissions
6. Clean up local files

## API Endpoints

### Authentication
- `POST /register`, `/login`, `/logout`

### Google Drive
- `GET /auth/google/redirect` - OAuth redirect
- `GET /auth/google/callback` - OAuth callback
- `POST /auth/google/disconnect` - Disconnect
- `GET /auth/google/status` - Connection status

### Recordings
- `GET /recordings` - List recordings
- `POST /recordings` - Upload recording
- `GET /recordings/{id}` - Show recording
- `PUT /recordings/{id}` - Update recording
- `DELETE /recordings/{id}` - Delete recording
- `GET /recordings/{id}/status` - Processing status

## Security Features

✅ CSRF Protection
✅ Mass Assignment Protection
✅ Input Validation (Form Requests)
✅ SQL Injection Prevention (Eloquent ORM)
✅ XSS Protection (Auto-escaping)
✅ Token Encryption (Google tokens)
✅ Authorization (Policies)
✅ File Upload Validation
✅ Environment Variables (.env)

## Performance Optimization

### Queue Setup (Recommended)

```env
QUEUE_CONNECTION=database
```

```bash
php artisan queue:work --tries=3
```

### Production Optimization

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Troubleshooting

**FFmpeg Not Found:**
- Verify: `ffmpeg -version`
- Add to PATH
- Restart server

**File Upload Size Limit:**
Update `php.ini`:
```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
```

**Permission Errors:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Contributing

1. Fork repository
2. Create feature branch: `git checkout -b feature/name`
3. Commit: `git commit -am 'Add feature'`
4. Push: `git push origin feature/name`
5. Submit pull request

Follow PSR-12 standards. Use Laravel Pint:
```bash
./vendor/bin/pint
```

## License

MIT License

## Acknowledgments

- Laravel Framework
- Google Drive API
- FFmpeg
- React & Inertia.js communities

---

**Built with Laravel 12** | **Powered by FFmpeg** | **Stores on Google Drive**

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecordingRequest;
use App\Models\Recording;
use App\Services\FFmpegService;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecordingController extends Controller
{
    public function __construct(
        private FFmpegService $ffmpegService,
        private GoogleDriveService $googleDriveService
    ) {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the user's recordings.
     */
    public function index(): Response
    {
        $recordings = Auth::user()
            ->recordings()
            ->latest()
            ->paginate(15);

        return Inertia::render('Recordings/Index', [
            'recordings' => $recordings,
            'googleDriveConnected' => Auth::user()->hasGoogleDriveConnected(),
        ]);
    }

    /**
     * Show the form for creating a new recording.
     */
    public function create(): Response
    {
        $user = Auth::user();

        if (!$user->hasGoogleDriveConnected()) {
            return Inertia::render('Recordings/ConnectDrive');
        }

        return Inertia::render('Recordings/Create');
    }

    /**
     * Store a newly created recording.
     */
    public function store(StoreRecordingRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasGoogleDriveConnected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please connect your Google Drive first.',
                ], 400);
            }

            // Validate the uploaded file
            $file = $request->file('video');
            $originalFilename = $file->getClientOriginalName();

            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $webmPath = storage_path('app/temp/recordings/' . $filename);

            // Ensure directory exists
            $dir = dirname($webmPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Move uploaded file
            $file->move($dir, $filename);

            // Create recording record
            $recording = Recording::create([
                'user_id' => $user->id,
                'title' => $request->input('title', 'Untitled Recording'),
                'description' => $request->input('description'),
                'original_filename' => $originalFilename,
                'local_path' => $webmPath,
                'status' => 'converting',
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'recorded_at' => now(),
            ]);

            // Process in background (you could use Laravel queues for this)
            $this->processRecording($recording);

            return response()->json([
                'success' => true,
                'message' => 'Recording uploaded successfully and is being processed.',
                'recording' => $recording,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store recording', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload recording. Please try again.',
            ], 500);
        }
    }

    /**
     * Process the recording (convert and upload to Google Drive).
     */
    private function processRecording(Recording $recording): void
    {
        try {
            // Convert WebM to MP4
            $mp4Filename = pathinfo($recording->original_filename, PATHINFO_FILENAME) . '.mp4';
            $mp4Path = storage_path('app/temp/recordings/' . Str::uuid() . '.mp4');

            $this->ffmpegService->convertWebMToMp4($recording->local_path, $mp4Path);

            // Get video duration
            $duration = $this->ffmpegService->getVideoDuration($mp4Path);
            $recording->update(['duration' => $duration]);

            // Update status
            $recording->update(['status' => 'uploading_to_drive']);

            // Upload to Google Drive
            $user = $recording->user;
            $this->googleDriveService->setUser($user);

            // Create recording folder
            $folderId = $this->googleDriveService->createRecordingFolder();

            // Upload the MP4 file
            $uploadResult = $this->googleDriveService->uploadFile(
                $mp4Path,
                $mp4Filename,
                $folderId,
                'video/mp4'
            );

            // Set file permission based on visibility
            if ($recording->visibility !== 'private') {
                $this->googleDriveService->setFilePermission(
                    $uploadResult['file_id'],
                    'anyone',
                    'reader'
                );
            }

            // Update recording with Google Drive info
            $recording->update([
                'google_drive_file_id' => $uploadResult['file_id'],
                'google_drive_folder_id' => $folderId,
                'google_drive_url' => $uploadResult['web_view_link'],
                'share_link' => $uploadResult['web_view_link'],
                'file_size' => $uploadResult['size'],
                'status' => 'completed',
            ]);

            // Clean up local files
            if (file_exists($recording->local_path)) {
                unlink($recording->local_path);
            }
            if (file_exists($mp4Path)) {
                unlink($mp4Path);
            }

            $recording->update(['local_path' => null]);
        } catch (\Exception $e) {
            Log::error('Failed to process recording', [
                'recording_id' => $recording->id,
                'error' => $e->getMessage(),
            ]);

            $recording->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified recording.
     */
    public function show(Recording $recording): Response
    {
        $this->authorize('view', $recording);

        return Inertia::render('Recordings/Show', [
            'recording' => $recording->load('user'),
        ]);
    }

    /**
     * Update the specified recording.
     */
    public function update(Request $request, Recording $recording): JsonResponse
    {
        $this->authorize('update', $recording);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'visibility' => 'sometimes|in:private,anyone_with_link,public',
        ]);

        $recording->update($validated);

        // Update Google Drive permissions if visibility changed
        if (isset($validated['visibility']) && $recording->google_drive_file_id) {
            try {
                $this->googleDriveService->setUser($recording->user);

                if ($validated['visibility'] === 'private') {
                    // Remove public permission (would need to implement permission removal)
                } else {
                    $this->googleDriveService->setFilePermission(
                        $recording->google_drive_file_id,
                        'anyone',
                        'reader'
                    );
                }
            } catch (\Exception $e) {
                Log::error('Failed to update Google Drive permissions', [
                    'recording_id' => $recording->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Recording updated successfully.',
            'recording' => $recording->fresh(),
        ]);
    }

    /**
     * Remove the specified recording.
     */
    public function destroy(Recording $recording): JsonResponse
    {
        $this->authorize('delete', $recording);

        try {
            // Delete from Google Drive
            if ($recording->google_drive_file_id) {
                $this->googleDriveService->setUser($recording->user);
                $this->googleDriveService->deleteFile($recording->google_drive_file_id);
            }

            // Delete local file if exists
            if ($recording->local_path && file_exists($recording->local_path)) {
                unlink($recording->local_path);
            }

            $recording->delete();

            return response()->json([
                'success' => true,
                'message' => 'Recording deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete recording', [
                'recording_id' => $recording->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete recording.',
            ], 500);
        }
    }

    /**
     * Get the processing status of a recording.
     */
    public function status(Recording $recording): JsonResponse
    {
        $this->authorize('view', $recording);

        return response()->json([
            'status' => $recording->status,
            'progress' => $this->getProgressPercentage($recording),
            'error' => $recording->error_message,
        ]);
    }

    /**
     * Get the progress percentage based on status.
     */
    private function getProgressPercentage(Recording $recording): int
    {
        return match ($recording->status) {
            'uploading' => 10,
            'converting' => 40,
            'uploading_to_drive' => 70,
            'completed' => 100,
            'failed' => 0,
            default => 0,
        };
    }
}

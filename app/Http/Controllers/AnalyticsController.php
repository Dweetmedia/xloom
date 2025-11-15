<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the analytics dashboard.
     */
    public function index(): Response
    {
        $user = Auth::user();

        // Get analytics data
        $analytics = $this->getAnalytics($user->id);

        return Inertia::render('Analytics/Dashboard', [
            'analytics' => $analytics,
        ]);
    }

    /**
     * Get analytics data for API.
     */
    public function data(): JsonResponse
    {
        $user = Auth::user();
        $analytics = $this->getAnalytics($user->id);

        return response()->json($analytics);
    }

    /**
     * Get comprehensive analytics data.
     */
    private function getAnalytics(int $userId): array
    {
        $recordings = Recording::where('user_id', $userId);

        return [
            'overview' => $this->getOverviewStats($userId),
            'statusBreakdown' => $this->getStatusBreakdown($userId),
            'storageStats' => $this->getStorageStats($userId),
            'recentActivity' => $this->getRecentActivity($userId),
            'recordingsByDate' => $this->getRecordingsByDate($userId),
            'averageDuration' => $this->getAverageDuration($userId),
            'visibilityBreakdown' => $this->getVisibilityBreakdown($userId),
        ];
    }

    /**
     * Get overview statistics.
     */
    private function getOverviewStats(int $userId): array
    {
        $totalRecordings = Recording::where('user_id', $userId)->count();
        $completedRecordings = Recording::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();
        $failedRecordings = Recording::where('user_id', $userId)
            ->where('status', 'failed')
            ->count();
        $processingRecordings = Recording::where('user_id', $userId)
            ->whereIn('status', ['uploading', 'converting', 'uploading_to_drive'])
            ->count();

        $totalSize = Recording::where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('file_size');

        $totalDuration = Recording::where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('duration');

        return [
            'total_recordings' => $totalRecordings,
            'completed_recordings' => $completedRecordings,
            'failed_recordings' => $failedRecordings,
            'processing_recordings' => $processingRecordings,
            'total_storage_bytes' => $totalSize,
            'total_duration_seconds' => $totalDuration,
            'success_rate' => $totalRecordings > 0
                ? round(($completedRecordings / $totalRecordings) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get status breakdown.
     */
    private function getStatusBreakdown(int $userId): array
    {
        $breakdown = Recording::where('user_id', $userId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            })
            ->toArray();

        return $breakdown;
    }

    /**
     * Get storage statistics.
     */
    private function getStorageStats(int $userId): array
    {
        $recordings = Recording::where('user_id', $userId)
            ->where('status', 'completed')
            ->get();

        $totalSize = $recordings->sum('file_size');
        $averageSize = $recordings->count() > 0
            ? $totalSize / $recordings->count()
            : 0;

        $largestRecording = $recordings->sortByDesc('file_size')->first();
        $smallestRecording = $recordings->sortBy('file_size')->where('file_size', '>', 0)->first();

        return [
            'total_bytes' => $totalSize,
            'average_bytes' => round($averageSize),
            'largest_recording' => $largestRecording ? [
                'id' => $largestRecording->id,
                'title' => $largestRecording->title,
                'size' => $largestRecording->file_size,
            ] : null,
            'smallest_recording' => $smallestRecording ? [
                'id' => $smallestRecording->id,
                'title' => $smallestRecording->title,
                'size' => $smallestRecording->file_size,
            ] : null,
        ];
    }

    /**
     * Get recent activity.
     */
    private function getRecentActivity(int $userId): array
    {
        $recentRecordings = Recording::where('user_id', $userId)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($recording) {
                return [
                    'id' => $recording->id,
                    'title' => $recording->title,
                    'status' => $recording->status,
                    'created_at' => $recording->created_at->toIso8601String(),
                    'recorded_at' => $recording->recorded_at?->toIso8601String(),
                ];
            })
            ->toArray();

        return $recentRecordings;
    }

    /**
     * Get recordings grouped by date.
     */
    private function getRecordingsByDate(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $recordingsByDate = Recording::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total' => $item->count,
                    'completed' => $item->completed,
                    'failed' => $item->failed,
                ];
            })
            ->toArray();

        return $recordingsByDate;
    }

    /**
     * Get average duration statistics.
     */
    private function getAverageDuration(int $userId): array
    {
        $recordings = Recording::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('duration')
            ->get();

        if ($recordings->isEmpty()) {
            return [
                'average_seconds' => 0,
                'total_seconds' => 0,
                'longest_recording' => null,
                'shortest_recording' => null,
            ];
        }

        $totalDuration = $recordings->sum('duration');
        $averageDuration = $totalDuration / $recordings->count();

        $longestRecording = $recordings->sortByDesc('duration')->first();
        $shortestRecording = $recordings->sortBy('duration')->where('duration', '>', 0)->first();

        return [
            'average_seconds' => round($averageDuration),
            'total_seconds' => $totalDuration,
            'longest_recording' => $longestRecording ? [
                'id' => $longestRecording->id,
                'title' => $longestRecording->title,
                'duration' => $longestRecording->duration,
            ] : null,
            'shortest_recording' => $shortestRecording ? [
                'id' => $shortestRecording->id,
                'title' => $shortestRecording->title,
                'duration' => $shortestRecording->duration,
            ] : null,
        ];
    }

    /**
     * Get visibility breakdown.
     */
    private function getVisibilityBreakdown(int $userId): array
    {
        $breakdown = Recording::where('user_id', $userId)
            ->select('visibility', DB::raw('count(*) as count'))
            ->groupBy('visibility')
            ->get()
            ->mapWithKeys(function ($item) {
                return [($item->visibility ?? 'private') => $item->count];
            })
            ->toArray();

        // Ensure all visibility options are present
        return array_merge([
            'private' => 0,
            'anyone_with_link' => 0,
            'public' => 0,
        ], $breakdown);
    }
}

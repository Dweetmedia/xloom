import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ analytics }) {
    const formatFileSize = (bytes) => {
        if (!bytes || bytes === 0) return '0 B';
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    };

    const formatDuration = (seconds) => {
        if (!seconds) return '0:00';
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hours > 0) {
            return `${hours}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const StatCard = ({ title, value, subtitle, icon }) => (
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-600">{title}</p>
                    <p className="mt-2 text-3xl font-bold text-gray-900">{value}</p>
                    {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
                </div>
                {icon && <div className="text-4xl text-gray-400">{icon}</div>}
            </div>
        </div>
    );

    const ProgressBar = ({ label, value, total, color = 'blue' }) => {
        const percentage = total > 0 ? (value / total) * 100 : 0;
        const colorClasses = {
            blue: 'bg-blue-600',
            green: 'bg-green-600',
            red: 'bg-red-600',
            yellow: 'bg-yellow-600',
            purple: 'bg-purple-600',
        };

        return (
            <div className="mb-4">
                <div className="flex justify-between text-sm mb-1">
                    <span className="font-medium text-gray-700">{label}</span>
                    <span className="text-gray-600">{value} ({percentage.toFixed(1)}%)</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div
                        className={`h-2.5 rounded-full ${colorClasses[color] || colorClasses.blue}`}
                        style={{ width: `${percentage}%` }}
                    ></div>
                </div>
            </div>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Analytics Dashboard
                    </h2>
                    <Link
                        href={route('recordings.index')}
                        className="text-sm text-blue-600 hover:text-blue-800"
                    >
                        View All Recordings
                    </Link>
                </div>
            }
        >
            <Head title="Analytics" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Overview Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <StatCard
                            title="Total Recordings"
                            value={analytics.overview.total_recordings}
                            subtitle={`${analytics.overview.success_rate}% success rate`}
                            icon="🎬"
                        />
                        <StatCard
                            title="Completed"
                            value={analytics.overview.completed_recordings}
                            subtitle={`${analytics.overview.processing_recordings} processing`}
                            icon="✅"
                        />
                        <StatCard
                            title="Total Storage"
                            value={formatFileSize(analytics.overview.total_storage_bytes)}
                            subtitle={formatFileSize(analytics.storageStats.average_bytes) + ' avg'}
                            icon="💾"
                        />
                        <StatCard
                            title="Total Duration"
                            value={formatDuration(analytics.overview.total_duration_seconds)}
                            subtitle={formatDuration(analytics.averageDuration.average_seconds) + ' avg'}
                            icon="⏱️"
                        />
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Status Breakdown */}
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                Recording Status
                            </h3>
                            <div className="space-y-2">
                                {Object.entries(analytics.statusBreakdown).map(([status, count]) => (
                                    <ProgressBar
                                        key={status}
                                        label={status.replace(/_/g, ' ').toUpperCase()}
                                        value={count}
                                        total={analytics.overview.total_recordings}
                                        color={
                                            status === 'completed' ? 'green' :
                                            status === 'failed' ? 'red' :
                                            status === 'converting' ? 'yellow' :
                                            status === 'uploading_to_drive' ? 'purple' :
                                            'blue'
                                        }
                                    />
                                ))}
                            </div>
                        </div>

                        {/* Visibility Breakdown */}
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                Visibility Settings
                            </h3>
                            <div className="space-y-2">
                                {Object.entries(analytics.visibilityBreakdown).map(([visibility, count]) => (
                                    <ProgressBar
                                        key={visibility}
                                        label={visibility.replace(/_/g, ' ').toUpperCase()}
                                        value={count}
                                        total={analytics.overview.total_recordings}
                                        color={
                                            visibility === 'private' ? 'red' :
                                            visibility === 'anyone_with_link' ? 'yellow' :
                                            'green'
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Storage Stats */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">
                            Storage Statistics
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {analytics.storageStats.largest_recording && (
                                <div>
                                    <p className="text-sm font-medium text-gray-600 mb-2">Largest Recording</p>
                                    <Link
                                        href={route('recordings.show', analytics.storageStats.largest_recording.id)}
                                        className="text-blue-600 hover:text-blue-800 font-medium"
                                    >
                                        {analytics.storageStats.largest_recording.title}
                                    </Link>
                                    <p className="text-sm text-gray-500">
                                        {formatFileSize(analytics.storageStats.largest_recording.size)}
                                    </p>
                                </div>
                            )}
                            {analytics.averageDuration.longest_recording && (
                                <div>
                                    <p className="text-sm font-medium text-gray-600 mb-2">Longest Recording</p>
                                    <Link
                                        href={route('recordings.show', analytics.averageDuration.longest_recording.id)}
                                        className="text-blue-600 hover:text-blue-800 font-medium"
                                    >
                                        {analytics.averageDuration.longest_recording.title}
                                    </Link>
                                    <p className="text-sm text-gray-500">
                                        {formatDuration(analytics.averageDuration.longest_recording.duration)}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Recent Activity */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">
                            Recent Activity
                        </h3>
                        {analytics.recentActivity.length > 0 ? (
                            <div className="space-y-3">
                                {analytics.recentActivity.map((recording) => (
                                    <div
                                        key={recording.id}
                                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                    >
                                        <div className="flex-1">
                                            <Link
                                                href={route('recordings.show', recording.id)}
                                                className="font-medium text-gray-900 hover:text-blue-600"
                                            >
                                                {recording.title}
                                            </Link>
                                            <p className="text-sm text-gray-500">
                                                {new Date(recording.created_at).toLocaleDateString()} at{' '}
                                                {new Date(recording.created_at).toLocaleTimeString()}
                                            </p>
                                        </div>
                                        <span
                                            className={`px-3 py-1 rounded-full text-xs font-medium ${
                                                recording.status === 'completed'
                                                    ? 'bg-green-100 text-green-800'
                                                    : recording.status === 'failed'
                                                    ? 'bg-red-100 text-red-800'
                                                    : 'bg-yellow-100 text-yellow-800'
                                            }`}
                                        >
                                            {recording.status.toUpperCase()}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-gray-500 text-center py-8">No recent activity</p>
                        )}
                    </div>

                    {/* Recordings by Date Chart */}
                    {analytics.recordingsByDate.length > 0 && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                Recordings Over Time (Last 30 Days)
                            </h3>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Date
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Total
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Completed
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Failed
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {analytics.recordingsByDate.map((item) => (
                                            <tr key={item.date}>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {new Date(item.date).toLocaleDateString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {item.total}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                                    {item.completed}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                                    {item.failed}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

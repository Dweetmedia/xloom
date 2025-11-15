import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Dashboard({ recentRecordings = [] }) {
    const { auth } = usePage().props;
    const [recordings, setRecordings] = useState(recentRecordings);

    useEffect(() => {
        // Listen for real-time recording updates via Laravel Echo (WebSockets)
        if (window.Echo) {
            const channel = window.Echo.private(`user.${auth.user.id}`);

            channel.listen('.recording.created', (event) => {
                setRecordings((prev) => [event, ...prev.slice(0, 4)]);
            });

            channel.listen('.recording.status.updated', (event) => {
                setRecordings((prev) =>
                    prev.map((rec) => (rec.id === event.id ? { ...rec, ...event } : rec))
                );
            });

            channel.listen('.recording.deleted', (event) => {
                setRecordings((prev) => prev.filter((rec) => rec.id !== event.id));
            });

            return () => {
                channel.stopListening('.recording.created');
                channel.stopListening('.recording.status.updated');
                channel.stopListening('.recording.deleted');
            };
        }
    }, [auth.user.id]);

    const getStatusBadge = (status) => {
        const badges = {
            uploading: 'bg-blue-100 text-blue-800',
            converting: 'bg-yellow-100 text-yellow-800',
            uploading_to_drive: 'bg-purple-100 text-purple-800',
            completed: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
        };

        return (
            <span
                className={`px-2 py-1 rounded-full text-xs font-medium ${
                    badges[status] || 'bg-gray-100 text-gray-800'
                }`}
            >
                {status?.replace(/_/g, ' ').toUpperCase() || 'UNKNOWN'}
            </span>
        );
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        return date.toLocaleDateString();
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Welcome Section */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="text-2xl font-bold mb-2">
                                Welcome back, {auth.user.name}!
                            </h3>
                            <p className="text-gray-600">
                                Manage your screen recordings, track analytics, and share your content.
                            </p>
                        </div>
                    </div>

                    {/* Quick Actions */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <Link
                            href={route('recordings.create')}
                            className="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg shadow-sm transition-colors"
                        >
                            <div className="text-3xl mb-2">🎥</div>
                            <h4 className="text-lg font-semibold mb-1">New Recording</h4>
                            <p className="text-sm text-blue-100">
                                Start a new screen recording
                            </p>
                        </Link>

                        <Link
                            href={route('recordings.index')}
                            className="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow-sm transition-colors"
                        >
                            <div className="text-3xl mb-2">📁</div>
                            <h4 className="text-lg font-semibold mb-1">My Recordings</h4>
                            <p className="text-sm text-green-100">View all your recordings</p>
                        </Link>

                        <Link
                            href={route('analytics.index')}
                            className="bg-purple-600 hover:bg-purple-700 text-white p-6 rounded-lg shadow-sm transition-colors"
                        >
                            <div className="text-3xl mb-2">📊</div>
                            <h4 className="text-lg font-semibold mb-1">Analytics</h4>
                            <p className="text-sm text-purple-100">
                                View your recording statistics
                            </p>
                        </Link>
                    </div>

                    {/* Recent Recordings */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-xl font-semibold text-gray-900">
                                    Recent Recordings
                                </h3>
                                <Link
                                    href={route('recordings.index')}
                                    className="text-sm text-blue-600 hover:text-blue-800"
                                >
                                    View All →
                                </Link>
                            </div>

                            {recordings.length > 0 ? (
                                <div className="space-y-3">
                                    {recordings.map((recording) => (
                                        <Link
                                            key={recording.id}
                                            href={route('recordings.show', recording.id)}
                                            className="block p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex-1 min-w-0">
                                                    <h4 className="text-base font-medium text-gray-900 truncate">
                                                        {recording.title}
                                                    </h4>
                                                    <p className="text-sm text-gray-500 mt-1">
                                                        {formatDate(recording.created_at || recording.recorded_at)}
                                                    </p>
                                                </div>
                                                <div className="ml-4 flex-shrink-0">
                                                    {getStatusBadge(recording.status)}
                                                </div>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-12">
                                    <div className="text-6xl mb-4">🎬</div>
                                    <p className="text-gray-500 mb-4">
                                        You haven't created any recordings yet.
                                    </p>
                                    <Link
                                        href={route('recordings.create')}
                                        className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                    >
                                        Create Your First Recording
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

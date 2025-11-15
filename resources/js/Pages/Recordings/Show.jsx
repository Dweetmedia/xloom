import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ recording }) {
    const [isDeleting, setIsDeleting] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState({
        title: recording.title,
        description: recording.description || '',
        visibility: recording.visibility || 'private',
    });

    const handleUpdate = (e) => {
        e.preventDefault();
        router.put(route('recordings.update', recording.id), formData, {
            onSuccess: () => {
                setIsEditing(false);
            },
        });
    };

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this recording? This action cannot be undone.')) {
            setIsDeleting(true);
            router.delete(route('recordings.destroy', recording.id), {
                onSuccess: () => {
                    router.visit(route('recordings.index'));
                },
                onError: () => {
                    setIsDeleting(false);
                },
            });
        }
    };

    const copyShareLink = () => {
        if (recording.share_link) {
            navigator.clipboard.writeText(recording.share_link);
            alert('Share link copied to clipboard!');
        }
    };

    const formatFileSize = (bytes) => {
        if (!bytes) return 'N/A';
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    };

    const formatDuration = (seconds) => {
        if (!seconds) return 'N/A';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const getStatusBadge = (status) => {
        const badges = {
            uploading: 'bg-blue-100 text-blue-800',
            converting: 'bg-yellow-100 text-yellow-800',
            uploading_to_drive: 'bg-purple-100 text-purple-800',
            completed: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
        };

        return (
            <span className={`px-3 py-1 rounded-full text-sm font-medium ${badges[status] || 'bg-gray-100 text-gray-800'}`}>
                {status.replace(/_/g, ' ').toUpperCase()}
            </span>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Recording Details
                    </h2>
                    <Link
                        href={route('recordings.index')}
                        className="text-sm text-blue-600 hover:text-blue-800"
                    >
                        Back to Recordings
                    </Link>
                </div>
            }
        >
            <Head title={recording.title} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Status Banner */}
                    {recording.status !== 'completed' && (
                        <div className="mb-6 bg-white shadow-sm sm:rounded-lg p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900">Processing Status</h3>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Your recording is being processed. This page will update automatically.
                                    </p>
                                </div>
                                {getStatusBadge(recording.status)}
                            </div>
                            {recording.error_message && (
                                <div className="mt-4 p-4 bg-red-50 rounded-md">
                                    <p className="text-sm text-red-800">
                                        <strong>Error:</strong> {recording.error_message}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Video Player */}
                    {recording.status === 'completed' && recording.google_drive_file_id && (
                        <div className="mb-6 bg-white shadow-sm sm:rounded-lg overflow-hidden">
                            <div className="aspect-video bg-black">
                                <iframe
                                    src={`https://drive.google.com/file/d/${recording.google_drive_file_id}/preview`}
                                    className="w-full h-full"
                                    allow="autoplay"
                                    title={recording.title}
                                />
                            </div>
                        </div>
                    )}

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Main Content */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Recording Info */}
                            <div className="bg-white shadow-sm sm:rounded-lg p-6">
                                {!isEditing ? (
                                    <>
                                        <div className="flex justify-between items-start mb-4">
                                            <div>
                                                <h3 className="text-2xl font-bold text-gray-900">{recording.title}</h3>
                                                {recording.description && (
                                                    <p className="mt-2 text-gray-600">{recording.description}</p>
                                                )}
                                            </div>
                                            <button
                                                onClick={() => setIsEditing(true)}
                                                className="text-sm text-blue-600 hover:text-blue-800"
                                            >
                                                Edit
                                            </button>
                                        </div>
                                    </>
                                ) : (
                                    <form onSubmit={handleUpdate} className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Title</label>
                                            <input
                                                type="text"
                                                value={formData.title}
                                                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Description</label>
                                            <textarea
                                                value={formData.description}
                                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                                rows={3}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Visibility</label>
                                            <select
                                                value={formData.visibility}
                                                onChange={(e) => setFormData({ ...formData, visibility: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            >
                                                <option value="private">Private</option>
                                                <option value="anyone_with_link">Anyone with link</option>
                                                <option value="public">Public</option>
                                            </select>
                                        </div>
                                        <div className="flex gap-2">
                                            <button
                                                type="submit"
                                                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                            >
                                                Save Changes
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setIsEditing(false)}
                                                className="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                )}

                                <div className="mt-6 grid grid-cols-2 gap-4 pt-6 border-t">
                                    <div>
                                        <p className="text-sm text-gray-500">Duration</p>
                                        <p className="text-lg font-semibold">{formatDuration(recording.duration)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500">File Size</p>
                                        <p className="text-lg font-semibold">{formatFileSize(recording.file_size)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500">Recorded</p>
                                        <p className="text-lg font-semibold">
                                            {new Date(recording.recorded_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500">Visibility</p>
                                        <p className="text-lg font-semibold capitalize">
                                            {recording.visibility?.replace(/_/g, ' ') || 'Private'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Actions */}
                            <div className="bg-white shadow-sm sm:rounded-lg p-6">
                                <h4 className="font-semibold text-gray-900 mb-4">Actions</h4>
                                <div className="space-y-2">
                                    {recording.google_drive_url && (
                                        <a
                                            href={recording.google_drive_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="block w-full px-4 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700"
                                        >
                                            Open in Google Drive
                                        </a>
                                    )}
                                    {recording.share_link && (
                                        <button
                                            onClick={copyShareLink}
                                            className="block w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                                        >
                                            Copy Share Link
                                        </button>
                                    )}
                                    <button
                                        onClick={handleDelete}
                                        disabled={isDeleting}
                                        className="block w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                                    >
                                        {isDeleting ? 'Deleting...' : 'Delete Recording'}
                                    </button>
                                </div>
                            </div>

                            {/* Technical Details */}
                            <div className="bg-white shadow-sm sm:rounded-lg p-6">
                                <h4 className="font-semibold text-gray-900 mb-4">Technical Details</h4>
                                <dl className="space-y-2 text-sm">
                                    <div>
                                        <dt className="text-gray-500">Original Filename</dt>
                                        <dd className="font-medium break-all">{recording.original_filename}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-500">MIME Type</dt>
                                        <dd className="font-medium">{recording.mime_type || 'N/A'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-500">Status</dt>
                                        <dd className="font-medium">{getStatusBadge(recording.status)}</dd>
                                    </div>
                                    {recording.google_drive_file_id && (
                                        <div>
                                            <dt className="text-gray-500">Google Drive ID</dt>
                                            <dd className="font-mono text-xs break-all">{recording.google_drive_file_id}</dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

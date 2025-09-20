import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, SlackFile, PaginatedData } from '@/types';
import { formatFileSize, formatDate } from '@/utils/helpers';
import FileUploadModal from '@/Components/FileUploadModal';
import FileCard from '@/Components/FileCard';
import SearchForm from '@/Components/SearchForm';

interface FilesIndexProps extends PageProps {
    files: PaginatedData<SlackFile>;
    filters: {
        search?: string;
        channel_id?: string;
        file_type?: string;
        date_from?: string;
        date_to?: string;
    };
}

export default function FilesIndex({ auth, files, filters }: FilesIndexProps) {
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [selectedFiles, setSelectedFiles] = useState<number[]>([]);
    const { delete: destroy } = useForm();

    const handleBulkDelete = () => {
        if (selectedFiles.length === 0) return;

        if (confirm(`Are you sure you want to delete ${selectedFiles.length} selected files?`)) {
            destroy(route('files.bulk-delete'), {
                data: { file_ids: selectedFiles },
                onSuccess: () => {
                    setSelectedFiles([]);
                },
            });
        }
    };

    const toggleFileSelection = (fileId: number) => {
        setSelectedFiles(prev => 
            prev.includes(fileId) 
                ? prev.filter(id => id !== fileId)
                : [...prev, fileId]
        );
    };

    const toggleAllFiles = () => {
        setSelectedFiles(
            selectedFiles.length === files.data.length 
                ? [] 
                : files.data.map(file => file.id)
        );
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Files
                    </h2>
                    <div className="flex space-x-2">
                        {selectedFiles.length > 0 && (
                            <button
                                onClick={handleBulkDelete}
                                className="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded"
                            >
                                Delete Selected ({selectedFiles.length})
                            </button>
                        )}
                        <button
                            onClick={() => setShowUploadModal(true)}
                            className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                        >
                            Upload File
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Files" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Search and Filter Form */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div className="p-6">
                            <SearchForm 
                                filters={filters}
                                searchPlaceholder="Search files..."
                                additionalFilters={
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                File Type
                                            </label>
                                            <select
                                                name="file_type"
                                                defaultValue={filters.file_type || ''}
                                                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">All Types</option>
                                                <option value="image">Images</option>
                                                <option value="video">Videos</option>
                                                <option value="audio">Audio</option>
                                                <option value="application">Documents</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                From Date
                                            </label>
                                            <input
                                                type="date"
                                                name="date_from"
                                                defaultValue={filters.date_from || ''}
                                                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                To Date
                                            </label>
                                            <input
                                                type="date"
                                                name="date_to"
                                                defaultValue={filters.date_to || ''}
                                                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        </div>
                                    </div>
                                }
                            />
                        </div>
                    </div>

                    {/* File Grid */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {files.data.length > 0 ? (
                                <>
                                    {/* Bulk Selection Header */}
                                    <div className="flex items-center justify-between mb-6 pb-4 border-b">
                                        <div className="flex items-center space-x-4">
                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedFiles.length === files.data.length}
                                                    onChange={toggleAllFiles}
                                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="text-sm font-medium text-gray-700">
                                                    Select All
                                                </span>
                                            </label>
                                            {selectedFiles.length > 0 && (
                                                <span className="text-sm text-gray-500">
                                                    {selectedFiles.length} files selected
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-sm text-gray-500">
                                            Showing {files.data.length} of {files.total} files
                                        </div>
                                    </div>

                                    {/* File Grid */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                        {files.data.map((file) => (
                                            <div key={file.id} className="relative">
                                                <div className="absolute top-2 left-2 z-10">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedFiles.includes(file.id)}
                                                        onChange={() => toggleFileSelection(file.id)}
                                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    />
                                                </div>
                                                <FileCard 
                                                    file={file}
                                                    onDelete={() => {
                                                        if (confirm('Are you sure you want to delete this file?')) {
                                                            destroy(route('files.destroy', file.id));
                                                        }
                                                    }}
                                                />
                                            </div>
                                        ))}
                                    </div>

                                    {/* Pagination */}
                                    {files.links && files.links.length > 3 && (
                                        <div className="mt-8 flex justify-center">
                                            <nav className="flex items-center space-x-2">
                                                {files.links.map((link, index) => (
                                                    <div key={index}>
                                                        {link.url ? (
                                                            <a
                                                                href={link.url}
                                                                className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                                    link.active
                                                                        ? 'bg-indigo-500 text-white'
                                                                        : 'bg-white text-gray-500 hover:text-gray-700 border border-gray-300 hover:bg-gray-50'
                                                                }`}
                                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                            />
                                                        ) : (
                                                            <span
                                                                className="px-3 py-2 text-sm font-medium text-gray-300 cursor-not-allowed"
                                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                            />
                                                        )}
                                                    </div>
                                                ))}
                                            </nav>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-12">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No files found</h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Get started by uploading your first file.
                                    </p>
                                    <div className="mt-6">
                                        <button
                                            onClick={() => setShowUploadModal(true)}
                                            className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            Upload File
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* File Upload Modal */}
            {showUploadModal && (
                <FileUploadModal
                    onClose={() => setShowUploadModal(false)}
                    onSuccess={() => {
                        setShowUploadModal(false);
                        window.location.reload(); // Refresh the page to show the new file
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
import React from 'react';
import { Link } from '@inertiajs/react';
import { SlackFile } from '@/types';
import { formatFileSize, formatDate } from '@/utils/helpers';

interface FileCardProps {
    file: SlackFile;
    onDelete?: () => void;
}

export interface SlackFile {
    id: number;
    name: string;
    mime_type: string;
    size: number;
    url: string;
    created_at: string;

    // 👇 追加
    file_type?: string; // 'image' など
    thumbnail_path?: string;
    original_name?: string;
    is_public?: boolean;
    uploaded_at?: string;

    user?: {
        id: number;
        name: string;
        display_name?: string;
        real_name?: string;
        avatar_url?: string;
    };

    channel?: {
        id: number;
        name: string;
        workspace_id?: number;
    };
}

export default function FileCard({ file, onDelete }: FileCardProps) {
    const getFileTypeIcon = (fileType: string, mimetype: string) => {
        switch (fileType) {
            case 'image':
                return (
                    <svg className="h-8 w-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clipRule="evenodd" />
                    </svg>
                );
            case 'video':
                return (
                    <svg className="h-8 w-8 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
                    </svg>
                );
            case 'audio':
                return (
                    <svg className="h-8 w-8 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z" />
                    </svg>
                );
            case 'document':
                if (mimetype.includes('pdf')) {
                    return (
                        <svg className="h-8 w-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                        </svg>
                    );
                } else if (mimetype.includes('word') || mimetype.includes('document')) {
                    return (
                        <svg className="h-8 w-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                        </svg>
                    );
                } else if (mimetype.includes('excel') || mimetype.includes('spreadsheet')) {
                    return (
                        <svg className="h-8 w-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                        </svg>
                    );
                }
                return (
                    <svg className="h-8 w-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                    </svg>
                );
            case 'archive':
                return (
                    <svg className="h-8 w-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
                        <path fillRule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clipRule="evenodd" />
                    </svg>
                );
            default:
                return (
                    <svg className="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                    </svg>
                );
        }
    };

    const getThumbnailUrl = () => {
        if (file.file_type === 'image' && file.local_file_url) {
            return file.local_file_url;
        }
        return null;
    };

    const thumbnailUrl = getThumbnailUrl();

    return (
        <div className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
            {/* File Preview */}
            <div className="aspect-w-16 aspect-h-12 bg-gray-100">
                {thumbnailUrl ? (
                    <img
                        src={thumbnailUrl}
                        alt={file.name}
                        className="w-full h-32 object-cover"
                        onError={(e) => {
                            // Fallback to icon if thumbnail fails to load
                            const target = e.target as HTMLImageElement;
                            target.style.display = 'none';
                            target.nextElementSibling?.classList.remove('hidden');
                        }}
                    />
                ) : null}
                <div className={`flex items-center justify-center h-32 ${thumbnailUrl ? 'hidden' : ''}`}>
                    {getFileTypeIcon(file.file_type, file.mimetype)}
                </div>
            </div>

            {/* File Info */}
            <div className="p-4">
                <div className="flex items-start justify-between">
                    <div className="flex-1 min-w-0">
                        <h3 className="text-sm font-medium text-gray-900 truncate">
                            <Link
                                href={route('files.show', file.id)}
                                className="hover:text-indigo-600"
                            >
                                {file.name || file.original_name}
                            </Link>
                        </h3>
                        <div className="mt-1 flex items-center text-xs text-gray-500 space-x-2">
                            <span>{formatFileSize(file.size)}</span>
                            <span>•</span>
                            <span>{file.file_type}</span>
                            {file.is_public && (
                                <>
                                    <span>•</span>
                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Public
                                    </span>
                                </>
                            )}
                        </div>
                    </div>

                    {/* Actions Dropdown */}
                    <div className="relative ml-2">
                        <div className="dropdown dropdown-end">
                            <label tabIndex={0} className="btn btn-ghost btn-sm p-1">
                                <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                </svg>
                            </label>
                            <ul tabIndex={0} className="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-40 z-10">
                                <li>
                                    <Link href={route('files.show', file.id)}>
                                        View
                                    </Link>
                                </li>
                                <li>
                                    <a href={route('files.download', file.id)} target="_blank">
                                        Download
                                    </a>
                                </li>
                                {onDelete && (
                                    <li>
                                        <button
                                            onClick={onDelete}
                                            className="text-red-600 hover:text-red-900"
                                        >
                                            Delete
                                        </button>
                                    </li>
                                )}
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Metadata */}
                <div className="mt-3 space-y-1">
                    {file.user && (
                        <div className="flex items-center text-xs text-gray-500">
                            <svg className="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd" />
                            </svg>
                            <span>{file.user.display_name || file.user.real_name}</span>
                        </div>
                    )}

                    {file.channel && (
                        <div className="flex items-center text-xs text-gray-500">
                            <svg className="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z" />
                            </svg>
                            <span>#{file.channel.name}</span>
                        </div>
                    )}

                    <div className="flex items-center text-xs text-gray-500">
                        <svg className="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clipRule="evenodd" />
                        </svg>
                        <span>{formatDate(file.uploaded_at || file.created_at)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

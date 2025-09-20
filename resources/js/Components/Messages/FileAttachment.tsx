import React, { useState } from 'react';
import Modal from '@/Components/Modal';

interface FileAttachmentData {
    id: string;
    name: string;
    mimetype: string;
    size: number;
    title?: string;
    is_external?: boolean;
}

interface FileAttachmentProps {
    files: FileAttachmentData[];
    className?: string;
    showPreview?: boolean;
    maxPreviewSize?: number;
}

const FileAttachment: React.FC<FileAttachmentProps> = ({
    files,
    className = '',
    showPreview = true,
    maxPreviewSize = 500
}) => {
    const [selectedFile, setSelectedFile] = useState<FileAttachmentData | null>(null);
    const [showModal, setShowModal] = useState(false);

    if (!files || files.length === 0) {
        return null;
    }

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getFileIcon = (mimetype: string, filename: string) => {
        const type = mimetype.split('/')[0];
        const extension = filename.split('.').pop()?.toLowerCase();

        if (type === 'image') {
            return <span className="text-green-500">🖼️</span>;
        }
        if (type === 'video') {
            return <span className="text-red-500">🎥</span>;
        }
        if (type === 'audio') {
            return <span className="text-purple-500">🎵</span>;
        }
        if (extension === 'pdf') {
            return <span className="text-red-600">📄</span>;
        }
        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(extension || '')) {
            return <span className="text-yellow-500">📦</span>;
        }
        return <span className="text-gray-500">📁</span>;
    };

    const isImageFile = (file: FileAttachmentData): boolean => {
        return file.mimetype.startsWith('image/');
    };

    const isVideoFile = (file: FileAttachmentData): boolean => {
        return file.mimetype.startsWith('video/');
    };

    const canPreview = (file: FileAttachmentData): boolean => {
        return isImageFile(file) || isVideoFile(file) || file.mimetype === 'application/pdf';
    };

    const handleFileClick = (file: FileAttachmentData) => {
        if (canPreview(file) && showPreview) {
            setSelectedFile(file);
            setShowModal(true);
        } else {
            // ダウンロード処理
            window.open(`/files/${file.id}/download`, '_blank');
        }
    };

    const renderFilePreview = (file: FileAttachmentData) => {
        if (isImageFile(file)) {
            return (
                <div className="relative group">
                    <img
                        src={`/files/${file.id}`}
                        alt={file.name}
                        className="w-full max-w-md rounded-lg shadow-sm cursor-pointer hover:shadow-md transition-shadow duration-200"
                        style={{ maxHeight: maxPreviewSize }}
                        onClick={() => handleFileClick(file)}
                    />
                </div>
            );
        }

        if (isVideoFile(file)) {
            return (
                <div className="relative">
                    <video
                        controls
                        className="w-full max-w-md rounded-lg shadow-sm"
                        style={{ maxHeight: maxPreviewSize }}
                        preload="metadata"
                    >
                        <source src={`/files/${file.id}`} type={file.mimetype} />
                        お使いのブラウザは動画の再生に対応していません。
                    </video>
                </div>
            );
        }

        return null;
    };

    return (
        <div className={`space-y-3 ${className}`}>
            {files.map((file) => (
                <div key={file.id} className="border border-gray-200 rounded-lg overflow-hidden">
                    {/* ファイルプレビュー */}
                    {renderFilePreview(file)}

                    {/* ファイル情報 */}
                    <div className="p-3 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-3 min-w-0 flex-1">
                                {getFileIcon(file.mimetype, file.name)}
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium text-gray-900 truncate">
                                        {file.title || file.name}
                                    </p>
                                    <div className="flex items-center space-x-2 text-xs text-gray-500">
                                        <span>{formatFileSize(file.size)}</span>
                                        <span>•</span>
                                        <span>{file.mimetype}</span>
                                        {file.is_external && (
                                            <>
                                                <span>•</span>
                                                <span className="text-blue-600">外部ファイル</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center space-x-2">
                                {canPreview(file) && showPreview && (
                                    <button
                                        onClick={() => handleFileClick(file)}
                                        className="p-2 text-gray-400 hover:text-gray-600 rounded-md hover:bg-gray-100"
                                        title="プレビュー"
                                    >
                                        🔍
                                    </button>
                                )}

                                <button
                                    onClick={() => handleFileClick(file)}
                                    className="p-2 text-gray-400 hover:text-blue-600 rounded-md hover:bg-blue-50"
                                    title="ダウンロード"
                                >
                                    ⬇️
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            ))}

            {/* ファイルプレビューモーダル */}
            {selectedFile && (
                <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="4xl">
                    <div className="p-4">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-medium text-gray-900 truncate">
                                {selectedFile.title || selectedFile.name}
                            </h3>
                            <div className="flex items-center space-x-2">
                                <a
                                    href={`/files/${selectedFile.id}/download`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    ⬇️ ダウンロード
                                </a>
                                <button
                                    onClick={() => setShowModal(false)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    ✖
                                </button>
                            </div>
                        </div>

                        <div className="max-h-96 overflow-auto">
                            {isImageFile(selectedFile) && (
                                <img
                                    src={`/files/${selectedFile.id}`}
                                    alt={selectedFile.name}
                                    className="w-full h-auto rounded-lg"
                                />
                            )}

                            {isVideoFile(selectedFile) && (
                                <video
                                    controls
                                    className="w-full h-auto rounded-lg"
                                    autoPlay
                                >
                                    <source src={`/files/${selectedFile.id}`} type={selectedFile.mimetype} />
                                    お使いのブラウザは動画の再生に対応していません。
                                </video>
                            )}
                        </div>

                        <div className="mt-4 text-sm text-gray-600">
                            <div className="flex items-center space-x-4">
                                <span>サイズ: {formatFileSize(selectedFile.size)}</span>
                                <span>形式: {selectedFile.mimetype}</span>
                                {selectedFile.is_external && (
                                    <span className="text-blue-600">外部ファイル</span>
                                )}
                            </div>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default FileAttachment;

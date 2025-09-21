import React, { useState } from 'react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { User, Channel, Workspace } from '@/types';
import FileAttachment from './FileAttachment';

interface ThreadMessage {
    id: number;
    text: string;
    timestamp: string;
    created_at: string;
    message_type?: string;
    has_files: boolean;
    files?: Array<{
        id: string;
        name: string;
        mimetype: string;
        size: number;
        url_private?: string;
        url_private_download?: string;
        thumb_360?: string;
        thumb_720?: string;
    }>;
    reactions?: Array<{ name: string; count: number; users: string[] }>;
    user?: User | null; // null 許容
}

interface ThreadViewProps {
    parentMessage: ThreadMessage;
    threadReplies: ThreadMessage[];
    channel: Channel;
    workspace?: Workspace;
    currentUser: User;
    className?: string;
    onReplySubmit?: (text: string) => void;
    isSubmitting?: boolean;
    showReplyForm?: boolean;
}

const ThreadView: React.FC<ThreadViewProps> = ({
    parentMessage,
    threadReplies,
    channel,
    workspace,
    currentUser,
    className = '',
    onReplySubmit,
    isSubmitting = false,
    showReplyForm = false
}) => {
    const [replyText, setReplyText] = useState('');
    const [showAllReplies, setShowAllReplies] = useState(threadReplies.length <= 10);

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return format(date, 'yyyy/MM/dd HH:mm', { locale: ja });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (replyText.trim() && onReplySubmit) {
            onReplySubmit(replyText.trim());
            setReplyText('');
        }
    };

    const getChannelIcon = () => {
        if (channel.is_dm) return '📧';
        return channel.is_private ? '🔒' : '#';
    };

    const canUserAccessMessage = (message: ThreadMessage) => {
        if (currentUser.is_admin) return true;
        if (message.user?.id === currentUser.id) return true;
        if (!channel.is_private && !channel.is_dm) return true;
        if (channel.is_dm) return true;
        return false;
    };

    const visibleReplies = showAllReplies ? threadReplies : threadReplies.slice(0, 10);
    const hiddenRepliesCount = threadReplies.length - visibleReplies.length;

    return (
        <div className={`bg-white border border-gray-200 rounded-lg ${className}`}>
            {/* スレッドヘッダー */}
            <div className="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <div className="flex items-center space-x-2">
                    <h2 className="text-lg font-semibold text-gray-900">スレッド</h2>
                    <span className="text-sm text-gray-500">
                        {getChannelIcon()}{channel.name}
                    </span>
                    {workspace && (
                        <span className="text-xs text-gray-400">
                            📁 {workspace.name}
                        </span>
                    )}
                </div>
                <div className="text-sm text-gray-500">{threadReplies.length}件の返信</div>
            </div>

            {/* 親メッセージ */}
            <div className="p-6 border-b border-gray-100 bg-blue-50">
                <div className="flex items-start space-x-3">
                    {parentMessage.user?.avatar_url && (
                        <img
                            src={parentMessage.user.avatar_url}
                            alt={parentMessage.user.name}
                            className="w-10 h-10 rounded-full flex-shrink-0"
                        />
                    )}
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center space-x-2 mb-1">
                            <span className="font-semibold text-gray-900">
                                {parentMessage.user?.display_name || parentMessage.user?.name || "Unknown"}
                            </span>
                            <span className="text-sm text-gray-500">
                                {formatDate(parentMessage.created_at)}
                            </span>
                        </div>
                        <div className="text-gray-800 whitespace-pre-wrap leading-relaxed">
                            {parentMessage.text}
                        </div>
                    </div>
                </div>
            </div>

            {/* スレッド返信リスト */}
            <div className="max-h-96 overflow-y-auto">
                {visibleReplies.map((reply) => {
                    const canAccess = canUserAccessMessage(reply);
                    return (
                        <div key={reply.id} className={`px-6 py-4 border-b ${!canAccess ? 'opacity-50 bg-red-50' : ''}`}>
                            <div className="flex items-start space-x-3">
                                {reply.user?.avatar_url && (
                                    <img
                                        src={reply.user.avatar_url}
                                        alt={reply.user?.name || "Unknown"}
                                        className="w-8 h-8 rounded-full flex-shrink-0"
                                    />
                                )}
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center space-x-2 mb-1">
                                        <span className="font-medium text-gray-900 text-sm">
                                            {reply.user?.display_name || reply.user?.name || "Unknown"}
                                        </span>
                                        <span className="text-xs text-gray-500">
                                            {formatDate(reply.created_at)}
                                        </span>
                                    </div>
                                    {canAccess ? (
                                        <div className="text-gray-800 whitespace-pre-wrap text-sm leading-relaxed">
                                            {reply.text}
                                        </div>
                                    ) : (
                                        <div className="text-sm text-red-600 italic">🔒 表示できません</div>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}
                {!showAllReplies && hiddenRepliesCount > 0 && (
                    <div className="px-6 py-4 text-center border-b">
                        <button
                            onClick={() => setShowAllReplies(true)}
                            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                        >
                            さらに{hiddenRepliesCount}件の返信を表示
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};

export default ThreadView;
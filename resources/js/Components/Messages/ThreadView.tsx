import React, { useState, useEffect } from 'react';
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
    user: User;
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
        const now = new Date();
        const diffInHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);

        if (diffInHours < 24) {
            return format(date, 'HH:mm', { locale: ja });
        } else if (diffInHours < 24 * 7) {
            return format(date, 'M月d日 HH:mm', { locale: ja });
        } else {
            return format(date, 'yyyy年M月d日 HH:mm', { locale: ja });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (replyText.trim() && onReplySubmit) {
            onReplySubmit(replyText.trim());
            setReplyText('');
        }
    };

    const getChannelIcon = () => {
        if (channel.is_dm) {
            return '📧';
        }
        return channel.is_private ? '🔒' : '#';
    };

    const canUserAccessMessage = (message: ThreadMessage) => {
        if (currentUser.is_admin) return true;
        
        // 自分のメッセージ
        if (message.user.id === currentUser.id) return true;
        
        // パブリックチャンネル
        if (!channel.is_private && !channel.is_dm) return true;
        
        // DMの場合は参加者のみ（実際にはサーバーサイドでフィルタリング済み）
        if (channel.is_dm) return true;
        
        return false;
    };

    const visibleReplies = showAllReplies ? threadReplies : threadReplies.slice(0, 10);
    const hiddenRepliesCount = threadReplies.length - visibleReplies.length;

    return (
        <div className={`bg-white border border-gray-200 rounded-lg ${className}`}>
            {/* スレッドヘッダー */}
            <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <svg className="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" />
                        </svg>
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
                    
                    <div className="text-sm text-gray-500">
                        {threadReplies.length}件の返信
                    </div>
                </div>
            </div>

            {/* 親メッセージ */}
            <div className="p-6 border-b border-gray-100 bg-blue-50">
                <div className="flex items-start space-x-3">
                    {parentMessage.user.avatar_url && (
                        <img
                            src={parentMessage.user.avatar_url}
                            alt={parentMessage.user.name}
                            className="w-10 h-10 rounded-full flex-shrink-0"
                        />
                    )}
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center space-x-2 mb-1">
                            <span className="font-semibold text-gray-900">
                                {parentMessage.user.display_name || parentMessage.user.name}
                            </span>
                            <span className="text-sm text-gray-500">
                                {formatDate(parentMessage.created_at)}
                            </span>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                スレッド開始
                            </span>
                        </div>
                        
                        <div className="text-gray-800 whitespace-pre-wrap leading-relaxed">
                            {parentMessage.text}
                        </div>

                        {/* 親メッセージのファイル添付 */}
                        {parentMessage.has_files && parentMessage.files && (
                            <div className="mt-3">
                                <FileAttachment files={parentMessage.files} />
                            </div>
                        )}

                        {/* 親メッセージのリアクション */}
                        {parentMessage.reactions && parentMessage.reactions.length > 0 && (
                            <div className="flex flex-wrap gap-1 mt-3">
                                {parentMessage.reactions.map((reaction, index) => (
                                    <span
                                        key={index}
                                        className="inline-flex items-center px-2 py-1 rounded-full text-xs bg-white border border-gray-200 text-gray-700"
                                    >
                                        {reaction.name} {reaction.count}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* スレッド返信リスト */}
            <div className="max-h-96 overflow-y-auto">
                {visibleReplies.map((reply, index) => {
                    const canAccess = canUserAccessMessage(reply);
                    
                    return (
                        <div
                            key={reply.id}
                            className={`px-6 py-4 border-b border-gray-50 hover:bg-gray-50 transition-colors duration-150 ${
                                !canAccess ? 'opacity-50 bg-red-50' : ''
                            }`}
                        >
                            <div className="flex items-start space-x-3">
                                {reply.user.avatar_url && (
                                    <img
                                        src={reply.user.avatar_url}
                                        alt={reply.user.name}
                                        className="w-8 h-8 rounded-full flex-shrink-0"
                                    />
                                )}
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center space-x-2 mb-1">
                                        <span className="font-medium text-gray-900 text-sm">
                                            {reply.user.display_name || reply.user.name}
                                        </span>
                                        <span className="text-xs text-gray-500">
                                            {formatDate(reply.created_at)}
                                        </span>
                                        {reply.user.id === currentUser.id && (
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                自分
                                            </span>
                                        )}
                                    </div>
                                    
                                    {canAccess ? (
                                        <>
                                            <div className="text-gray-800 whitespace-pre-wrap text-sm leading-relaxed">
                                                {reply.text}
                                            </div>

                                            {/* 返信のファイル添付 */}
                                            {reply.has_files && reply.files && (
                                                <div className="mt-2">
                                                    <FileAttachment 
                                                        files={reply.files} 
                                                        maxPreviewSize={300}
                                                    />
                                                </div>
                                            )}

                                            {/* 返信のリアクション */}
                                            {reply.reactions && reply.reactions.length > 0 && (
                                                <div className="flex flex-wrap gap-1 mt-2">
                                                    {reply.reactions.map((reaction, reactionIndex) => (
                                                        <span
                                                            key={reactionIndex}
                                                            className="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600"
                                                        >
                                                            {reaction.name} {reaction.count}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <div className="text-sm text-red-600 italic">
                                            🔒 このメッセージは個人データ制限により表示できません
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}

                {/* 「もっと見る」ボタン */}
                {!showAllReplies && hiddenRepliesCount > 0 && (
                    <div className="px-6 py-4 text-center border-b border-gray-100">
                        <button
                            onClick={() => setShowAllReplies(true)}
                            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                        >
                            さらに{hiddenRepliesCount}件の返信を表示
                        </button>
                    </div>
                )}

                {/* 返信がない場合 */}
                {threadReplies.length === 0 && (
                    <div className="px-6 py-8 text-center text-gray-500">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <p className="text-sm">まだ返信がありません</p>
                        {showReplyForm && (
                            <p className="text-xs mt-1">最初の返信を投稿してみましょう</p>
                        )}
                    </div>
                )}
            </div>

            {/* 返信フォーム（将来拡張用） */}
            {showReplyForm && (
                <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <form onSubmit={handleSubmit} className="space-y-3">
                        <div>
                            <label className="sr-only">返信を投稿</label>
                            <textarea
                                value={replyText}
                                onChange={(e) => setReplyText(e.target.value)}
                                placeholder="このスレッドに返信..."
                                rows={3}
                                className="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                disabled={isSubmitting}
                            />
                        </div>
                        
                        <div className="flex justify-between items-center">
                            <div className="text-xs text-gray-500">
                                💡 Enterで送信、Shift+Enterで改行
                            </div>
                            
                            <div className="flex space-x-2">
                                <button
                                    type="button"
                                    onClick={() => setReplyText('')}
                                    className="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 disabled:opacity-50"
                                    disabled={isSubmitting}
                                >
                                    キャンセル
                                </button>
                                <button
                                    type="submit"
                                    disabled={!replyText.trim() || isSubmitting}
                                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {isSubmitting ? (
                                        <div className="flex items-center">
                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            送信中...
                                        </div>
                                    ) : (
                                        '返信を投稿'
                                    )}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            )}

            {/* スレッド統計 */}
            {threadReplies.length > 0 && (
                <div className="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    <div className="flex justify-between items-center text-xs text-gray-500">
                        <div className="flex items-center space-x-4">
                            <span>返信数: {threadReplies.length}</span>
                            <span>
                                参加者: {
                                    new Set([
                                        parentMessage.user.id,
                                        ...threadReplies.map(r => r.user.id)
                                    ]).size
                                }人
                            </span>
                        </div>
                        <div>
                            最新: {threadReplies.length > 0 && formatDate(threadReplies[threadReplies.length - 1].created_at)}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ThreadView;
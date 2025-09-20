import React from 'react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { Link } from '@inertiajs/react';
import { User, Channel, Workspace } from '@/types';

interface Message {
    id: number;
    text: string;
    timestamp: string;
    created_at: string;
    message_type?: string;
    thread_ts?: string;
    reply_count?: number;
    has_files: boolean;
    reactions?: Array<{ name: string; count: number; users: string[] }>;
    user: User;
    channel: Channel;
    workspace?: Workspace;
}

interface MessageCardProps {
    message: Message;
    showChannel?: boolean;
    showWorkspace?: boolean;
    isRestricted?: boolean;
    searchTerm?: string;
    compact?: boolean;
}

const MessageCard: React.FC<MessageCardProps> = ({
    message,
    showChannel = true,
    showWorkspace = false,
    isRestricted = false,
    searchTerm,
    compact = false
}) => {
    const formatDate = (dateString: string) => {
        return format(new Date(dateString), 'yyyy/MM/dd HH:mm', { locale: ja });
    };

    const highlightSearchTerm = (text: string, term?: string) => {
        if (!term) return text;
        
        const regex = new RegExp(`(${term})`, 'gi');
        const parts = text.split(regex);
        
        return (
            <>
                {parts.map((part, index) =>
                    regex.test(part) ? (
                        <mark key={index} className="bg-yellow-200 px-1 rounded">
                            {part}
                        </mark>
                    ) : (
                        part
                    )
                )}
            </>
        );
    };

    const getChannelIcon = () => {
        if (message.channel.is_dm) {
            return (
                <svg className="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zM7 8H5v2h2V8zm2 0h2v2H9V8zm6 0h-2v2h2V8z" clipRule="evenodd" />
                </svg>
            );
        }
        
        if (message.channel.is_private) {
            return (
                <svg className="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                </svg>
            );
        }
        
        return (
            <svg className="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z" clipRule="evenodd" />
            </svg>
        );
    };

    const cardClasses = `
        ${compact ? 'p-3' : 'p-4'} 
        bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200
        ${isRestricted ? 'border-l-4 border-l-red-500 bg-red-50' : ''}
    `;

    return (
        <div className={cardClasses}>
            {/* ヘッダー部分 */}
            <div className="flex items-start justify-between mb-2">
                <div className="flex items-center space-x-2 min-w-0 flex-1">
                    {/* ユーザー情報 */}
                    <div className="flex items-center space-x-2">
                        {message.user.avatar_url && (
                            <img
                                src={message.user.avatar_url}
                                alt={message.user.name}
                                className="w-8 h-8 rounded-full"
                            />
                        )}
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate">
                                {message.user.display_name || message.user.name}
                            </p>
                            <p className="text-xs text-gray-500">
                                {formatDate(message.created_at)}
                            </p>
                        </div>
                    </div>

                    {/* チャンネル情報 */}
                    {showChannel && (
                        <div className="flex items-center space-x-1 text-sm text-gray-600">
                            {getChannelIcon()}
                            <span className="truncate">
                                {message.channel.name}
                            </span>
                        </div>
                    )}

                    {/* ワークスペース情報 */}
                    {showWorkspace && message.workspace && (
                        <div className="text-xs text-gray-500">
                            📁 {message.workspace.name}
                        </div>
                    )}
                </div>

                {/* メッセージタイプバッジ */}
                {message.message_type && message.message_type !== 'message' && (
                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {message.message_type}
                    </span>
                )}
            </div>

            {/* 制限通知 */}
            {isRestricted && (
                <div className="mb-3 p-2 bg-red-100 border-l-4 border-red-400 text-red-700 text-sm">
                    <div className="flex">
                        <svg className="w-4 h-4 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                        <span>
                            個人データ制限により、一部情報が非表示になっています
                        </span>
                    </div>
                </div>
            )}

            {/* メッセージ本文 */}
            <div className="mb-3">
                <p className="text-gray-800 whitespace-pre-wrap leading-relaxed">
                    {highlightSearchTerm(message.text, searchTerm)}
                </p>
            </div>

            {/* ファイル添付インジケーター */}
            {message.has_files && (
                <div className="flex items-center space-x-1 text-sm text-gray-600 mb-2">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clipRule="evenodd" />
                    </svg>
                    <span>ファイルが添付されています</span>
                </div>
            )}

            {/* リアクション */}
            {message.reactions && message.reactions.length > 0 && (
                <div className="flex flex-wrap gap-1 mb-2">
                    {message.reactions.map((reaction, index) => (
                        <span
                            key={index}
                            className="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700"
                        >
                            {reaction.name} {reaction.count}
                        </span>
                    ))}
                </div>
            )}

            {/* スレッド情報 */}
            {message.thread_ts && message.reply_count && message.reply_count > 0 && (
                <div className="flex items-center space-x-1 text-sm text-blue-600">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" />
                    </svg>
                    <span>{message.reply_count}件の返信</span>
                </div>
            )}

            {/* アクションボタン */}
            <div className="flex justify-between items-center mt-4 pt-2 border-t border-gray-100">
                <div className="flex space-x-2">
                    <Link
                        href={`/messages/${message.id}`}
                        className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                    >
                        詳細を見る
                    </Link>
                    
                    {message.thread_ts && (
                        <Link
                            href={`/messages/${message.id}#thread`}
                            className="text-sm text-gray-600 hover:text-gray-800"
                        >
                            スレッドを見る
                        </Link>
                    )}
                </div>

                <div className="text-xs text-gray-400">
                    ID: {message.id}
                </div>
            </div>
        </div>
    );
};

export default MessageCard;
import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ThreadView from '@/Components/Messages/ThreadView';
import FileAttachment from '@/Components/Messages/FileAttachment';
import ReactionList, { ReactionStats } from '@/Components/Messages/ReactionList';
import PersonalDataBadge from '@/Components/Messages/PersonalDataBadge';
import { PageProps, User, Channel, Workspace } from '@/types';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface Message {
    id: number;
    text: string;
    timestamp: string;
    created_at: string;
    message_type?: string;
    thread_ts?: string;
    reply_count?: number;
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
    channel: Channel;
    workspace?: Workspace;
}

interface ShowProps extends PageProps {
    message: Message;
    threadReplies: Message[];
    channelInfo: {
        id: number;
        name: string;
        is_private: boolean;
        is_dm: boolean;
    };
    allUsers?: Record<string, User>;
    canReply?: boolean;
}

export default function Show({ 
    auth, 
    message, 
    threadReplies, 
    channelInfo, 
    allUsers = {},
    canReply = false
}: ShowProps) {
    const [showFullThread, setShowFullThread] = useState(false);
    const [isSubmittingReply, setIsSubmittingReply] = useState(false);

    // ページタイトル生成
    const getPageTitle = () => {
        const channelName = channelInfo.name;
        const userName = message.user.display_name || message.user.name;
        return `${userName}のメッセージ - #${channelName}`;
    };

    // 日時フォーマット
    const formatDate = (dateString: string) => {
        return format(new Date(dateString), 'yyyy年M月d日 HH:mm', { locale: ja });
    };

    // チャンネルアイコン取得
    const getChannelIcon = () => {
        if (channelInfo.is_dm) {
            return '📧';
        }
        return channelInfo.is_private ? '🔒' : '#';
    };

    // 個人データ制限チェック
    const isRestricted = (msg: Message) => {
        if (auth.user.is_admin) return false;
        
        if (channelInfo.is_private && msg.user.id !== auth.user.id) {
            return true;
        }
        
        if (channelInfo.is_dm && msg.user.id !== auth.user.id) {
            return true;
        }
        
        return false;
    };

    // 返信送信処理（将来拡張用）
    const handleReplySubmit = async (text: string) => {
        if (!canReply) return;
        
        setIsSubmittingReply(true);
        try {
            // ここに実際の返信送信ロジックを実装
            console.log('Reply submitted:', text);
            // await submitReply(message.id, text);
        } catch (error) {
            console.error('Failed to submit reply:', error);
        } finally {
            setIsSubmittingReply(false);
        }
    };

    // リアクションクリック処理（将来拡張用）
    const handleReactionClick = (reactionName: string) => {
        console.log('Reaction clicked:', reactionName);
        // ここに実際のリアクション追加/削除ロジックを実装
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center space-x-3">
                        {/* パンくずナビ */}
                        <nav className="flex items-center space-x-2 text-sm text-gray-600">
                            <Link 
                                href="/messages" 
                                className="hover:text-gray-900 font-medium"
                            >
                                メッセージ一覧
                            </Link>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span className="flex items-center space-x-1">
                                <span>{getChannelIcon()}</span>
                                <span className="font-medium text-gray-900">{channelInfo.name}</span>
                            </span>
                        </nav>
                    </div>
                    
                    {/* ヘッダー右側 */}
                    <div className="flex items-center space-x-3 mt-4 sm:mt-0">
                        <PersonalDataBadge
                            currentUser={auth.user}
                            totalMessages={1}
                            accessibleMessages={isRestricted(message) ? 0 : 1}
                        />
                    </div>
                </div>
            }
        >
            <Head title={getPageTitle()} />

            <div className="py-6">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    {/* メイン メッセージ */}
                    <div className="bg-white shadow-sm border border-gray-200 rounded-lg mb-6">
                        <div className="p-6">
                            {/* 制限警告 */}
                            {isRestricted(message) && (
                                <div className="mb-4 p-3 bg-red-100 border-l-4 border-red-400 text-red-700">
                                    <div className="flex">
                                        <svg className="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                        </svg>
                                        <span>
                                            個人データ制限により、このメッセージの一部情報が非表示になっています
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* メッセージヘッダー */}
                            <div className="flex items-start space-x-4 mb-4">
                                {message.user.avatar_url && (
                                    <img
                                        src={message.user.avatar_url}
                                        alt={message.user.name}
                                        className="w-12 h-12 rounded-full flex-shrink-0"
                                    />
                                )}
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center space-x-3 mb-1">
                                        <h1 className="text-lg font-semibold text-gray-900">
                                            {message.user.display_name || message.user.name}
                                        </h1>
                                        {message.user.id === auth.user.id && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                あなた
                                            </span>
                                        )}
                                        {message.user.is_admin && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                管理者
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center space-x-3 text-sm text-gray-600">
                                        <span>{formatDate(message.created_at)}</span>
                                        <span className="flex items-center space-x-1">
                                            <span>{getChannelIcon()}</span>
                                            <span>{channelInfo.name}</span>
                                        </span>
                                        {message.workspace && (
                                            <span className="flex items-center space-x-1">
                                                <span>📁</span>
                                                <span>{message.workspace.name}</span>
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* メッセージ本文 */}
                            <div className="prose max-w-none mb-4">
                                <div className="text-gray-800 whitespace-pre-wrap leading-relaxed text-base">
                                    {message.text}
                                </div>
                            </div>

                            {/* ファイル添付 */}
                            {message.has_files && message.files && (
                                <div className="mb-4">
                                    <FileAttachment 
                                        files={message.files}
                                        showPreview={true}
                                        maxPreviewSize={600}
                                    />
                                </div>
                            )}

                            {/* リアクション */}
                            {message.reactions && message.reactions.length > 0 && (
                                <div className="space-y-2">
                                    <ReactionList
                                        reactions={message.reactions}
                                        allUsers={allUsers}
                                        currentUser={auth.user}
                                        showUserDetails={true}
                                        interactive={canReply}
                                        onReactionClick={handleReactionClick}
                                    />
                                    <ReactionStats reactions={message.reactions} />
                                </div>
                            )}

                            {/* メッセージ情報 */}
                            <div className="mt-6 pt-4 border-t border-gray-200">
                                <div className="flex justify-between items-center text-sm text-gray-500">
                                    <div className="flex items-center space-x-4">
                                        <span>メッセージID: {message.id}</span>
                                        <span>Timestamp: {message.timestamp}</span>
                                        {message.message_type && message.message_type !== 'message' && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {message.message_type}
                                            </span>
                                        )}
                                    </div>
                                    
                                    <div className="flex items-center space-x-2">
                                        <button
                                            onClick={() => window.history.back()}
                                            className="text-gray-600 hover:text-gray-900"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* スレッド表示 */}
                    {(message.thread_ts || threadReplies.length > 0) && (
                        <div className="mb-6">
                            <ThreadView
                                parentMessage={message}
                                threadReplies={threadReplies}
                                channel={channelInfo}
                                workspace={message.workspace}
                                currentUser={auth.user}
                                showReplyForm={canReply}
                                isSubmitting={isSubmittingReply}
                                onReplySubmit={handleReplySubmit}
                            />
                        </div>
                    )}

                    {/* スレッドが存在しない場合の表示 */}
                    {!message.thread_ts && threadReplies.length === 0 && (
                        <div className="bg-white shadow-sm border border-gray-200 rounded-lg p-6 text-center">
                            <svg className="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                スレッドはありません
                            </h3>
                            <p className="text-gray-600 mb-4">
                                このメッセージにはスレッド返信がありません
                            </p>
                            {canReply && (
                                <button
                                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    onClick={() => setShowFullThread(true)}
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    スレッドを開始
                                </button>
                            )}
                        </div>
                    )}

                    {/* アクションボタン */}
                    <div className="flex justify-center space-x-4 mt-8">
                        <Link
                            href="/messages"
                            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            メッセージ一覧に戻る
                        </Link>
                        
                        {channelInfo && (
                            <Link
                                href={`/messages?channel_id=${channelInfo.id}`}
                                className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                #{channelInfo.name} のメッセージ
                            </Link>
                        )}
                    </div>

                    {/* デバッグ情報（開発時のみ） */}
                    {process.env.NODE_ENV === 'development' && (
                        <div className="mt-8 p-4 bg-gray-100 rounded-lg">
                            <h3 className="text-sm font-medium text-gray-900 mb-2">デバッグ情報</h3>
                            <details className="text-xs text-gray-600">
                                <summary className="cursor-pointer hover:text-gray-900">
                                    メッセージ・スレッド情報を表示
                                </summary>
                                <pre className="mt-2 overflow-auto">
                                    {JSON.stringify({
                                        message: {
                                            id: message.id,
                                            thread_ts: message.thread_ts,
                                            reply_count: message.reply_count,
                                            has_files: message.has_files,
                                            reactions_count: message.reactions?.length || 0
                                        },
                                        threadReplies: {
                                            count: threadReplies.length,
                                            ids: threadReplies.map(r => r.id)
                                        },
                                        channelInfo,
                                        restrictions: {
                                            isRestricted: isRestricted(message),
                                            canReply
                                        }
                                    }, null, 2)}
                                </pre>
                            </details>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
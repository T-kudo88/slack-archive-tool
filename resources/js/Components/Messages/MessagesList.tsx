import React from 'react';
import { Link } from '@inertiajs/react';
import MessageCard from './MessageCard';
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

interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    data: Message[];
}

interface MessagesListProps {
    messages: PaginationData;
    searchTerm?: string;
    isLoading?: boolean;
    showChannel?: boolean;
    showWorkspace?: boolean;
    compact?: boolean;
    currentUser: User;
    emptyStateMessage?: string;
    emptyStateDescription?: string;
}

const MessagesList: React.FC<MessagesListProps> = ({
    messages,
    searchTerm,
    isLoading = false,
    showChannel = true,
    showWorkspace = false,
    compact = false,
    currentUser,
    emptyStateMessage = "メッセージが見つかりませんでした",
    emptyStateDescription = "検索条件を変更するか、別のフィルターを試してください"
}) => {
    const isRestricted = (message: Message) => {
        // 一般ユーザーの場合、アクセス制限があるメッセージかチェック
        if (currentUser.is_admin) return false;
        
        // プライベートチャンネルで自分のメッセージでない場合
        if (message.channel.is_private && message.user.id !== currentUser.id) {
            return true;
        }
        
        // DMチャンネルで自分が参加していない場合
        if (message.channel.is_dm && message.user.id !== currentUser.id) {
            // 実際にはサーバーサイドでフィルタリングされるため、ここには来ないはず
            return true;
        }
        
        return false;
    };

    // ローディング状態
    if (isLoading) {
        return (
            <div className="space-y-4">
                {[...Array(5)].map((_, index) => (
                    <div key={index} className="animate-pulse">
                        <div className="bg-white border border-gray-200 rounded-lg p-4">
                            <div className="flex items-center space-x-3 mb-3">
                                <div className="w-8 h-8 bg-gray-300 rounded-full"></div>
                                <div className="flex-1 space-y-2">
                                    <div className="h-3 bg-gray-300 rounded w-1/4"></div>
                                    <div className="h-2 bg-gray-300 rounded w-1/6"></div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <div className="h-4 bg-gray-300 rounded w-full"></div>
                                <div className="h-4 bg-gray-300 rounded w-3/4"></div>
                                <div className="h-4 bg-gray-300 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    // 空の状態
    if (!messages.data || messages.data.length === 0) {
        return (
            <div className="text-center py-12">
                <div className="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg className="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <h3 className="text-lg font-medium text-gray-900 mb-2">
                    {emptyStateMessage}
                </h3>
                <p className="text-gray-600 mb-6 max-w-md mx-auto">
                    {emptyStateDescription}
                </p>
                
                {searchTerm && (
                    <div className="inline-flex items-center px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-sm">
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        検索キーワード: "{searchTerm}"
                    </div>
                )}
            </div>
        );
    }

    return (
        <div>
            {/* 結果サマリー */}
            <div className="flex justify-between items-center mb-4 text-sm text-gray-600">
                <div>
                    {messages.total > 0 ? (
                        <>
                            <span className="font-medium text-gray-900">
                                {messages.from?.toLocaleString()} - {messages.to?.toLocaleString()}
                            </span>
                            {' '}件 (全{messages.total.toLocaleString()}件中)
                        </>
                    ) : (
                        '0件'
                    )}
                </div>
                
                {searchTerm && (
                    <div className="flex items-center text-blue-600">
                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        "{searchTerm}" で検索中
                    </div>
                )}
            </div>

            {/* メッセージリスト */}
            <div className={`space-y-${compact ? '2' : '4'}`}>
                {messages.data.map((message) => (
                    <MessageCard
                        key={message.id}
                        message={message}
                        showChannel={showChannel}
                        showWorkspace={showWorkspace}
                        isRestricted={isRestricted(message)}
                        searchTerm={searchTerm}
                        compact={compact}
                    />
                ))}
            </div>

            {/* ページネーション */}
            {messages.last_page > 1 && (
                <div className="mt-8">
                    <PaginationComponent messages={messages} />
                </div>
            )}

            {/* パフォーマンス情報（開発時のみ） */}
            {process.env.NODE_ENV === 'development' && (
                <div className="mt-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
                    <div className="flex justify-between">
                        <span>ページサイズ: {messages.per_page}</span>
                        <span>現在ページ: {messages.current_page}/{messages.last_page}</span>
                        <span>メモリ使用量: {messages.data.length} items loaded</span>
                    </div>
                </div>
            )}
        </div>
    );
};

// ページネーションコンポーネント
const PaginationComponent: React.FC<{ messages: PaginationData }> = ({ messages }) => {
    const generatePageNumbers = () => {
        const current = messages.current_page;
        const total = messages.last_page;
        const delta = 2; // 現在ページの前後に表示するページ数
        
        const pages: (number | string)[] = [];
        
        // 最初のページ
        if (current - delta > 2) {
            pages.push(1);
            if (current - delta > 3) {
                pages.push('...');
            }
        }
        
        // 現在ページ周辺
        for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) {
            pages.push(i);
        }
        
        // 最後のページ
        if (current + delta < total - 1) {
            if (current + delta < total - 2) {
                pages.push('...');
            }
            pages.push(total);
        }
        
        return pages;
    };

    const pages = generatePageNumbers();

    return (
        <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-lg">
            <div className="flex flex-1 justify-between sm:hidden">
                {messages.prev_page_url && (
                    <Link
                        href={messages.prev_page_url}
                        className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        前へ
                    </Link>
                )}
                {messages.next_page_url && (
                    <Link
                        href={messages.next_page_url}
                        className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        次へ
                    </Link>
                )}
            </div>
            
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-gray-700">
                        <span className="font-medium">{messages.from?.toLocaleString()}</span>
                        {' '}から{' '}
                        <span className="font-medium">{messages.to?.toLocaleString()}</span>
                        {' '}まで (全{' '}
                        <span className="font-medium">{messages.total.toLocaleString()}</span>
                        {' '}件中)
                    </p>
                </div>
                
                <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        {/* 前へボタン */}
                        {messages.prev_page_url ? (
                            <Link
                                href={messages.prev_page_url}
                                className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0"
                            >
                                <span className="sr-only">前へ</span>
                                <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
                                </svg>
                            </Link>
                        ) : (
                            <span className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-300 ring-1 ring-inset ring-gray-300">
                                <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
                                </svg>
                            </span>
                        )}
                        
                        {/* ページ番号 */}
                        {pages.map((page, index) => {
                            if (page === '...') {
                                return (
                                    <span
                                        key={index}
                                        className="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300"
                                    >
                                        ...
                                    </span>
                                );
                            }
                            
                            const isCurrentPage = page === messages.current_page;
                            const url = new URL(window.location.href);
                            url.searchParams.set('page', page.toString());
                            
                            return (
                                <Link
                                    key={page}
                                    href={url.toString()}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ${
                                        isCurrentPage
                                            ? 'z-10 bg-indigo-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                                            : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'
                                    }`}
                                >
                                    {page}
                                </Link>
                            );
                        })}
                        
                        {/* 次へボタン */}
                        {messages.next_page_url ? (
                            <Link
                                href={messages.next_page_url}
                                className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0"
                            >
                                <span className="sr-only">次へ</span>
                                <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
                                </svg>
                            </Link>
                        ) : (
                            <span className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-300 ring-1 ring-inset ring-gray-300">
                                <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
                                </svg>
                            </span>
                        )}
                    </nav>
                </div>
            </div>
        </div>
    );
};

export default MessagesList;
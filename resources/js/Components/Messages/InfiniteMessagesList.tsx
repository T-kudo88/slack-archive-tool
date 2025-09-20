import React, { useState, useEffect, useCallback, useRef } from 'react';
import { router } from '@inertiajs/react';
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

interface InfiniteMessagesListProps {
    initialMessages: PaginationData;
    searchTerm?: string;
    currentFilters?: Record<string, any>;
    showChannel?: boolean;
    showWorkspace?: boolean;
    compact?: boolean;
    currentUser: User;
    emptyStateMessage?: string;
    emptyStateDescription?: string;
    enableInfiniteScroll?: boolean;
}

const InfiniteMessagesList: React.FC<InfiniteMessagesListProps> = ({
    initialMessages,
    searchTerm,
    currentFilters = {},
    showChannel = true,
    showWorkspace = false,
    compact = false,
    currentUser,
    emptyStateMessage = "メッセージが見つかりませんでした",
    emptyStateDescription = "検索条件を変更するか、別のフィルターを試してください",
    enableInfiniteScroll = true
}) => {
    const [messages, setMessages] = useState<Message[]>(initialMessages.data);
    const [hasNextPage, setHasNextPage] = useState(initialMessages.current_page < initialMessages.last_page);
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(initialMessages.current_page);
    const [totalCount, setTotalCount] = useState(initialMessages.total);

    const observerRef = useRef<IntersectionObserver>();
    const lastMessageElementRef = useCallback((node: HTMLDivElement) => {
        if (isLoadingMore) return;
        if (observerRef.current) observerRef.current.disconnect();
        
        observerRef.current = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting && hasNextPage && enableInfiniteScroll) {
                loadMoreMessages();
            }
        });
        
        if (node) observerRef.current.observe(node);
    }, [isLoadingMore, hasNextPage, enableInfiniteScroll]);

    // 初期データ変更時の処理
    useEffect(() => {
        setMessages(initialMessages.data);
        setHasNextPage(initialMessages.current_page < initialMessages.last_page);
        setCurrentPage(initialMessages.current_page);
        setTotalCount(initialMessages.total);
    }, [initialMessages]);

    const loadMoreMessages = async () => {
        if (!hasNextPage || isLoadingMore) return;

        setIsLoadingMore(true);
        setError(null);

        try {
            const nextPage = currentPage + 1;
            const params = new URLSearchParams({
                ...currentFilters,
                page: nextPage.toString(),
                per_page: initialMessages.per_page.toString()
            });

            const response = await fetch(`/messages?${params.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error(`メッセージの読み込みに失敗しました: ${response.status}`);
            }

            const data = await response.json();
            const newMessages = data.props?.messages?.data || data.messages?.data || [];

            if (newMessages.length > 0) {
                setMessages(prev => [...prev, ...newMessages]);
                setCurrentPage(nextPage);
                setHasNextPage(nextPage < (data.props?.messages?.last_page || data.messages?.last_page || 1));
            } else {
                setHasNextPage(false);
            }

        } catch (error) {
            console.error('Error loading more messages:', error);
            setError(error instanceof Error ? error.message : 'メッセージの読み込み中にエラーが発生しました');
        } finally {
            setIsLoadingMore(false);
        }
    };

    const isRestricted = (message: Message) => {
        if (currentUser.is_admin) return false;
        
        if (message.channel.is_private && message.user.id !== currentUser.id) {
            return true;
        }
        
        if (message.channel.is_dm && message.user.id !== currentUser.id) {
            return true;
        }
        
        return false;
    };

    const handleRetry = () => {
        setError(null);
        loadMoreMessages();
    };

    // 新しい検索やフィルター変更時の処理
    const refreshMessages = () => {
        setMessages([]);
        setCurrentPage(0);
        setHasNextPage(true);
        setIsLoading(true);
        
        // InertiaJSでページリロード
        router.reload({
            only: ['messages'],
            onSuccess: () => setIsLoading(false),
            onError: () => {
                setIsLoading(false);
                setError('メッセージの読み込みに失敗しました');
            }
        });
    };

    // ローディング状態の表示
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
    if (messages.length === 0) {
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

                <button
                    onClick={refreshMessages}
                    className="mt-4 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    再読み込み
                </button>
            </div>
        );
    }

    return (
        <div>
            {/* 結果サマリー */}
            <div className="flex justify-between items-center mb-4 text-sm text-gray-600">
                <div>
                    <span className="font-medium text-gray-900">
                        {messages.length.toLocaleString()}
                    </span>
                    {' '}件表示中
                    {totalCount > messages.length && (
                        <span className="text-gray-500">
                            {' '}(全{totalCount.toLocaleString()}件中)
                        </span>
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

            {/* スクロールポジション表示（デスクトップのみ） */}
            {enableInfiniteScroll && messages.length > 10 && (
                <div className="hidden md:flex justify-center mb-4">
                    <div className="flex items-center space-x-2 px-3 py-1 bg-gray-100 rounded-full text-xs text-gray-600">
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        スクロールして続きを読み込み
                    </div>
                </div>
            )}

            {/* メッセージリスト */}
            <div className={`space-y-${compact ? '2' : '4'}`}>
                {messages.map((message, index) => {
                    const isLast = index === messages.length - 1;
                    
                    return (
                        <div
                            key={message.id}
                            ref={isLast && enableInfiniteScroll ? lastMessageElementRef : undefined}
                            className={`${compact ? 'mb-2' : 'mb-4'} transition-opacity duration-200`}
                        >
                            <MessageCard
                                message={message}
                                showChannel={showChannel}
                                showWorkspace={showWorkspace}
                                isRestricted={isRestricted(message)}
                                searchTerm={searchTerm}
                                compact={compact}
                            />
                        </div>
                    );
                })}
            </div>

            {/* ローディングインジケーター */}
            {isLoadingMore && (
                <div className="flex justify-center items-center py-8">
                    <div className="flex items-center space-x-2 text-gray-600">
                        <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        <span className="text-sm">メッセージを読み込み中...</span>
                    </div>
                </div>
            )}

            {/* エラー表示 */}
            {error && (
                <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div className="flex items-center">
                        <svg className="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                        </svg>
                        <div className="flex-1">
                            <h3 className="text-sm font-medium text-red-800">
                                読み込みエラー
                            </h3>
                            <p className="text-sm text-red-700 mt-1">
                                {error}
                            </p>
                        </div>
                        <button
                            onClick={handleRetry}
                            className="ml-4 text-sm font-medium text-red-800 hover:text-red-900"
                        >
                            再試行
                        </button>
                    </div>
                </div>
            )}

            {/* 終了メッセージ */}
            {!hasNextPage && messages.length > 0 && (
                <div className="text-center py-8 border-t border-gray-200 mt-8">
                    <div className="inline-flex items-center px-4 py-2 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
                        <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                        </svg>
                        すべてのメッセージを読み込みました ({messages.length.toLocaleString()}件)
                    </div>
                    
                    <button
                        onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                        className="mt-4 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7" />
                        </svg>
                        トップに戻る
                    </button>
                </div>
            )}

            {/* パフォーマンス情報（開発時のみ） */}
            {process.env.NODE_ENV === 'development' && (
                <div className="mt-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
                    <div className="flex justify-between">
                        <span>読み込み済み: {messages.length}</span>
                        <span>現在ページ: {currentPage}</span>
                        <span>残りページ: {hasNextPage ? 'あり' : 'なし'}</span>
                        <span>無限スクロール: {enableInfiniteScroll ? 'ON' : 'OFF'}</span>
                    </div>
                </div>
            )}
        </div>
    );
};

export default InfiniteMessagesList;
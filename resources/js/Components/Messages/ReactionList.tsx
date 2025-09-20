import React, { useState } from 'react';
import Modal from '@/Components/Modal';
import { User } from '@/types';

interface Reaction {
    name: string;
    count: number;
    users: string[];
    emoji?: string;
}

interface ReactionListProps {
    reactions: Reaction[];
    allUsers?: Record<string, User>;
    currentUser: User;
    className?: string;
    showUserDetails?: boolean;
    onReactionClick?: (reactionName: string) => void;
    interactive?: boolean;
}

const ReactionList: React.FC<ReactionListProps> = ({
    reactions,
    allUsers = {},
    currentUser,
    className = '',
    showUserDetails = true,
    onReactionClick,
    interactive = false
}) => {
    const [selectedReaction, setSelectedReaction] = useState<Reaction | null>(null);
    const [showModal, setShowModal] = useState(false);

    if (!reactions || reactions.length === 0) {
        return null;
    }

    // 絵文字マッピング（よく使われるSlack絵文字）
    const emojiMap: Record<string, string> = {
        '+1': '👍',
        '-1': '👎',
        'heart': '❤️',
        'thumbsup': '👍',
        'thumbsdown': '👎',
        'smile': '😊',
        'laughing': '😆',
        'joy': '😂',
        'heart_eyes': '😍',
        'thinking_face': '🤔',
        'clap': '👏',
        'fire': '🔥',
        'eyes': '👀',
        'raised_hands': '🙌',
        'ok_hand': '👌',
        'pray': '🙏',
        'muscle': '💪',
        'tada': '🎉',
        'star': '⭐',
        'white_check_mark': '✅',
        'x': '❌',
        'warning': '⚠️',
        'heavy_check_mark': '✔️',
        'question': '❓',
        'exclamation': '❗',
        'bulb': '💡',
        'rocket': '🚀',
        'calendar': '📅',
        'memo': '📝',
        'computer': '💻',
        'phone': '📱',
        'email': '📧',
        'link': '🔗',
        'lock': '🔒',
        'unlock': '🔓'
    };

    const getEmojiDisplay = (reactionName: string) => {
        // 既に絵文字の場合はそのまま返す
        if (/[\u{1F600}-\u{1F64F}]|[\u{1F300}-\u{1F5FF}]|[\u{1F680}-\u{1F6FF}]|[\u{1F700}-\u{1F77F}]|[\u{1F780}-\u{1F7FF}]|[\u{1F800}-\u{1F8FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/u.test(reactionName)) {
            return reactionName;
        }

        // マッピングから取得
        return emojiMap[reactionName] || `：${reactionName}：`;
    };

    const handleReactionClick = (reaction: Reaction) => {
        if (interactive && onReactionClick) {
            onReactionClick(reaction.name);
        } else if (showUserDetails) {
            setSelectedReaction(reaction);
            setShowModal(true);
        }
    };

    const getUsersForReaction = (reaction: Reaction): User[] => {
        return reaction.users
            .map(userId => allUsers[userId])
            .filter(Boolean);
    };

    const isUserReacted = (reaction: Reaction): boolean => {
        return reaction.users.includes(currentUser.id.toString());
    };

    // リアクションを人気順でソート
    const sortedReactions = [...reactions].sort((a, b) => b.count - a.count);

    return (
        <div className={`flex flex-wrap gap-1 ${className}`}>
            {sortedReactions.map((reaction, index) => {
                const userReacted = isUserReacted(reaction);
                const emoji = getEmojiDisplay(reaction.name);
                
                return (
                    <button
                        key={`${reaction.name}-${index}`}
                        onClick={() => handleReactionClick(reaction)}
                        className={`
                            inline-flex items-center px-2 py-1 rounded-full text-sm font-medium 
                            transition-all duration-150 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-1
                            ${
                                userReacted
                                    ? 'bg-blue-100 text-blue-800 border border-blue-300 shadow-sm'
                                    : 'bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-150'
                            }
                            ${interactive ? 'hover:shadow-md cursor-pointer' : ''}
                            ${showUserDetails ? 'hover:bg-gray-200' : ''}
                        `}
                        title={
                            showUserDetails 
                                ? `${reaction.count}人が${reaction.name}でリアクションしました。クリックで詳細を表示`
                                : `${reaction.count}人が${reaction.name}でリアクション`
                        }
                        disabled={!interactive && !showUserDetails}
                    >
                        <span className="mr-1" style={{ fontSize: '14px' }}>
                            {emoji}
                        </span>
                        <span className="text-xs">
                            {reaction.count}
                        </span>
                        {userReacted && (
                            <span className="ml-1 text-xs text-blue-600">
                                ●
                            </span>
                        )}
                    </button>
                );
            })}

            {/* リアクション詳細モーダル */}
            {selectedReaction && (
                <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="md">
                    <div className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center space-x-3">
                                <span className="text-2xl">
                                    {getEmojiDisplay(selectedReaction.name)}
                                </span>
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900">
                                        ：{selectedReaction.name}：
                                    </h3>
                                    <p className="text-sm text-gray-600">
                                        {selectedReaction.count}人がリアクションしました
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => setShowModal(false)}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div className="max-h-64 overflow-y-auto">
                            <div className="space-y-3">
                                {getUsersForReaction(selectedReaction).map((user) => (
                                    <div key={user.id} className="flex items-center space-x-3">
                                        {user.avatar_url ? (
                                            <img
                                                src={user.avatar_url}
                                                alt={user.name}
                                                className="w-8 h-8 rounded-full"
                                            />
                                        ) : (
                                            <div className="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                <span className="text-xs font-medium text-gray-700">
                                                    {user.name.charAt(0).toUpperCase()}
                                                </span>
                                            </div>
                                        )}
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">
                                                {user.display_name || user.name}
                                            </p>
                                            {user.id === currentUser.id && (
                                                <p className="text-xs text-blue-600">あなた</p>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                {/* ユーザー情報が取得できない場合 */}
                                {selectedReaction.users.length > getUsersForReaction(selectedReaction).length && (
                                    <div className="text-center py-3 text-gray-500">
                                        <p className="text-sm">
                                            {selectedReaction.users.length - getUsersForReaction(selectedReaction).length}人の
                                            ユーザー情報を表示できません
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {interactive && (
                            <div className="mt-6 flex justify-end space-x-3">
                                <button
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"
                                >
                                    閉じる
                                </button>
                                {onReactionClick && (
                                    <button
                                        onClick={() => {
                                            onReactionClick(selectedReaction.name);
                                            setShowModal(false);
                                        }}
                                        className={`px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                                            isUserReacted(selectedReaction)
                                                ? 'text-red-700 bg-red-100 hover:bg-red-200 focus:ring-red-500'
                                                : 'text-blue-700 bg-blue-100 hover:bg-blue-200 focus:ring-blue-500'
                                        }`}
                                    >
                                        {isUserReacted(selectedReaction) ? 'リアクションを削除' : 'リアクションを追加'}
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                </Modal>
            )}
        </div>
    );
};

// リアクション統計表示コンポーネント
interface ReactionStatsProps {
    reactions: Reaction[];
    className?: string;
}

export const ReactionStats: React.FC<ReactionStatsProps> = ({
    reactions,
    className = ''
}) => {
    if (!reactions || reactions.length === 0) {
        return null;
    }

    const totalReactions = reactions.reduce((sum, reaction) => sum + reaction.count, 0);
    const uniqueUsers = new Set(reactions.flatMap(r => r.users)).size;

    return (
        <div className={`text-xs text-gray-500 ${className}`}>
            <div className="flex items-center space-x-3">
                <span>
                    {totalReactions}個のリアクション
                </span>
                <span>
                    {uniqueUsers}人がリアクション
                </span>
                <span>
                    {reactions.length}種類
                </span>
            </div>
        </div>
    );
};

export default ReactionList;
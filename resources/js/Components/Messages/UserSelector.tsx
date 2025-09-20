import React, { useState, useEffect } from 'react';
import { User } from '@/types';

interface UserSelectorProps {
    currentUser: User;
    selectedUserId?: number;
    onUserChange: (userId: number | null) => void;
    users?: User[];
    isLoading?: boolean;
    className?: string;
}

const UserSelector: React.FC<UserSelectorProps> = ({
    currentUser,
    selectedUserId,
    onUserChange,
    users = [],
    isLoading = false,
    className = ''
}) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [filteredUsers, setFilteredUsers] = useState<User[]>(users);

    // 管理者でない場合は表示しない
    if (!currentUser.is_admin) {
        return null;
    }

    // ユーザー検索のフィルタリング
    useEffect(() => {
        if (!searchTerm) {
            setFilteredUsers(users);
        } else {
            const filtered = users.filter(user =>
                user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (user.display_name && user.display_name.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (user.email && user.email.toLowerCase().includes(searchTerm.toLowerCase()))
            );
            setFilteredUsers(filtered);
        }
    }, [searchTerm, users]);

    const selectedUser = users.find(user => user.id === selectedUserId);

    const handleUserSelect = (userId: number | null) => {
        onUserChange(userId);
        setIsExpanded(false);
        setSearchTerm('');
    };

    const getUserStats = (user: User) => {
        // 実際の統計情報があれば表示
        return {
            messageCount: user.message_count || 0,
            channelCount: user.accessible_channel_count || 0,
            lastSync: user.last_sync_at || null
        };
    };

    return (
        <div className={`relative ${className}`}>
            {/* 管理者用セレクター */}
            <div className="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-4 mb-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <div className="p-2 bg-purple-100 rounded-full">
                            <svg className="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-sm font-medium text-purple-900">
                                👑 管理者モード
                            </h3>
                            <p className="text-xs text-purple-600">
                                {selectedUser ? `${selectedUser.name}として表示中` : '全ユーザーのデータを表示中'}
                            </p>
                        </div>
                    </div>
                    
                    <button
                        onClick={() => setIsExpanded(!isExpanded)}
                        className="px-3 py-2 text-sm font-medium text-purple-700 bg-white border border-purple-300 rounded-md hover:bg-purple-50 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        disabled={isLoading}
                    >
                        {isLoading ? (
                            <div className="flex items-center">
                                <svg className="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                読み込み中...
                            </div>
                        ) : (
                            <>
                                ユーザー切り替え
                                <svg 
                                    className={`w-4 h-4 ml-1 transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </>
                        )}
                    </button>
                </div>

                {/* 選択中ユーザーの詳細情報 */}
                {selectedUser && (
                    <div className="mt-3 p-3 bg-white rounded-md border border-purple-200">
                        <div className="flex items-center space-x-3">
                            {selectedUser.avatar_url && (
                                <img
                                    src={selectedUser.avatar_url}
                                    alt={selectedUser.name}
                                    className="w-10 h-10 rounded-full"
                                />
                            )}
                            <div className="flex-1">
                                <div className="flex items-center space-x-2">
                                    <h4 className="text-sm font-medium text-gray-900">
                                        {selectedUser.display_name || selectedUser.name}
                                    </h4>
                                    {selectedUser.is_admin && (
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                            管理者
                                        </span>
                                    )}
                                    {!selectedUser.is_active && (
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                            無効
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-gray-500">{selectedUser.email}</p>
                                
                                {/* 統計情報 */}
                                <div className="mt-2 flex space-x-4 text-xs text-gray-600">
                                    <span>メッセージ: {getUserStats(selectedUser).messageCount}件</span>
                                    <span>チャンネル: {getUserStats(selectedUser).channelCount}個</span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* ユーザー選択ドロップダウン */}
            {isExpanded && (
                <div className="absolute top-full left-0 right-0 z-50 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-96 overflow-hidden">
                    {/* 検索フィールド */}
                    <div className="p-3 border-b border-gray-200">
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                placeholder="ユーザーを検索..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            />
                        </div>
                    </div>

                    {/* ユーザーリスト */}
                    <div className="max-h-64 overflow-y-auto">
                        {/* 全ユーザー表示オプション */}
                        <button
                            onClick={() => handleUserSelect(null)}
                            className={`w-full text-left px-4 py-3 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none transition-colors ${
                                !selectedUserId ? 'bg-purple-50 border-r-4 border-r-purple-500' : ''
                            }`}
                        >
                            <div className="flex items-center space-x-3">
                                <div className="w-8 h-8 rounded-full bg-gradient-to-r from-purple-400 to-indigo-500 flex items-center justify-center">
                                    <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" />
                                    </svg>
                                </div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-gray-900">すべてのユーザー</p>
                                    <p className="text-xs text-gray-500">全データを管理者として表示</p>
                                </div>
                            </div>
                        </button>

                        {/* 個別ユーザー */}
                        {filteredUsers.map((user) => {
                            const stats = getUserStats(user);
                            const isSelected = selectedUserId === user.id;
                            
                            return (
                                <button
                                    key={user.id}
                                    onClick={() => handleUserSelect(user.id)}
                                    className={`w-full text-left px-4 py-3 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none transition-colors ${
                                        isSelected ? 'bg-purple-50 border-r-4 border-r-purple-500' : ''
                                    }`}
                                >
                                    <div className="flex items-center space-x-3">
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
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center space-x-2">
                                                <p className="text-sm font-medium text-gray-900 truncate">
                                                    {user.display_name || user.name}
                                                </p>
                                                {user.is_admin && (
                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                                        管理者
                                                    </span>
                                                )}
                                                {!user.is_active && (
                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                                        無効
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-gray-500 truncate">{user.email}</p>
                                            <div className="flex space-x-3 text-xs text-gray-400 mt-1">
                                                <span>📝 {stats.messageCount}</span>
                                                <span>📁 {stats.channelCount}</span>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            );
                        })}

                        {filteredUsers.length === 0 && (
                            <div className="px-4 py-6 text-center text-gray-500">
                                <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                </svg>
                                <p className="mt-2 text-sm">該当するユーザーが見つかりません</p>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

export default UserSelector;
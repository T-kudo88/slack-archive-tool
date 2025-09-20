import React from 'react';
import { User } from '@/types';

interface PersonalDataBadgeProps {
    currentUser: User;
    totalMessages: number;
    accessibleMessages: number;
    restrictedMessages?: number;
    className?: string;
    showDetails?: boolean;
}

const PersonalDataBadge: React.FC<PersonalDataBadgeProps> = ({
    currentUser,
    totalMessages,
    accessibleMessages,
    restrictedMessages,
    className = '',
    showDetails = false
}) => {
    // 管理者の場合は制限なし
    if (currentUser.is_admin) {
        return (
            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800 ${className}`}>
                <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clipRule="evenodd" />
                </svg>
                👑 管理者 (全データアクセス可)
            </div>
        );
    }

    const restrictionPercentage = totalMessages > 0 
        ? Math.round(((totalMessages - accessibleMessages) / totalMessages) * 100)
        : 0;

    const hasRestrictions = restrictionPercentage > 0;

    if (!hasRestrictions) {
        return (
            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 ${className}`}>
                <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
                全データにアクセス可能
            </div>
        );
    }

    const getBadgeColor = () => {
        if (restrictionPercentage <= 10) return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        if (restrictionPercentage <= 50) return 'bg-orange-100 text-orange-800 border-orange-200';
        return 'bg-red-100 text-red-800 border-red-200';
    };

    const getIcon = () => {
        return (
            <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
            </svg>
        );
    };

    return (
        <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border ${getBadgeColor()} ${className}`}>
            {getIcon()}
            個人データ制限適用中
            {showDetails && (
                <span className="ml-1 text-xs opacity-75">
                    ({restrictionPercentage}%制限)
                </span>
            )}
        </div>
    );
};

// 個人制限の詳細情報を表示するコンポーネント
interface PersonalDataInfoProps {
    currentUser: User;
    stats: {
        total_channels: number;
        accessible_channels: number;
        dm_channels: number;
        total_messages: number;
        user_messages: number;
    };
    className?: string;
}

export const PersonalDataInfo: React.FC<PersonalDataInfoProps> = ({
    currentUser,
    stats,
    className = ''
}) => {
    if (currentUser.is_admin) {
        return (
            <div className={`bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-4 ${className}`}>
                <div className="flex items-center mb-2">
                    <div className="p-2 bg-purple-100 rounded-full mr-3">
                        <svg className="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-sm font-medium text-purple-900">👑 管理者権限</h3>
                        <p className="text-xs text-purple-600">すべてのデータにアクセス可能です</p>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span className="text-purple-700 font-medium">総チャンネル数:</span>
                        <span className="ml-1 text-purple-900">{stats.total_channels.toLocaleString()}個</span>
                    </div>
                    <div>
                        <span className="text-purple-700 font-medium">総メッセージ数:</span>
                        <span className="ml-1 text-purple-900">{stats.total_messages.toLocaleString()}件</span>
                    </div>
                </div>
            </div>
        );
    }

    const restrictedChannels = stats.total_channels - stats.accessible_channels;
    const restrictedMessages = stats.total_messages - stats.user_messages;
    const channelRestrictionPercentage = stats.total_channels > 0 
        ? Math.round((restrictedChannels / stats.total_channels) * 100)
        : 0;

    return (
        <div className={`bg-gradient-to-r from-blue-50 to-cyan-50 border border-blue-200 rounded-lg p-4 ${className}`}>
            <div className="flex items-center mb-3">
                <div className="p-2 bg-blue-100 rounded-full mr-3">
                    <svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                    </svg>
                </div>
                <div>
                    <h3 className="text-sm font-medium text-blue-900">🔒 個人データ制限</h3>
                    <p className="text-xs text-blue-600">
                        プライバシー保護のため、アクセス可能なデータが制限されています
                    </p>
                </div>
            </div>

            <div className="space-y-3">
                {/* チャンネルアクセス状況 */}
                <div>
                    <div className="flex justify-between items-center mb-1">
                        <span className="text-sm font-medium text-blue-900">チャンネルアクセス</span>
                        <span className="text-xs text-blue-700">
                            {stats.accessible_channels}/{stats.total_channels}個
                        </span>
                    </div>
                    <div className="w-full bg-blue-200 rounded-full h-2">
                        <div 
                            className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                            style={{ 
                                width: stats.total_channels > 0 
                                    ? `${(stats.accessible_channels / stats.total_channels) * 100}%` 
                                    : '0%' 
                            }}
                        ></div>
                    </div>
                    <div className="flex justify-between text-xs text-blue-600 mt-1">
                        <span>アクセス可能</span>
                        {restrictedChannels > 0 && (
                            <span>制限: {restrictedChannels}個 ({channelRestrictionPercentage}%)</span>
                        )}
                    </div>
                </div>

                {/* アクセス可能なチャンネル種別 */}
                <div className="grid grid-cols-3 gap-2 text-xs">
                    <div className="text-center p-2 bg-white rounded border border-blue-200">
                        <div className="font-medium text-blue-900">{stats.accessible_channels - stats.dm_channels}</div>
                        <div className="text-blue-600">パブリック</div>
                    </div>
                    <div className="text-center p-2 bg-white rounded border border-blue-200">
                        <div className="font-medium text-blue-900">{stats.dm_channels}</div>
                        <div className="text-blue-600">DM</div>
                    </div>
                    <div className="text-center p-2 bg-white rounded border border-blue-200">
                        <div className="font-medium text-blue-900">{stats.user_messages.toLocaleString()}</div>
                        <div className="text-blue-600">自分のメッセージ</div>
                    </div>
                </div>

                {/* 制限の説明 */}
                <div className="bg-white p-3 rounded border border-blue-200">
                    <h4 className="text-xs font-medium text-blue-900 mb-2">制限内容</h4>
                    <ul className="text-xs text-blue-700 space-y-1">
                        <li className="flex items-center">
                            <svg className="w-3 h-3 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                            パブリックチャンネルのすべてのメッセージ
                        </li>
                        <li className="flex items-center">
                            <svg className="w-3 h-3 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                            参加しているDMのメッセージ
                        </li>
                        <li className="flex items-center">
                            <svg className="w-3 h-3 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                            すべてのチャンネルでの自分のメッセージ
                        </li>
                        <li className="flex items-center">
                            <svg className="w-3 h-3 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                            参加していないプライベートチャンネル
                        </li>
                        <li className="flex items-center">
                            <svg className="w-3 h-3 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                            他のユーザーのDMメッセージ
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    );
};

export default PersonalDataBadge;
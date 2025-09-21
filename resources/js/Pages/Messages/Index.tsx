import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SearchForm from '@/Components/Messages/SearchForm';
import UserSelector from '@/Components/Messages/UserSelector';
import PersonalDataBadge, { PersonalDataInfo } from '@/Components/Messages/PersonalDataBadge';
import FileAttachment from '@/Components/Messages/FileAttachment';
import ThreadView from '@/Components/Messages/ThreadView';
import { PageProps, User, Workspace, Channel } from '@/types';

interface FileAttachmentData {
    id: string;
    name: string;
    mimetype: string;
    size: number;
    title?: string;
    is_external?: boolean;
}

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
    files?: FileAttachmentData[];
}

interface GroupedMessage {
    date: string;
    messages: Message[];
}

interface IndexProps extends PageProps {
    groupedMessages: GroupedMessage[];
    filters: {
        workspace_id?: number;
        channel_id?: number;
        search?: string;
        date_from?: string;
        date_to?: string;
        message_type?: string;
        limit?: number;
    };
    filterOptions: {
        workspaces: Workspace[];
        channels: Channel[];
        messageTypes: string[];
    };
    stats: {
        total_messages: number;
        today_messages: number;
        this_week_messages: number;
        accessible_channels: number;
    };
}

export default function Index({ auth, groupedMessages, filters, filterOptions, stats }: IndexProps) {
    const [showPersonalDataInfo, setShowPersonalDataInfo] = useState(false);
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    // スレッド表示用の state
    const [threadData, setThreadData] = useState<{
        parent: Message | null;
        replies: Message[];
        channel: Channel | null;
        workspace?: Workspace | null;
    } | null>(null);
    
    const openThread = async (msg: Message) => {
        try {
            const res = await fetch(`/messages/${msg.id}/thread`);
            const data = await res.json();
            setThreadData({
                parent: data.parent,
                replies: data.replies,
                channel: data.channel,
                workspace: data.workspace,
            });
        } catch (err) {
            console.error("Failed to fetch thread:", err);
        }
    };

    const completeStats = {
        total_channels: filterOptions.channels.length,
        accessible_channels: stats.accessible_channels,
        dm_channels: filterOptions.channels.filter(c => c.is_dm).length,
        total_messages: stats.total_messages,
        user_messages: stats.total_messages,
        ...stats
    };

    const handleUserChange = (userId: number | null) => {
        setSelectedUserId(userId);
    };

    const getPageTitle = () => {
        let title = 'メッセージ一覧';
        if (filters.search) title += ` - "${filters.search}"`;
        if (selectedUserId && auth.user.is_admin) title += ' (管理者モード)';
        return title;
    };

    const getSubtitle = () => {
        const parts: string[] = [];
        if (filters.workspace_id) {
            const workspace = filterOptions.workspaces.find(w => w.id === filters.workspace_id);
            if (workspace) parts.push(`ワークスペース: ${workspace.name}`);
        }
        if (filters.channel_id) {
            const channel = filterOptions.channels.find(c => c.id === filters.channel_id);
            if (channel) parts.push(`チャンネル: ${channel.name}`);
        }
        if (filters.date_from || filters.date_to) {
            const dateRange = [filters.date_from, filters.date_to].filter(Boolean).join(' ~ ');
            parts.push(`期間: ${dateRange}`);
        }
        return parts.join(' | ');
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            {getPageTitle()}
                        </h2>
                        {getSubtitle() && (
                            <p className="text-sm text-gray-600 mt-1">{getSubtitle()}</p>
                        )}
                    </div>
                    <div className="flex items-center space-x-3 mt-4 sm:mt-0">
                        <PersonalDataBadge
                            currentUser={auth.user}
                            totalMessages={completeStats.total_messages}
                            accessibleMessages={completeStats.user_messages}
                            showDetails={false}
                        />
                        <button
                            onClick={() => setShowPersonalDataInfo(!showPersonalDataInfo)}
                            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                        >
                            {showPersonalDataInfo ? '詳細を隠す' : 'アクセス詳細'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={getPageTitle()} />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 flex">
                    
                    {/* 左側: メッセージ一覧 */}
                    <div className={`flex-1 bg-gray-50 rounded-lg p-6 ${threadData ? 'w-2/3' : 'w-full'}`}>
                        {auth.user.is_admin && (
                            <UserSelector
                                currentUser={auth.user}
                                selectedUserId={selectedUserId}
                                onUserChange={handleUserChange}
                                users={[]}
                                isLoading={isLoading}
                                className="mb-6"
                            />
                        )}

                        {showPersonalDataInfo && (
                            <PersonalDataInfo
                                currentUser={auth.user}
                                stats={completeStats}
                                className="mb-6"
                            />
                        )}

                        <SearchForm
                            initialFilters={filters}
                            filterOptions={filterOptions}
                            isLoading={isLoading}
                        />

                        {groupedMessages.length > 0 ? (
                            groupedMessages.map(group => (
                                <div key={group.date} className="mb-8">
                                    <h2 className="text-lg font-bold mb-4">{group.date}</h2>
                                    <div className="space-y-3">
                                        {group.messages.map(msg => (
                                            <div key={msg.id} className="p-3 bg-white rounded shadow-sm">
                                                <p className="text-sm text-gray-800">
                                                    <span className="font-semibold">{msg.user?.name}</span>: {msg.text}
                                                </p>

                                                {msg.files && msg.files.length > 0 && (
                                                    <div className="mt-2">
                                                        <FileAttachment files={msg.files} />
                                                    </div>
                                                )}

                                                {msg.reply_count && msg.reply_count > 0 && (
                                                    <button
                                                        onClick={() => openThread(msg)}
                                                        className="text-blue-600 text-sm hover:underline mt-1 inline-block"
                                                    >
                                                        {msg.reply_count}件の返信を見る
                                                    </button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))
                        ) : (
                            <p className="text-gray-500">
                                {filters.search
                                    ? "検索条件に一致するメッセージが見つかりませんでした"
                                    : "まだメッセージがありません"}
                            </p>
                        )}
                    </div>

                    {/* 右側: スレッドペイン */}
                    {threadData && (
                        <div className="w-1/3 border-l bg-white p-4">
                            <button
                                onClick={() => setThreadData(null)}
                                className="text-gray-500 hover:text-gray-800 float-right"
                            >
                                ✕
                            </button>
                            <ThreadView
                                parentMessage={threadData.parent}
                                threadReplies={threadData.replies}
                                channel={threadData.channel}
                                workspace={threadData.workspace}
                                currentUser={auth.user}
                            />
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
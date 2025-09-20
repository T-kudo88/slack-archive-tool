import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, User } from '@/types';

interface SystemStats {
    users: {
        total: number;
        active: number;
        inactive: number;
        admins: number;
        recent_logins: number;
        most_active: User[];
    };
    workspaces: {
        total: number;
    };
    channels: {
        total: number;
        public: number;
        private: number;
        dm: number;
    };
    messages: {
        total: number;
        today: number;
        this_week: number;
        this_month: number;
        with_files: number;
        average_per_day: number;
    };
    sync: {
        completed_today: number;
        failed_today: number;
        in_progress: number;
    };
    storage: {
        messages_size_mb: number;
        files_size_mb: number;
    };
    growth: {
        daily: Record<string, number>;
        monthly: Record<string, number>;
    };
}

interface Activity {
    type: string;
    title: string;
    description: string;
    timestamp: string;
    icon: string;
    color: string;
}

interface SecurityAlert {
    id: string;
    type: 'warning' | 'error' | 'info';
    title: string;
    description: string;
    severity: 'low' | 'medium' | 'high';
    timestamp: string;
    action_url?: string;
}

interface SyncStatus {
    running_jobs: number;
    last_sync: string | null;
    next_scheduled_sync: string;
    total_synced_messages_today: number;
    sync_health: 'healthy' | 'syncing' | 'error';
}

interface DashboardProps extends PageProps {
    stats: SystemStats;
    recentActivities: Activity[];
    securityAlerts: SecurityAlert[];
    syncStatus: SyncStatus;
}

export default function Dashboard({ auth, stats, recentActivities, securityAlerts, syncStatus }: DashboardProps) {
    const [realtimeStats, setRealtimeStats] = useState(stats);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [selectedTimeRange, setSelectedTimeRange] = useState('week');

    useEffect(() => {
        const interval = setInterval(async () => {
            try {
                const response = await fetch('/admin/dashboard/realtime-stats');
                if (response.ok) {
                    const data = await response.json();
                    setRealtimeStats(prev => ({ ...prev, ...data }));
                }
            } catch (error) {
                console.error('Failed to fetch realtime stats:', error);
            }
        }, 30000); // 30秒間隔

        return () => clearInterval(interval);
    }, []);

    const refreshStats = async () => {
        setIsRefreshing(true);
        try {
            window.location.reload();
        } catch (error) {
            console.error('Failed to refresh stats:', error);
        } finally {
            setIsRefreshing(false);
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'healthy': return 'text-green-600';
            case 'syncing': return 'text-blue-600';
            case 'error': return 'text-red-600';
            default: return 'text-gray-600';
        }
    };

    const getAlertColor = (severity: string, type: string) => {
        if (type === 'error') return 'bg-red-100 border-red-500 text-red-700';
        if (type === 'warning') return 'bg-yellow-100 border-yellow-500 text-yellow-700';
        return 'bg-blue-100 border-blue-500 text-blue-700';
    };

    const StatCard = ({ title, value, subtitle, icon, trend, color = 'blue' }: {
        title: string;
        value: string | number;
        subtitle?: string;
        icon: React.ReactNode;
        trend?: string;
        color?: string;
    }) => (
        <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
                <div className="flex items-center">
                    <div className="flex-shrink-0">
                        <div className={`text-${color}-600`}>
                            {icon}
                        </div>
                    </div>
                    <div className="ml-5 w-0 flex-1">
                        <dl>
                            <dt className="text-sm font-medium text-gray-500 truncate">
                                {title}
                            </dt>
                            <dd className="flex items-baseline">
                                <div className="text-2xl font-semibold text-gray-900">
                                    {typeof value === 'number' ? value.toLocaleString() : value}
                                </div>
                                {trend && (
                                    <div className="ml-2 flex items-baseline text-sm">
                                        <span className="text-green-600 font-medium">
                                            {trend}
                                        </span>
                                    </div>
                                )}
                            </dd>
                            {subtitle && (
                                <dd className="text-sm text-gray-600 mt-1">
                                    {subtitle}
                                </dd>
                            )}
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            管理者ダッシュボード
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            システム全体の統計と監視
                        </p>
                    </div>
                    <div className="flex items-center space-x-3 mt-4 sm:mt-0">
                        <button
                            onClick={refreshStats}
                            disabled={isRefreshing}
                            className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <svg className={`-ml-0.5 mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {isRefreshing ? '更新中...' : '更新'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="管理者ダッシュボード" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* セキュリティアラート */}
                    {securityAlerts.length > 0 && (
                        <div className="mb-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-3">🚨 セキュリティアラート</h3>
                            <div className="space-y-3">
                                {securityAlerts.map((alert) => (
                                    <div
                                        key={alert.id}
                                        className={`border-l-4 p-4 ${getAlertColor(alert.severity, alert.type)}`}
                                    >
                                        <div className="flex">
                                            <div className="flex-1">
                                                <p className="font-medium">{alert.title}</p>
                                                <p className="mt-1 text-sm">{alert.description}</p>
                                            </div>
                                            {alert.action_url && (
                                                <div className="ml-4">
                                                    <a
                                                        href={alert.action_url}
                                                        className="text-sm font-medium underline hover:no-underline"
                                                    >
                                                        詳細を見る
                                                    </a>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* 主要統計 */}
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <StatCard
                            title="総ユーザー数"
                            value={realtimeStats.users.total}
                            subtitle={`アクティブ: ${realtimeStats.users.active}人`}
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                </svg>
                            }
                            color="blue"
                        />

                        <StatCard
                            title="総メッセージ数"
                            value={realtimeStats.messages.total}
                            subtitle={`今日: ${realtimeStats.messages.today}件`}
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            }
                            color="green"
                        />

                        <StatCard
                            title="チャンネル数"
                            value={realtimeStats.channels.total}
                            subtitle={`パブリック: ${realtimeStats.channels.public}, DM: ${realtimeStats.channels.dm}`}
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            }
                            color="purple"
                        />

                        <StatCard
                            title="同期ステータス"
                            value={syncStatus.sync_health === 'healthy' ? '正常' : syncStatus.sync_health === 'syncing' ? '同期中' : 'エラー'}
                            subtitle={`実行中ジョブ: ${syncStatus.running_jobs}個`}
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            }
                            color={syncStatus.sync_health === 'healthy' ? 'green' : syncStatus.sync_health === 'syncing' ? 'blue' : 'red'}
                        />
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* 詳細統計 */}
                        <div className="lg:col-span-2">
                            <div className="bg-white shadow rounded-lg p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg font-medium text-gray-900">📊 詳細統計</h3>
                                    <select
                                        value={selectedTimeRange}
                                        onChange={(e) => setSelectedTimeRange(e.target.value)}
                                        className="text-sm border-gray-300 rounded-md"
                                    >
                                        <option value="day">今日</option>
                                        <option value="week">今週</option>
                                        <option value="month">今月</option>
                                    </select>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="p-4 bg-gray-50 rounded-lg">
                                        <h4 className="font-medium text-gray-900 mb-2">👥 ユーザー</h4>
                                        <div className="space-y-1 text-sm">
                                            <div className="flex justify-between">
                                                <span>アクティブユーザー:</span>
                                                <span className="font-medium">{realtimeStats.users.active}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>管理者:</span>
                                                <span className="font-medium">{realtimeStats.users.admins}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>最近のログイン:</span>
                                                <span className="font-medium">{realtimeStats.users.recent_logins}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="p-4 bg-gray-50 rounded-lg">
                                        <h4 className="font-medium text-gray-900 mb-2">💬 メッセージ</h4>
                                        <div className="space-y-1 text-sm">
                                            <div className="flex justify-between">
                                                <span>今週:</span>
                                                <span className="font-medium">{realtimeStats.messages.this_week.toLocaleString()}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>今月:</span>
                                                <span className="font-medium">{realtimeStats.messages.this_month.toLocaleString()}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>ファイル付き:</span>
                                                <span className="font-medium">{realtimeStats.messages.with_files.toLocaleString()}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="p-4 bg-gray-50 rounded-lg">
                                        <h4 className="font-medium text-gray-900 mb-2">💾 ストレージ</h4>
                                        <div className="space-y-1 text-sm">
                                            <div className="flex justify-between">
                                                <span>メッセージ:</span>
                                                <span className="font-medium">{realtimeStats.storage.messages_size_mb} MB</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>ファイル:</span>
                                                <span className="font-medium">{realtimeStats.storage.files_size_mb} MB</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>合計:</span>
                                                <span className="font-medium">
                                                    {(realtimeStats.storage.messages_size_mb + realtimeStats.storage.files_size_mb).toFixed(2)} MB
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="p-4 bg-gray-50 rounded-lg">
                                        <h4 className="font-medium text-gray-900 mb-2">🔄 同期</h4>
                                        <div className="space-y-1 text-sm">
                                            <div className="flex justify-between">
                                                <span>今日の完了:</span>
                                                <span className="font-medium">{realtimeStats.sync.completed_today}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>今日の失敗:</span>
                                                <span className="font-medium text-red-600">{realtimeStats.sync.failed_today}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>実行中:</span>
                                                <span className="font-medium">{realtimeStats.sync.in_progress}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 最近のアクティビティ */}
                        <div className="lg:col-span-1">
                            <div className="bg-white shadow rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">📋 最近のアクティビティ</h3>
                                <div className="flow-root">
                                    <ul className="-mb-8">
                                        {recentActivities.slice(0, 8).map((activity, index) => (
                                            <li key={index}>
                                                <div className="relative pb-8">
                                                    {index < recentActivities.length - 1 && (
                                                        <span
                                                            className="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    <div className="relative flex space-x-3">
                                                        <div className={`h-8 w-8 rounded-full bg-${activity.color}-100 flex items-center justify-center ring-8 ring-white`}>
                                                            <span className="text-sm">{activity.icon === 'user-plus' ? '👤' : '💬'}</span>
                                                        </div>
                                                        <div className="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                            <div>
                                                                <p className="text-sm text-gray-900">{activity.title}</p>
                                                                <p className="text-xs text-gray-500">{activity.description}</p>
                                                            </div>
                                                            <div className="text-right text-xs text-gray-500">
                                                                {new Date(activity.timestamp).toLocaleString('ja-JP')}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* クイックアクション */}
                    <div className="mt-8">
                        <div className="bg-white shadow rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">⚡ クイックアクション</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <a
                                    href="/admin/users"
                                    className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    👥 ユーザー管理
                                </a>
                                <a
                                    href="/admin/audit-logs"
                                    className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    📋 監査ログ
                                </a>
                                <a
                                    href="/admin/sync-status"
                                    className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    🔄 同期管理
                                </a>
                                <a
                                    href="/admin/health-check"
                                    className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    🏥 システム状態
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
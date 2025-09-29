import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, User } from '@/types';

interface SystemStats {
    users: { total: number; active: number; inactive: number; admins: number; recent_logins: number; most_active: User[] };
    workspaces: { total: number };
    channels: { total: number; public: number; private: number; dm: number };
    messages: { total: number; today: number; this_week: number; this_month: number; with_files: number; average_per_day: number };
    sync: { completed_today: number; failed_today: number; in_progress: number };
    storage: { messages_size_mb: number; files_size_mb: number };
    growth: { daily: Record<string, number>; monthly: Record<string, number> };
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
    userCount: number;    // 👈 追加
    messageCount: number; // 👈 追加
    users: {
        id: number;
        name: string;
        email: string;
        is_admin: boolean;
        last_login_at: string | null;
    }[]; // 👈 追加
}

export default function Dashboard({
    auth,
    stats,
    recentActivities,
    securityAlerts,
    syncStatus,
    userCount,
    messageCount,
    users
}: DashboardProps) {
    const [realtimeStats, setRealtimeStats] = useState(stats);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isSyncingDiff, setIsSyncingDiff] = useState(false);
    const [isSyncingAll, setIsSyncingAll] = useState(false);

    // 30秒ごとにリアルタイム統計を取得
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
        }, 30000);
        return () => clearInterval(interval);
    }, []);

    // 統計リフレッシュ
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

    // 差分同期トリガー
    const triggerDiffSync = async () => {
        setIsSyncingDiff(true);
        try {
            const response = await fetch('/admin/sync-messages', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
                }
            });
            if (response.ok) {
                alert('差分同期を開始しました 🚀');
            } else {
                alert('差分同期でエラーが発生しました 😢');
            }
        } catch (error) {
            console.error(error);
            alert('差分同期リクエストに失敗しました ❌');
        } finally {
            setIsSyncingDiff(false);
        }
    };

    // 全メッセージ同期トリガー
    const triggerFullSync = async () => {
        setIsSyncingAll(true);
        try {
            const response = await fetch('/admin/sync-all-messages', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
                }
            });
            if (response.ok) {
                alert('全メッセージ同期が完了しました ✅');
            } else {
                alert('全メッセージ同期でエラーが発生しました 😢');
            }
        } catch (error) {
            console.error(error);
            alert('全メッセージ同期リクエストに失敗しました ❌');
        } finally {
            setIsSyncingAll(false);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">管理者ダッシュボード</h2>
                        <p className="text-sm text-gray-600 mt-1">システム全体の統計と監視</p>
                    </div>
                    <div className="flex items-center space-x-3 mt-4 sm:mt-0">
                        <button
                            onClick={refreshStats}
                            disabled={isRefreshing}
                            className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm rounded-md bg-white hover:bg-gray-50"
                        >
                            <svg
                                className={`-ml-0.5 mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`}
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                            {isRefreshing ? '更新中...' : '更新'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="管理者ダッシュボード" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                {/* クイックアクション */}
                <div className="mt-8">
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">⚡ クイックアクション</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                            <a href="/admin/users" className="btn">👥 ユーザー管理</a>
                            <a href="/admin/audit-logs" className="btn">📋 監査ログ</a>
                            <a href="/admin/sync-status" className="btn">🔄 同期管理</a>
                            <a href="/admin/health-check" className="btn">🏥 システム状態</a>
                            <button
                                onClick={triggerDiffSync}
                                disabled={isSyncingDiff}
                                className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm rounded-md bg-white hover:bg-gray-50"
                            >
                                {isSyncingDiff ? '取得中...' : '📥 差分同期'}
                            </button>
                            <button
                                onClick={triggerFullSync}
                                disabled={isSyncingAll}
                                className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm rounded-md bg-white hover:bg-gray-50"
                            >
                                {isSyncingAll ? '取得中...' : '📥 全メッセージ同期'}
                            </button>
                        </div>
                    </div>
                </div>

                {/* アカウント情報 */}
                <div className="mt-8 bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">👤 アカウント情報</h3>
                    <p><b>名前:</b> {auth.user.name}</p>
                    <p><b>メール:</b> {auth.user.email}</p>
                    <p><b>権限:</b> {auth.user.is_admin ? '管理者' : '一般ユーザー'}</p>
                </div>

                {/* ユーザー統計 */}
                <div className="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">📊 ユーザー統計</h3>
                        <p><b>ユーザー数:</b> {userCount}</p>
                        <p><b>メッセージ数:</b> {messageCount}</p>
                    </div>
                </div>

                {/* ユーザー一覧 */}
                <div className="mt-8 bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">👥 ユーザー一覧</h3>
                    <table className="min-w-full border">
                        <thead>
                            <tr className="bg-gray-100">
                                <th className="px-4 py-2 text-left">名前</th>
                                <th className="px-4 py-2 text-left">メール</th>
                                <th className="px-4 py-2 text-left">管理者</th>
                                <th className="px-4 py-2 text-left">最終ログイン</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="px-4 py-2">{user.name}</td>
                                    <td className="px-4 py-2">{user.email}</td>
                                    <td className="px-4 py-2">{user.is_admin ? '✔' : '-'}</td>
                                    <td className="px-4 py-2">{user.last_login_at ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
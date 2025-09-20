import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';

interface SyncProps {
    stats: Record<string, any>;
    runningJobs: any[];
    recentSyncs: any[];
    failedJobs: any[];
    userSyncStatus: any[];
}

export default function Index({ auth, stats, runningJobs, recentSyncs, failedJobs, userSyncStatus }: PageProps<SyncProps>) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 space-y-6">
                <h1 className="text-xl font-bold">同期管理</h1>

                {/* 統計カード */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="p-4 bg-white rounded shadow">
                        <p className="text-sm text-gray-500">本日のジョブ数</p>
                        <p className="text-2xl font-bold">{stats.total_sync_jobs_today}</p>
                    </div>
                    <div className="p-4 bg-white rounded shadow">
                        <p className="text-sm text-gray-500">成功ジョブ数</p>
                        <p className="text-2xl font-bold text-green-600">{stats.successful_sync_jobs_today}</p>
                    </div>
                    <div className="p-4 bg-white rounded shadow">
                        <p className="text-sm text-gray-500">失敗ジョブ数</p>
                        <p className="text-2xl font-bold text-red-600">{stats.failed_sync_jobs_today}</p>
                    </div>
                    <div className="p-4 bg-white rounded shadow">
                        <p className="text-sm text-gray-500">同期ヘルス</p>
                        <p className="text-2xl font-bold">{stats.sync_health_status}</p>
                    </div>
                </div>

                {/* 最近の同期履歴 */}
                <div>
                    <h2 className="text-lg font-semibold mt-6">最近の同期履歴</h2>
                    {recentSyncs.length === 0 ? (
                        <p className="text-gray-500">データがありません</p>
                    ) : (
                        <table className="w-full text-sm mt-2 border">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="p-2">ユーザー</th>
                                    <th className="p-2">アクション</th>
                                    <th className="p-2">日時</th>
                                    <th className="p-2">ステータス</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentSyncs.map((sync, idx) => (
                                    <tr key={idx} className="border-t">
                                        <td className="p-2">{sync.user_name}</td>
                                        <td className="p-2">{sync.action}</td>
                                        <td className="p-2">{sync.created_at}</td>
                                        <td className="p-2">{sync.status}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import SlackIntegration from '@/Components/SlackIntegration';

import { User } from '@/types';

// props の型定義
interface Props extends PageProps {
    users: User[];
    totalUsers: number;
    totalMessages: number;
}

export default function Dashboard() {
    const { auth, users, totalUsers, totalMessages, errors, success } = usePage<Props>().props;
    const user = auth.user;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                    {/* エラーメッセージ */}
                    {errors && errors.slack && (
                        <div className="bg-red-50 border border-red-200 rounded-md p-4">
                            <div className="flex items-start">
                                <svg className="w-5 h-5 text-red-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <h3 className="text-sm font-medium text-red-800">Slack連携エラー</h3>
                                    <p className="text-sm text-red-700 mt-1">{errors.slack}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* 成功メッセージ */}
                    {success && (
                        <div className="bg-green-50 border border-green-200 rounded-md p-4">
                            <div className="flex items-start">
                                <svg className="w-5 h-5 text-green-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <h3 className="text-sm font-medium text-green-800">成功</h3>
                                    <p className="text-sm text-green-700 mt-1">{success}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Slack連携セクション */}
                    <SlackIntegration user={user} />

                    {/* ログイン中ユーザーの情報 */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <p className="mb-4">You're logged in!</p>

                            <h3 className="text-lg font-bold mb-2">ユーザー情報</h3>
                            <p><strong>ID:</strong> {user.id}</p>
                            <p><strong>名前:</strong> {user.name}</p>
                            <p><strong>Email:</strong> {user.email ?? '未設定'}</p>
                            <p><strong>Slack ID:</strong> {user.slack_user_id}</p>

                            {user.avatar_url && (
                                <div className="mt-4">
                                    <img
                                        src={user.avatar_url}
                                        alt="Avatar"
                                        className="w-24 h-24 rounded-full"
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* サマリー */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-bold mb-2">ユーザー数</h3>
                            <p>{totalUsers}</p>
                        </div>
                        <div className="bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-bold mb-2">メッセージ数</h3>
                            <p>{totalMessages}</p>
                        </div>
                    </div>

                    {/* ユーザー一覧 */}
                    <div className="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-bold mb-4">ユーザー一覧</h3>
                        <table className="min-w-full border text-sm">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="px-4 py-2 border">名前</th>
                                    <th className="px-4 py-2 border">メール</th>
                                    <th className="px-4 py-2 border">管理者</th>
                                    <th className="px-4 py-2 border">最終ログイン</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((u) => (
                                    <tr key={u.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-2 border">{u.name}</td>
                                        <td className="px-4 py-2 border">{u.email ?? '-'}</td>
                                        <td className="px-4 py-2 border">{u.is_admin ? '✓' : ''}</td>
                                        <td className="px-4 py-2 border">{u.last_login_at ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}

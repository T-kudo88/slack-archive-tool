import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, User, Workspace } from '@/types';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface UserWithStats extends User {
    messages_count: number;
    channels_count: number;
    workspaces: Workspace[];
}

interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    data: UserWithStats[];
}

interface UsersIndexProps extends PageProps {
    users: PaginationData;
    filters: {
        search?: string;
        status?: string;
        workspace_id?: number;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
    stats: {
        total_users: number;
        active_users: number;
        admin_users: number;
        inactive_admins: number;
        recent_logins: number;
        never_logged_in: number;
    };
    filterOptions: {
        workspaces: Workspace[];
        statuses: Array<{ value: string; label: string }>;
    };
}

export default function Index({ auth, users, filters, stats, filterOptions }: UsersIndexProps) {
    const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
    const [showBulkActions, setShowBulkActions] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [showFilters, setShowFilters] = useState(false);

    const handleFilter = (key: string, value: any) => {
        const newFilters = { ...filters, [key]: value };
        if (!value) delete newFilters[key];
        
        router.get('/admin/users', newFilters, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const handleSort = (column: string) => {
        const isCurrentSort = filters.sort_by === column;
        const newOrder = isCurrentSort && filters.sort_order === 'asc' ? 'desc' : 'asc';
        
        handleFilter('sort_by', column);
        handleFilter('sort_order', newOrder);
    };

    const toggleUserSelection = (userId: number) => {
        setSelectedUsers(prev => 
            prev.includes(userId)
                ? prev.filter(id => id !== userId)
                : [...prev, userId]
        );
    };

    const toggleAllUsers = () => {
        if (selectedUsers.length === users.data.length) {
            setSelectedUsers([]);
        } else {
            setSelectedUsers(users.data.map(user => user.id));
        }
    };

    const handleBulkAction = async (action: string) => {
        if (selectedUsers.length === 0) return;

        const reason = prompt(`一括${action}の理由を入力してください:`);
        if (!reason) return;

        setIsLoading(true);
        try {
            const response = await fetch('/admin/users/bulk-action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    action,
                    user_ids: selectedUsers,
                    reason
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('操作が完了しました');
                window.location.reload();
            } else {
                alert('操作に失敗しました: ' + data.message);
            }
        } catch (error) {
            alert('操作に失敗しました');
        } finally {
            setIsLoading(false);
        }
    };

    const handleUserAction = async (userId: number, action: string) => {
        const user = users.data.find(u => u.id === userId);
        if (!user) return;

        const reason = prompt(`${user.name}への${action}の理由を入力してください:`);
        if (!reason) return;

        setIsLoading(true);
        try {
            let endpoint = '';
            let payload = { reason };

            switch (action) {
                case 'toggle_status':
                    endpoint = `/admin/users/${userId}/status`;
                    payload = { ...payload, is_active: !user.is_active };
                    break;
                case 'toggle_admin':
                    endpoint = `/admin/users/${userId}/admin-status`;
                    payload = { ...payload, is_admin: !user.is_admin };
                    break;
                case 'delete':
                    endpoint = `/admin/users/${userId}`;
                    break;
            }

            const method = action === 'delete' ? 'DELETE' : 'PUT';
            const response = await fetch(endpoint, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                alert('操作が完了しました');
                window.location.reload();
            } else {
                alert('操作に失敗しました: ' + data.message);
            }
        } catch (error) {
            alert('操作に失敗しました');
        } finally {
            setIsLoading(false);
        }
    };

    const getStatusBadge = (user: UserWithStats) => {
        if (!user.is_active) {
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">非アクティブ</span>;
        }
        if (user.is_admin) {
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">管理者</span>;
        }
        return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">アクティブ</span>;
    };

    const getSortIcon = (column: string) => {
        if (filters.sort_by !== column) return null;
        return filters.sort_order === 'asc' ? '↑' : '↓';
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            👥 ユーザー管理
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            システム内のユーザーを管理します
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="ユーザー管理" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* 統計カード */}
                    <div className="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">総ユーザー</div>
                            <div className="text-2xl font-semibold text-gray-900">{stats.total_users}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">アクティブ</div>
                            <div className="text-2xl font-semibold text-green-600">{stats.active_users}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">管理者</div>
                            <div className="text-2xl font-semibold text-purple-600">{stats.admin_users}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">非アクティブ管理者</div>
                            <div className="text-2xl font-semibold text-red-600">{stats.inactive_admins}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">最近のログイン</div>
                            <div className="text-2xl font-semibold text-blue-600">{stats.recent_logins}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">未ログイン</div>
                            <div className="text-2xl font-semibold text-yellow-600">{stats.never_logged_in}</div>
                        </div>
                    </div>

                    {/* 検索・フィルター */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div className="flex items-center space-x-4">
                                <input
                                    type="text"
                                    placeholder="ユーザー名、メール、表示名で検索..."
                                    value={filters.search || ''}
                                    onChange={(e) => handleFilter('search', e.target.value)}
                                    className="border-gray-300 rounded-md shadow-sm text-sm w-64"
                                />
                                <button
                                    onClick={() => setShowFilters(!showFilters)}
                                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    🔍 フィルター
                                </button>
                            </div>
                            
                            <div className="flex items-center space-x-2">
                                <select
                                    value={filters.per_page || 25}
                                    onChange={(e) => handleFilter('per_page', e.target.value)}
                                    className="text-sm border-gray-300 rounded-md"
                                >
                                    <option value="10">10件</option>
                                    <option value="25">25件</option>
                                    <option value="50">50件</option>
                                    <option value="100">100件</option>
                                </select>
                            </div>
                        </div>

                        {showFilters && (
                            <div className="mt-4 pt-4 border-t border-gray-200">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">ステータス</label>
                                        <select
                                            value={filters.status || ''}
                                            onChange={(e) => handleFilter('status', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                        >
                                            <option value="">すべて</option>
                                            {filterOptions.statuses.map(status => (
                                                <option key={status.value} value={status.value}>
                                                    {status.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">ワークスペース</label>
                                        <select
                                            value={filters.workspace_id || ''}
                                            onChange={(e) => handleFilter('workspace_id', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                        >
                                            <option value="">すべて</option>
                                            {filterOptions.workspaces.map(workspace => (
                                                <option key={workspace.id} value={workspace.id}>
                                                    {workspace.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* 一括操作 */}
                    {selectedUsers.length > 0 && (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <div className="flex items-center justify-between">
                                <div className="text-sm text-blue-800">
                                    {selectedUsers.length}人のユーザーが選択されています
                                </div>
                                <div className="flex items-center space-x-2">
                                    <button
                                        onClick={() => handleBulkAction('activate')}
                                        className="px-3 py-1 bg-green-600 text-white text-sm rounded-md hover:bg-green-700"
                                        disabled={isLoading}
                                    >
                                        一括アクティブ化
                                    </button>
                                    <button
                                        onClick={() => handleBulkAction('deactivate')}
                                        className="px-3 py-1 bg-yellow-600 text-white text-sm rounded-md hover:bg-yellow-700"
                                        disabled={isLoading}
                                    >
                                        一括非アクティブ化
                                    </button>
                                    <button
                                        onClick={() => handleBulkAction('make_admin')}
                                        className="px-3 py-1 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700"
                                        disabled={isLoading}
                                    >
                                        一括管理者化
                                    </button>
                                    <button
                                        onClick={() => setSelectedUsers([])}
                                        className="px-3 py-1 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700"
                                    >
                                        選択解除
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ユーザー一覧 */}
                    <div className="bg-white shadow overflow-hidden sm:rounded-md">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left">
                                            <input
                                                type="checkbox"
                                                checked={selectedUsers.length === users.data.length && users.data.length > 0}
                                                onChange={toggleAllUsers}
                                                className="h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                            />
                                        </th>
                                        <th
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('name')}
                                        >
                                            ユーザー {getSortIcon('name')}
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ステータス
                                        </th>
                                        <th
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('messages_count')}
                                        >
                                            メッセージ {getSortIcon('messages_count')}
                                        </th>
                                        <th
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('last_login_at')}
                                        >
                                            最終ログイン {getSortIcon('last_login_at')}
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ワークスペース
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedUsers.includes(user.id)}
                                                    onChange={() => toggleUserSelection(user.id)}
                                                    className="h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                                />
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center">
                                                    {user.avatar_url && (
                                                        <img
                                                            src={user.avatar_url}
                                                            alt=""
                                                            className="h-8 w-8 rounded-full mr-3"
                                                        />
                                                    )}
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {user.display_name || user.name}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            {user.email}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                {getStatusBadge(user)}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                {user.messages_count?.toLocaleString() || 0}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                {user.last_login_at ? (
                                                    format(new Date(user.last_login_at), 'yyyy/MM/dd HH:mm', { locale: ja })
                                                ) : (
                                                    <span className="text-gray-400">未ログイン</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                {user.workspaces?.length || 0}個
                                            </td>
                                            <td className="px-6 py-4 text-sm font-medium space-x-2">
                                                <a
                                                    href={`/admin/users/${user.id}`}
                                                    className="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    詳細
                                                </a>
                                                {user.id !== auth.user.id && (
                                                    <>
                                                        <button
                                                            onClick={() => handleUserAction(user.id, 'toggle_status')}
                                                            className={user.is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'}
                                                            disabled={isLoading}
                                                        >
                                                            {user.is_active ? '無効化' : '有効化'}
                                                        </button>
                                                        <button
                                                            onClick={() => handleUserAction(user.id, 'toggle_admin')}
                                                            className={user.is_admin ? 'text-orange-600 hover:text-orange-900' : 'text-purple-600 hover:text-purple-900'}
                                                            disabled={isLoading}
                                                        >
                                                            {user.is_admin ? '管理者削除' : '管理者追加'}
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ページネーション */}
                        <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div className="flex-1 flex justify-between sm:hidden">
                                {users.prev_page_url && (
                                    <a
                                        href={users.prev_page_url}
                                        className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        前へ
                                    </a>
                                )}
                                {users.next_page_url && (
                                    <a
                                        href={users.next_page_url}
                                        className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        次へ
                                    </a>
                                )}
                            </div>
                            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm text-gray-700">
                                        <span className="font-medium">{users.from}</span> - <span className="font-medium">{users.to}</span> / <span className="font-medium">{users.total}</span>件
                                    </p>
                                </div>
                                <div className="flex space-x-1">
                                    {Array.from({ length: users.last_page }, (_, i) => i + 1).map((page) => (
                                        <button
                                            key={page}
                                            onClick={() => handleFilter('page', page)}
                                            className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                users.current_page === page
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'
                                            }`}
                                        >
                                            {page}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
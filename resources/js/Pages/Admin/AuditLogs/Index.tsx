import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, User } from '@/types';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface AuditLog {
    id: number;
    user_id: number | null;
    action: string;
    resource_type: string | null;
    resource_id: number | null;
    metadata: Record<string, any>;
    created_at: string;
    user?: User;
}

interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    data: AuditLog[];
}

interface AuditLogsIndexProps extends PageProps {
    logs: PaginationData;
    filters: {
        search?: string;
        action?: string;
        resource_type?: string;
        user_id?: number;
        date_from?: string;
        date_to?: string;
        ip_address?: string;
        suspicious?: boolean;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
    stats: {
        total_logs: number;
        unique_users: number;
        security_events: number;
        admin_actions: number;
    };
    filterOptions: {
        actions: string[];
        resource_types: string[];
        users: User[];
    };
}

export default function Index({ auth, logs, filters, stats, filterOptions }: AuditLogsIndexProps) {
    const [showFilters, setShowFilters] = useState(false);
    const [showExportModal, setShowExportModal] = useState(false);
    const [isExporting, setIsExporting] = useState(false);

    const handleFilter = (key: string, value: any) => {
        const newFilters = { ...filters, [key]: value };
        if (!value) delete newFilters[key];
        
        router.get('/admin/audit-logs', newFilters, {
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

    const handleExport = async (format: string) => {
        setIsExporting(true);
        try {
            const response = await fetch('/admin/audit-logs/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    format,
                    ...filters
                })
            });

            const data = await response.json();
            if (data.success) {
                // Base64データをダウンロード
                const blob = new Blob([atob(data.data)], { type: data.mime_type });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                setShowExportModal(false);
            } else {
                alert('エクスポートに失敗しました: ' + data.message);
            }
        } catch (error) {
            alert('エクスポートに失敗しました');
        } finally {
            setIsExporting(false);
        }
    };

    const getActionBadge = (action: string) => {
        if (action.includes('failed') || action.includes('suspicious') || action.includes('unauthorized')) {
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">セキュリティ</span>;
        }
        if (action.includes('admin') || action.includes('bulk_')) {
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">管理者</span>;
        }
        if (action.includes('login') || action.includes('logout')) {
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">認証</span>;
        }
        return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">一般</span>;
    };

    const getResourceIcon = (resourceType: string | null) => {
        switch (resourceType) {
            case 'user': return '👤';
            case 'message': return '💬';
            case 'channel': return '#️⃣';
            case 'workspace': return '🏢';
            case 'system': return '⚙️';
            default: return '📄';
        }
    };

    const getSortIcon = (column: string) => {
        if (filters.sort_by !== column) return null;
        return filters.sort_order === 'asc' ? '↑' : '↓';
    };

    const formatMetadata = (metadata: Record<string, any>) => {
        const important = ['ip_address', 'user_agent', 'reason', 'old_status', 'new_status'];
        const filtered = Object.entries(metadata)
            .filter(([key]) => important.includes(key))
            .slice(0, 3);
        
        return filtered.map(([key, value]) => `${key}: ${value}`).join(', ');
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            📋 監査ログ
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            システム内の全ての操作ログを確認できます
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="監査ログ" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* 統計カード */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="text-sm font-medium text-gray-500">総ログ数</div>
                                <div className="ml-auto text-2xl font-semibold text-gray-900">{stats.total_logs.toLocaleString()}</div>
                            </div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="text-sm font-medium text-gray-500">ユニークユーザー</div>
                                <div className="ml-auto text-2xl font-semibold text-blue-600">{stats.unique_users}</div>
                            </div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="text-sm font-medium text-gray-500">セキュリティイベント</div>
                                <div className="ml-auto text-2xl font-semibold text-red-600">{stats.security_events}</div>
                            </div>
                        </div>
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="text-sm font-medium text-gray-500">管理者操作</div>
                                <div className="ml-auto text-2xl font-semibold text-purple-600">{stats.admin_actions}</div>
                            </div>
                        </div>
                    </div>

                    {/* 検索・フィルター */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div className="flex items-center space-x-4">
                                <input
                                    type="text"
                                    placeholder="アクション、リソース、ユーザーで検索..."
                                    value={filters.search || ''}
                                    onChange={(e) => handleFilter('search', e.target.value)}
                                    className="border-gray-300 rounded-md shadow-sm text-sm w-64"
                                />
                                <button
                                    onClick={() => setShowFilters(!showFilters)}
                                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    🔍 詳細フィルター
                                </button>
                                <button
                                    onClick={() => handleFilter('suspicious', !filters.suspicious)}
                                    className={`inline-flex items-center px-3 py-2 border shadow-sm text-sm leading-4 font-medium rounded-md ${
                                        filters.suspicious
                                            ? 'bg-red-100 text-red-800 border-red-300'
                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                    }`}
                                >
                                    🚨 セキュリティのみ
                                </button>
                            </div>
                            
                            <div className="flex items-center space-x-2">
                                <button
                                    onClick={() => setShowExportModal(true)}
                                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    📤 エクスポート
                                </button>
                                <select
                                    value={filters.per_page || 50}
                                    onChange={(e) => handleFilter('per_page', e.target.value)}
                                    className="text-sm border-gray-300 rounded-md"
                                >
                                    <option value="25">25件</option>
                                    <option value="50">50件</option>
                                    <option value="100">100件</option>
                                    <option value="200">200件</option>
                                </select>
                            </div>
                        </div>

                        {showFilters && (
                            <div className="mt-4 pt-4 border-t border-gray-200">
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">アクション</label>
                                        <select
                                            value={filters.action || ''}
                                            onChange={(e) => handleFilter('action', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                        >
                                            <option value="">すべて</option>
                                            {filterOptions.actions.map(action => (
                                                <option key={action} value={action}>
                                                    {action}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">リソース種別</label>
                                        <select
                                            value={filters.resource_type || ''}
                                            onChange={(e) => handleFilter('resource_type', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                        >
                                            <option value="">すべて</option>
                                            {filterOptions.resource_types.map(type => (
                                                <option key={type} value={type}>
                                                    {type}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">ユーザー</label>
                                        <select
                                            value={filters.user_id || ''}
                                            onChange={(e) => handleFilter('user_id', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                        >
                                            <option value="">すべて</option>
                                            {filterOptions.users.map(user => (
                                                <option key={user.id} value={user.id}>
                                                    {user.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">IPアドレス</label>
                                        <input
                                            type="text"
                                            value={filters.ip_address || ''}
                                            onChange={(e) => handleFilter('ip_address', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                            placeholder="192.168.1.1"
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">開始日</label>
                                        <input
                                            type="date"
                                            value={filters.date_from || ''}
                                            onChange={(e) => handleFilter('date_from', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">終了日</label>
                                        <input
                                            type="date"
                                            value={filters.date_to || ''}
                                            onChange={(e) => handleFilter('date_to', e.target.value)}
                                            className="w-full text-sm border-gray-300 rounded-md"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ログ一覧 */}
                    <div className="bg-white shadow overflow-hidden sm:rounded-md">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('created_at')}
                                        >
                                            日時 {getSortIcon('created_at')}
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ユーザー
                                        </th>
                                        <th
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('action')}
                                        >
                                            アクション {getSortIcon('action')}
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            リソース
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            詳細
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            IP
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {logs.data.map((log) => (
                                        <tr key={log.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {format(new Date(log.created_at), 'MM/dd HH:mm:ss', { locale: ja })}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    {log.user ? (
                                                        <>
                                                            {log.user.avatar_url && (
                                                                <img
                                                                    src={log.user.avatar_url}
                                                                    alt=""
                                                                    className="h-6 w-6 rounded-full mr-2"
                                                                />
                                                            )}
                                                            <div className="text-sm font-medium text-gray-900">
                                                                {log.user.name}
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <span className="text-sm text-gray-500">システム</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center space-x-2">
                                                    {getActionBadge(log.action)}
                                                    <span className="text-sm text-gray-900">{log.action}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {log.resource_type && (
                                                    <div className="flex items-center">
                                                        <span className="mr-1">{getResourceIcon(log.resource_type)}</span>
                                                        <span>{log.resource_type}</span>
                                                        {log.resource_id && (
                                                            <span className="ml-1 text-gray-500">#{log.resource_id}</span>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                                {formatMetadata(log.metadata)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {log.metadata.ip_address || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a
                                                    href={`/admin/audit-logs/${log.id}`}
                                                    className="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    詳細
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ページネーション */}
                        <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div className="flex-1 flex justify-between sm:hidden">
                                {logs.prev_page_url && (
                                    <a
                                        href={logs.prev_page_url}
                                        className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        前へ
                                    </a>
                                )}
                                {logs.next_page_url && (
                                    <a
                                        href={logs.next_page_url}
                                        className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        次へ
                                    </a>
                                )}
                            </div>
                            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm text-gray-700">
                                        <span className="font-medium">{logs.from}</span> - <span className="font-medium">{logs.to}</span> / <span className="font-medium">{logs.total}</span>件
                                    </p>
                                </div>
                                <div className="flex space-x-1">
                                    {Array.from({ length: Math.min(10, logs.last_page) }, (_, i) => {
                                        let page;
                                        if (logs.last_page <= 10) {
                                            page = i + 1;
                                        } else {
                                            const current = logs.current_page;
                                            const start = Math.max(1, current - 4);
                                            page = start + i;
                                        }
                                        return page <= logs.last_page ? (
                                            <button
                                                key={page}
                                                onClick={() => handleFilter('page', page)}
                                                className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                    logs.current_page === page
                                                        ? 'bg-indigo-600 text-white'
                                                        : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'
                                                }`}
                                            >
                                                {page}
                                            </button>
                                        ) : null;
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* エクスポートモーダル */}
                    {showExportModal && (
                        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                            <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                <div className="mt-3 text-center">
                                    <h3 className="text-lg font-medium text-gray-900">ログをエクスポート</h3>
                                    <div className="mt-4 space-y-3">
                                        <button
                                            onClick={() => handleExport('csv')}
                                            disabled={isExporting}
                                            className="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            CSV形式でエクスポート
                                        </button>
                                        <button
                                            onClick={() => handleExport('json')}
                                            disabled={isExporting}
                                            className="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                        >
                                            JSON形式でエクスポート
                                        </button>
                                    </div>
                                    <div className="mt-4">
                                        <button
                                            onClick={() => setShowExportModal(false)}
                                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
                                        >
                                            キャンセル
                                        </button>
                                    </div>
                                    {isExporting && (
                                        <div className="mt-3 text-sm text-gray-500">
                                            エクスポート中...
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
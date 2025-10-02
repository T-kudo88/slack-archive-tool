import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, User } from '@/types';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface AuditLog {
    id: number;
    action: string;
    resource_type: string | null;
    resource_id: number | null;
    metadata: Record<string, any> | null;
    created_at: string;
    admin_user?: User;
    accessed_user?: {
        id: number | null;
        name: string | null;
        email: string | null;
    };
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

    const formatMetadata = (metadata: Record<string, any> | null | undefined) => {
        const meta = metadata ?? {};
        const important = ['ip_address', 'user_agent', 'reason', 'old_status', 'new_status'];
        const filtered = Object.entries(meta)
            .filter(([key]) => important.includes(key))
            .slice(0, 3);
        
        return filtered.map(([key, value]) => `${key}: ${value}`).join(', ');
    };

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">📋 監査ログ</h2>
                    <p className="text-sm text-gray-600 mt-1">システム内の全ての操作ログを確認できます</p>
                </div>
            </div>
        }>
            <Head title="監査ログ" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* ログ一覧 */}
                    <div className="bg-white shadow overflow-hidden sm:rounded-md">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500">日時</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500">ユーザー</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500">アクション</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500">リソース</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500">詳細</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500">IP</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {logs.data.map((log) => (
                                        <tr key={log.id}>
                                            <td className="px-6 py-4 text-sm">{format(new Date(log.created_at), 'MM/dd HH:mm:ss', { locale: ja })}</td>
                                            <td className="px-6 py-4 text-sm">
                                                {log.admin_user ? (
                                                    <div>
                                                        管理者: {log.admin_user.name} ({log.admin_user.email})<br />
                                                        {log.accessed_user && (
                                                            <>対象: {log.accessed_user.name} ({log.accessed_user.email})</>
                                                        )}
                                                    </div>
                                                ) : <span>システム</span>}
                                            </td>
                                            <td className="px-6 py-4">{getActionBadge(log.action)} {log.action}</td>
                                            <td className="px-6 py-4">{getResourceIcon(log.resource_type)} {log.resource_type} #{log.resource_id}</td>
                                            <td className="px-6 py-4">{formatMetadata(log.metadata)}</td>
                                            <td className="px-6 py-4">{log.metadata?.ip_address || '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
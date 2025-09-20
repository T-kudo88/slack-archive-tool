import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, User } from '@/types';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface SecurityLog {
    id: number;
    user_id: number | null;
    action: string;
    resource_type: string | null;
    resource_id: number | null;
    metadata: Record<string, any>;
    created_at: string;
    user?: User;
}

interface SuspiciousIP {
    ip_address: string;
    attempt_count: number;
    user_count: number;
    last_attempt: string;
}

interface BruteForceAttempt {
    ip_address: string;
    failed_attempts: number;
    last_attempt: string;
}

interface SecurityDashboardProps extends PageProps {
    securityLogs: SecurityLog[];
    suspiciousIPs: SuspiciousIP[];
    bruteForceAttempts: BruteForceAttempt[];
}

export default function SecurityDashboard({ auth, securityLogs, suspiciousIPs, bruteForceAttempts }: SecurityDashboardProps) {
    const [realTimeAlerts, setRealTimeAlerts] = useState<SecurityLog[]>([]);
    const [selectedIP, setSelectedIP] = useState<string | null>(null);
    const [isBlocking, setIsBlocking] = useState(false);

    useEffect(() => {
        // リアルタイム更新のシミュレーション（実際の実装ではWebSocketやSSEを使用）
        const interval = setInterval(async () => {
            try {
                const response = await fetch('/admin/security/real-time-alerts');
                if (response.ok) {
                    const newAlerts = await response.json();
                    setRealTimeAlerts(newAlerts);
                }
            } catch (error) {
                console.error('Failed to fetch real-time alerts:', error);
            }
        }, 30000); // 30秒間隔

        return () => clearInterval(interval);
    }, []);

    const handleBlockIP = async (ipAddress: string) => {
        setIsBlocking(true);
        try {
            const response = await fetch('/admin/security/block-ip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    ip_address: ipAddress,
                    reason: 'Suspicious activity detected from admin dashboard'
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('IPアドレスをブロックしました');
                window.location.reload();
            } else {
                alert('IPブロックに失敗しました: ' + data.message);
            }
        } catch (error) {
            alert('IPブロックに失敗しました');
        } finally {
            setIsBlocking(false);
        }
    };

    const getAlertSeverity = (action: string): { color: string; icon: string; label: string } => {
        if (action.includes('failed') || action.includes('unauthorized')) {
            return { color: 'red', icon: '🚨', label: '高' };
        }
        if (action.includes('suspicious') || action.includes('unusual')) {
            return { color: 'yellow', icon: '⚠️', label: '中' };
        }
        return { color: 'blue', icon: 'ℹ️', label: '低' };
    };

    const formatTimeAgo = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));
        
        if (diffInMinutes < 1) return 'たった今';
        if (diffInMinutes < 60) return `${diffInMinutes}分前`;
        if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}時間前`;
        return `${Math.floor(diffInMinutes / 1440)}日前`;
    };

    const getThreatLevel = () => {
        const highThreats = securityLogs.filter(log => 
            log.action.includes('failed') || log.action.includes('unauthorized')
        ).length;
        
        if (highThreats > 10) return { level: '高', color: 'red', description: '複数の重大な脅威を検出' };
        if (highThreats > 5) return { level: '中', color: 'yellow', description: 'いくつかの脅威を検出' };
        return { level: '低', color: 'green', description: '現在脅威は検出されていません' };
    };

    const threatLevel = getThreatLevel();

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            🛡️ セキュリティダッシュボード
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            システムのセキュリティ状態を監視します
                        </p>
                    </div>
                    <div className="flex items-center space-x-3 mt-4 sm:mt-0">
                        <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-${threatLevel.color}-100 text-${threatLevel.color}-800`}>
                            <span className="mr-1">🛡️</span>
                            脅威レベル: {threatLevel.level}
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="セキュリティダッシュボード" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* 概要カード */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <span className="text-2xl">🚨</span>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <div className="text-sm font-medium text-gray-500">セキュリティイベント</div>
                                    <div className="text-2xl font-semibold text-red-600">{securityLogs.length}</div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <span className="text-2xl">🌐</span>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <div className="text-sm font-medium text-gray-500">疑わしいIP</div>
                                    <div className="text-2xl font-semibold text-yellow-600">{suspiciousIPs.length}</div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <span className="text-2xl">🔒</span>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <div className="text-sm font-medium text-gray-500">ブルートフォース</div>
                                    <div className="text-2xl font-semibold text-red-600">{bruteForceAttempts.length}</div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg p-4">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <span className="text-2xl">⚡</span>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <div className="text-sm font-medium text-gray-500">リアルタイム監視</div>
                                    <div className="text-sm font-semibold text-green-600">アクティブ</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* 脅威レベル詳細 */}
                    <div className="bg-white shadow rounded-lg p-6 mb-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">現在の脅威レベル</h3>
                                <p className="text-sm text-gray-600 mt-1">{threatLevel.description}</p>
                            </div>
                            <div className={`inline-flex items-center px-4 py-2 rounded-full text-lg font-medium bg-${threatLevel.color}-100 text-${threatLevel.color}-800`}>
                                {threatLevel.level}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* ブルートフォース攻撃 */}
                        {bruteForceAttempts.length > 0 && (
                            <div className="bg-white shadow rounded-lg p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg font-medium text-gray-900">🔒 ブルートフォース攻撃</h3>
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        緊急
                                    </span>
                                </div>
                                <div className="space-y-3">
                                    {bruteForceAttempts.slice(0, 5).map((attempt, index) => (
                                        <div key={index} className="border-l-4 border-red-500 bg-red-50 p-3">
                                            <div className="flex justify-between items-start">
                                                <div>
                                                    <div className="font-medium text-red-800">{attempt.ip_address}</div>
                                                    <div className="text-sm text-red-600">
                                                        {attempt.failed_attempts}回の失敗試行
                                                    </div>
                                                    <div className="text-xs text-red-500">
                                                        最終試行: {formatTimeAgo(attempt.last_attempt)}
                                                    </div>
                                                </div>
                                                <button
                                                    onClick={() => handleBlockIP(attempt.ip_address)}
                                                    disabled={isBlocking}
                                                    className="px-3 py-1 bg-red-600 text-white text-xs rounded-md hover:bg-red-700 disabled:opacity-50"
                                                >
                                                    ブロック
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* 疑わしいIPアドレス */}
                        <div className="bg-white shadow rounded-lg p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-medium text-gray-900">🌐 疑わしいIPアドレス</h3>
                                <span className="text-sm text-gray-500">過去7日間</span>
                            </div>
                            <div className="space-y-3">
                                {suspiciousIPs.slice(0, 8).map((ip, index) => (
                                    <div
                                        key={index}
                                        className={`p-3 rounded-lg border-l-4 cursor-pointer hover:bg-gray-50 ${
                                            ip.attempt_count > 50 ? 'border-red-500 bg-red-50' : 'border-yellow-500 bg-yellow-50'
                                        }`}
                                        onClick={() => setSelectedIP(ip.ip_address)}
                                    >
                                        <div className="flex justify-between items-start">
                                            <div>
                                                <div className="font-medium text-gray-900">{ip.ip_address}</div>
                                                <div className="text-sm text-gray-600">
                                                    {ip.attempt_count}回のアクセス, {ip.user_count}人のユーザー
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    最終アクセス: {formatTimeAgo(ip.last_attempt)}
                                                </div>
                                            </div>
                                            <div className="flex space-x-2">
                                                <button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        window.open(`/admin/audit-logs?ip_address=${ip.ip_address}`, '_blank');
                                                    }}
                                                    className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-md hover:bg-blue-200"
                                                >
                                                    詳細
                                                </button>
                                                {ip.attempt_count > 20 && (
                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleBlockIP(ip.ip_address);
                                                        }}
                                                        className="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-md hover:bg-red-200"
                                                    >
                                                        ブロック
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* 最近のセキュリティイベント */}
                        <div className="lg:col-span-2 bg-white shadow rounded-lg p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-medium text-gray-900">📋 最近のセキュリティイベント</h3>
                                <a
                                    href="/admin/audit-logs?suspicious=1"
                                    className="text-sm text-blue-600 hover:text-blue-800"
                                >
                                    すべて見る →
                                </a>
                            </div>
                            <div className="space-y-3">
                                {securityLogs.slice(0, 10).map((log) => {
                                    const severity = getAlertSeverity(log.action);
                                    return (
                                        <div
                                            key={log.id}
                                            className={`p-3 rounded-lg border-l-4 border-${severity.color}-500 bg-${severity.color}-50`}
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <div className="flex items-center space-x-2 mb-1">
                                                        <span>{severity.icon}</span>
                                                        <span className="font-medium text-gray-900">{log.action}</span>
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-${severity.color}-100 text-${severity.color}-800`}>
                                                            {severity.label}
                                                        </span>
                                                    </div>
                                                    <div className="text-sm text-gray-600">
                                                        {log.user ? (
                                                            <>ユーザー: {log.user.name}</>
                                                        ) : (
                                                            'システム'
                                                        )}
                                                        {log.metadata.ip_address && (
                                                            <> • IP: {log.metadata.ip_address}</>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-gray-500 mt-1">
                                                        {format(new Date(log.created_at), 'yyyy/MM/dd HH:mm:ss', { locale: ja })}
                                                    </div>
                                                </div>
                                                <a
                                                    href={`/admin/audit-logs/${log.id}`}
                                                    className="text-sm text-blue-600 hover:text-blue-800 ml-4"
                                                >
                                                    詳細
                                                </a>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    {/* セキュリティ推奨事項 */}
                    <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h3 className="text-lg font-medium text-blue-900 mb-4">💡 セキュリティ推奨事項</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="bg-white rounded-lg p-4 border border-blue-200">
                                <h4 className="font-medium text-gray-900 mb-2">🔐 強固なパスワード</h4>
                                <p className="text-sm text-gray-600">
                                    全ユーザーに強固なパスワードの使用と定期的な更新を推奨してください。
                                </p>
                            </div>
                            <div className="bg-white rounded-lg p-4 border border-blue-200">
                                <h4 className="font-medium text-gray-900 mb-2">🛡️ 2段階認証</h4>
                                <p className="text-sm text-gray-600">
                                    管理者アカウントでは2段階認証の有効化を強く推奨します。
                                </p>
                            </div>
                            <div className="bg-white rounded-lg p-4 border border-blue-200">
                                <h4 className="font-medium text-gray-900 mb-2">📊 定期監査</h4>
                                <p className="text-sm text-gray-600">
                                    監査ログを定期的にレビューして、異常なアクティビティを早期発見してください。
                                </p>
                            </div>
                            <div className="bg-white rounded-lg p-4 border border-blue-200">
                                <h4 className="font-medium text-gray-900 mb-2">🔄 自動更新</h4>
                                <p className="text-sm text-gray-600">
                                    システムとセキュリティパッチの自動更新を有効にしてください。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
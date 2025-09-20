import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Workspace, Channel } from '@/types';

interface ExportButtonProps {
    currentFilters?: {
        workspace_id?: number;
        channel_id?: number;
        date_from?: string;
        date_to?: string;
        search?: string;
        message_type?: string;
    };
    filterOptions?: {
        workspaces: Workspace[];
        channels: Channel[];
    };
    className?: string;
    variant?: 'primary' | 'secondary' | 'outline';
    size?: 'sm' | 'md' | 'lg';
    totalMessages?: number;
}

interface ExportSettings {
    format: 'json' | 'csv' | 'txt';
    workspace_id: number | '';
    channel_id: number | '';
    date_from: string;
    date_to: string;
    includeThreads: boolean;
    includeReactions: boolean;
    includeFiles: boolean;
}

const ExportButton: React.FC<ExportButtonProps> = ({
    currentFilters = {},
    filterOptions = { workspaces: [], channels: [] },
    className = '',
    variant = 'secondary',
    size = 'md',
    totalMessages = 0
}) => {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isExporting, setIsExporting] = useState(false);
    const [exportProgress, setExportProgress] = useState<{
        status: 'preparing' | 'exporting' | 'completed' | 'error';
        message: string;
        downloadUrl?: string;
        filename?: string;
    } | null>(null);

    const [settings, setSettings] = useState<ExportSettings>({
        format: 'json',
        workspace_id: currentFilters.workspace_id || '',
        channel_id: currentFilters.channel_id || '',
        date_from: currentFilters.date_from || '',
        date_to: currentFilters.date_to || '',
        includeThreads: true,
        includeReactions: true,
        includeFiles: false
    });

    const availableChannels = settings.workspace_id
        ? filterOptions.channels.filter(channel => channel.workspace_id === Number(settings.workspace_id))
        : filterOptions.channels;

    const handleSettingChange = <K extends keyof ExportSettings>(
        key: K,
        value: ExportSettings[K]
    ) => {
        setSettings(prev => ({
            ...prev,
            [key]: value
        }));

        // ワークスペース変更時にチャンネルをリセット
        if (key === 'workspace_id' && settings.channel_id) {
            const channelExists = availableChannels.find(
                channel => channel.id === Number(settings.channel_id)
            );
            if (!channelExists) {
                setSettings(prev => ({ ...prev, channel_id: '' }));
            }
        }
    };

    const handleExport = async () => {
        setIsExporting(true);
        setExportProgress({
            status: 'preparing',
            message: 'エクスポートを準備しています...'
        });

        try {
            // エクスポート設定を準備
            const exportData = Object.fromEntries(
                Object.entries(settings).filter(([_, value]) => 
                    value !== '' && value !== false && value !== null && value !== undefined
                )
            );

            const response = await fetch('/messages/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(exportData)
            });

            if (!response.ok) {
                throw new Error(`エクスポートに失敗しました: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                setExportProgress({
                    status: 'completed',
                    message: `${result.message_count}件のメッセージをエクスポートしました！`,
                    downloadUrl: result.download_url,
                    filename: result.filename
                });
            } else {
                throw new Error(result.error || 'エクスポートに失敗しました');
            }

        } catch (error) {
            console.error('Export error:', error);
            setExportProgress({
                status: 'error',
                message: error instanceof Error ? error.message : 'エクスポート中にエラーが発生しました'
            });
        } finally {
            setIsExporting(false);
        }
    };

    const handleDownload = () => {
        if (exportProgress?.downloadUrl) {
            window.location.href = exportProgress.downloadUrl;
        }
    };

    const resetModal = () => {
        setIsModalOpen(false);
        setIsExporting(false);
        setExportProgress(null);
    };

    const getButtonClasses = () => {
        const baseClasses = 'inline-flex items-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2';
        
        const sizeClasses = {
            sm: 'px-3 py-2 text-sm',
            md: 'px-4 py-2 text-sm',
            lg: 'px-6 py-3 text-base'
        };

        const variantClasses = {
            primary: 'text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500',
            secondary: 'text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:ring-indigo-500',
            outline: 'text-indigo-700 bg-white border border-indigo-300 hover:bg-indigo-50 focus:ring-indigo-500'
        };

        return `${baseClasses} ${sizeClasses[size]} ${variantClasses[variant]} ${className}`;
    };

    const getFormatDescription = (format: string) => {
        switch (format) {
            case 'json':
                return '構造化データ形式。プログラムでの処理に適しています。';
            case 'csv':
                return 'スプレッドシート形式。ExcelやGoogleシートで開けます。';
            case 'txt':
                return 'プレーンテキスト形式。読みやすい形式で保存されます。';
            default:
                return '';
        }
    };

    return (
        <>
            <button
                onClick={() => setIsModalOpen(true)}
                className={getButtonClasses()}
                disabled={isExporting}
            >
                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                エクスポート
                {totalMessages > 0 && (
                    <span className="ml-1 text-xs opacity-75">({totalMessages.toLocaleString()}件)</span>
                )}
            </button>

            <Modal show={isModalOpen} onClose={resetModal} maxWidth="2xl">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg font-medium text-gray-900">
                            メッセージのエクスポート
                        </h3>
                        <button
                            onClick={resetModal}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* エクスポート進行状況 */}
                    {exportProgress && (
                        <div className={`mb-6 p-4 rounded-lg border ${
                            exportProgress.status === 'error' 
                                ? 'bg-red-50 border-red-200 text-red-800'
                                : exportProgress.status === 'completed'
                                    ? 'bg-green-50 border-green-200 text-green-800'
                                    : 'bg-blue-50 border-blue-200 text-blue-800'
                        }`}>
                            <div className="flex items-center">
                                {exportProgress.status === 'preparing' || exportProgress.status === 'exporting' ? (
                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                ) : exportProgress.status === 'completed' ? (
                                    <svg className="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                    </svg>
                                ) : (
                                    <svg className="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                    </svg>
                                )}
                                <span>{exportProgress.message}</span>
                            </div>

                            {exportProgress.status === 'completed' && exportProgress.downloadUrl && (
                                <div className="mt-3">
                                    <button
                                        onClick={handleDownload}
                                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        ダウンロード
                                    </button>
                                </div>
                            )}
                        </div>
                    )}

                    {/* エクスポート設定フォーム */}
                    {!exportProgress && (
                        <form onSubmit={(e) => { e.preventDefault(); handleExport(); }}>
                            <div className="space-y-4">
                                {/* フォーマット選択 */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        エクスポート形式
                                    </label>
                                    <div className="grid grid-cols-3 gap-3">
                                        {(['json', 'csv', 'txt'] as const).map(format => (
                                            <label
                                                key={format}
                                                className={`relative flex cursor-pointer rounded-lg border p-4 focus:outline-none ${
                                                    settings.format === format
                                                        ? 'border-indigo-600 ring-2 ring-indigo-600 bg-indigo-50'
                                                        : 'border-gray-300 hover:border-gray-400'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="format"
                                                    value={format}
                                                    checked={settings.format === format}
                                                    onChange={(e) => handleSettingChange('format', e.target.value as any)}
                                                    className="sr-only"
                                                />
                                                <div className="flex flex-col">
                                                    <span className="block text-sm font-medium text-gray-900 uppercase">
                                                        {format}
                                                    </span>
                                                    <span className="mt-1 flex items-center text-xs text-gray-500">
                                                        {getFormatDescription(format)}
                                                    </span>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                {/* フィルター設定 */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            ワークスペース
                                        </label>
                                        <select
                                            value={settings.workspace_id}
                                            onChange={(e) => handleSettingChange('workspace_id', e.target.value ? Number(e.target.value) : '')}
                                            className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">すべてのワークスペース</option>
                                            {filterOptions.workspaces.map(workspace => (
                                                <option key={workspace.id} value={workspace.id}>
                                                    {workspace.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            チャンネル
                                        </label>
                                        <select
                                            value={settings.channel_id}
                                            onChange={(e) => handleSettingChange('channel_id', e.target.value ? Number(e.target.value) : '')}
                                            className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">すべてのチャンネル</option>
                                            {availableChannels.map(channel => (
                                                <option key={channel.id} value={channel.id}>
                                                    {channel.is_dm ? '📧' : channel.is_private ? '🔒' : '#'} {channel.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            開始日
                                        </label>
                                        <input
                                            type="date"
                                            value={settings.date_from}
                                            onChange={(e) => handleSettingChange('date_from', e.target.value)}
                                            className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            終了日
                                        </label>
                                        <input
                                            type="date"
                                            value={settings.date_to}
                                            onChange={(e) => handleSettingChange('date_to', e.target.value)}
                                            min={settings.date_from || undefined}
                                            className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>

                                {/* オプション設定 */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        エクスポートオプション
                                    </label>
                                    <div className="space-y-2">
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={settings.includeThreads}
                                                onChange={(e) => handleSettingChange('includeThreads', e.target.checked)}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">スレッド返信を含める</span>
                                        </label>
                                        
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={settings.includeReactions}
                                                onChange={(e) => handleSettingChange('includeReactions', e.target.checked)}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">リアクション情報を含める</span>
                                        </label>
                                        
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={settings.includeFiles}
                                                onChange={(e) => handleSettingChange('includeFiles', e.target.checked)}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">ファイル添付情報を含める</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* アクションボタン */}
                            <div className="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                                <SecondaryButton onClick={resetModal} type="button">
                                    キャンセル
                                </SecondaryButton>
                                <PrimaryButton
                                    type="submit"
                                    disabled={isExporting}
                                >
                                    {isExporting ? (
                                        <div className="flex items-center">
                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            エクスポート中...
                                        </div>
                                    ) : (
                                        'エクスポート開始'
                                    )}
                                </PrimaryButton>
                            </div>
                        </form>
                    )}
                </div>
            </Modal>
        </>
    );
};

export default ExportButton;
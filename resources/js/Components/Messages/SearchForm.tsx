import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Workspace, Channel } from '@/types';

interface SearchFormProps {
  initialFilters?: {
    search?: string;
    workspace_id?: number | string;
    channel_id?: number | string;
    date_from?: string;
    date_to?: string;
    message_type?: string;
    per_page?: number;
  };
  filterOptions: {
    workspaces: Workspace[];
    channels: Channel[];
    messageTypes: string[];
  };
  onSearch?: (filters: any) => void;
  isLoading?: boolean;
}

const SearchForm: React.FC<SearchFormProps> = ({
  initialFilters = {},
  filterOptions,
  onSearch,
  isLoading = false,
}) => {
  const [filters, setFilters] = useState({
    search: initialFilters.search ?? '',
    workspace_id: initialFilters.workspace_id ?? '',
    channel_id: initialFilters.channel_id ?? '',
    date_from: initialFilters.date_from ?? '',
    date_to: initialFilters.date_to ?? '',
    message_type: initialFilters.message_type ?? 'all',
    per_page: Number(initialFilters.per_page ?? 25),
  });

  const [isExpanded, setIsExpanded] = useState(false);
  const [availableChannels, setAvailableChannels] = useState<Channel[]>([]);

  // 🔹 props(initialFilters) の更新に追従
  useEffect(() => {
    setFilters({
      search: initialFilters.search ?? '',
      workspace_id: initialFilters.workspace_id ?? '',
      channel_id: initialFilters.channel_id ?? '',
      date_from: initialFilters.date_from ?? '',
      date_to: initialFilters.date_to ?? '',
      message_type: initialFilters.message_type ?? 'all',
      per_page: Number(initialFilters.per_page ?? 25),
    });
  }, [initialFilters]);

  // 🔹 ワークスペース変更時にチャンネルをフィルタリング
  useEffect(() => {
    if (filters.workspace_id) {
      const workspaceChannels = filterOptions.channels.filter(
        (channel) => channel.workspace_id === Number(filters.workspace_id)
      );
      setAvailableChannels(workspaceChannels);

      if (
        filters.channel_id &&
        !workspaceChannels.find((channel) => channel.id === Number(filters.channel_id))
      ) {
        setFilters((prev) => ({ ...prev, channel_id: '' }));
      }
    } else {
      setAvailableChannels(filterOptions.channels);
    }
  }, [filters.workspace_id, filterOptions.channels]);

  const handleInputChange = (field: string, value: string | number) => {
    setFilters((prev) => ({
      ...prev,
      [field]: value,
    }));
  };

  const handleSearch = () => {
    const params = {
      ...filters,
      per_page: Number(filters.per_page || 25),
    };

    if (onSearch) {
      onSearch(params);
    } else {
      router.get('/messages', params, {
        preserveScroll: true,
        preserveState: true,
      });
    }
  };

  const handleReset = () => {
    const resetFilters = {
      search: '',
      workspace_id: '',
      channel_id: '',
      date_from: '',
      date_to: '',
      message_type: 'all',
      per_page: 25,
    };

    setFilters(resetFilters);

    if (onSearch) {
      onSearch(resetFilters);
    } else {
      router.get('/messages', resetFilters, {
        preserveScroll: true,
        preserveState: true,
      });
    }
  };

  const hasActiveFilters = Object.entries(filters).some(
    ([key, value]) =>
      key !== 'per_page' && key !== 'message_type' && value !== '' && value !== 'all'
  );

  return (
    <div className="bg-white shadow-sm border border-gray-200 rounded-lg mb-6">
      <div className="p-4 border-b border-gray-200">
        <div className="flex flex-col sm:flex-row gap-4">
          {/* 🔍 メイン検索フィールド */}
          <div className="flex-1">
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg
                  className="w-5 h-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  />
                </svg>
              </div>
              <TextInput
                type="text"
                placeholder="メッセージを検索..."
                value={filters.search}
                onChange={(e) => handleInputChange('search', e.target.value)}
                className="pl-10 w-full"
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
              />
            </div>
          </div>

          {/* 🔘 検索ボタン */}
          <div className="flex gap-2">
            <PrimaryButton onClick={handleSearch} disabled={isLoading}>
              {isLoading ? '検索中...' : '検索'}
            </PrimaryButton>

            <SecondaryButton
              onClick={() => setIsExpanded(!isExpanded)}
              className="flex items-center"
            >
              <svg
                className={`w-4 h-4 mr-1 transition-transform ${
                  isExpanded ? 'rotate-180' : ''
                }`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M19 9l-7 7-7-7"
                />
              </svg>
              詳細検索
            </SecondaryButton>
          </div>
        </div>

        {/* アクティブフィルター表示 */}
        {hasActiveFilters && (
          <div className="mt-3 flex flex-wrap gap-2">
            {filters.workspace_id && (
              <span className="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                ワークスペース: {filterOptions.workspaces.find((w) => w.id === Number(filters.workspace_id))?.name}
              </span>
            )}
            {filters.channel_id && (
              <span className="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                チャンネル: {availableChannels.find((c) => c.id === Number(filters.channel_id))?.name}
              </span>
            )}
            {filters.date_from && (
              <span className="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">
                開始日: {filters.date_from}
              </span>
            )}
            {filters.date_to && (
              <span className="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">
                終了日: {filters.date_to}
              </span>
            )}
            <button
              onClick={handleReset}
              className="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700 hover:bg-gray-200"
            >
              すべてクリア
            </button>
          </div>
        )}
      </div>

      {/* 詳細検索フォーム */}
      {isExpanded && (
        <div className="p-4 bg-gray-50 border-t border-gray-200">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {/* ワークスペース */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">ワークスペース</label>
              <select
                value={filters.workspace_id}
                onChange={(e) => handleInputChange('workspace_id', e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option value="">すべてのワークスペース</option>
                {filterOptions.workspaces.map((w) => (
                  <option key={w.id} value={w.id}>{w.name}</option>
                ))}
              </select>
            </div>

            {/* チャンネル */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">チャンネル</label>
              <select
                value={filters.channel_id}
                onChange={(e) => handleInputChange('channel_id', e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                disabled={!availableChannels.length}
              >
                <option value="">すべてのチャンネル</option>
                {availableChannels.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.is_dm ? '📧' : c.is_private ? '🔒' : '#'} {c.name}
                  </option>
                ))}
              </select>
            </div>

            {/* メッセージタイプ */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">メッセージタイプ</label>
              <select
                value={filters.message_type}
                onChange={(e) => handleInputChange('message_type', e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option value="all">すべてのタイプ</option>
                {filterOptions.messageTypes.map((t) => (
                  <option key={t} value={t}>{t}</option>
                ))}
              </select>
            </div>

            {/* 開始日 */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">開始日</label>
              <input
                type="date"
                value={filters.date_from}
                onChange={(e) => handleInputChange('date_from', e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
            </div>

            {/* 終了日 */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">終了日</label>
              <input
                type="date"
                value={filters.date_to}
                onChange={(e) => handleInputChange('date_to', e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                min={filters.date_from || undefined}
              />
            </div>

            {/* 表示件数 */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">表示件数</label>
              <select
                value={filters.per_page}
                onChange={(e) => handleInputChange('per_page', Number(e.target.value))}
                className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option value={25}>25件</option>
                <option value={50}>50件</option>
                <option value={100}>100件</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end gap-2 mt-4">
            <SecondaryButton onClick={handleReset}>リセット</SecondaryButton>
            <PrimaryButton onClick={handleSearch} disabled={isLoading}>検索実行</PrimaryButton>
          </div>
        </div>
      )}
    </div>
  );
};

export default SearchForm;
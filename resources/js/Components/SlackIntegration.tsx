import { useState } from 'react';
import { User } from '@/types';

interface Props {
    user: User;
    onIntegrationStart?: () => void;
}

const SlackIntegration: React.FC<Props> = ({ user, onIntegrationStart }) => {
    const [isConnecting, setIsConnecting] = useState(false);
    
    const isSlackConnected = Boolean(user.slack_user_id);
    
    const handleSlackConnect = () => {
        setIsConnecting(true);
        if (onIntegrationStart) {
            onIntegrationStart();
        }
        // Navigate to Slack OAuth
        window.location.href = '/auth/slack/redirect';
    };

    if (isSlackConnected) {
        return (
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-bold text-gray-900">Slack連携</h3>
                    <div className="flex items-center text-green-600">
                        <svg className="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                        </svg>
                        <span className="text-sm font-medium">連携済み</span>
                    </div>
                </div>
                
                <div className="space-y-3">
                    <div className="flex items-center space-x-4">
                        {user.avatar_url && (
                            <img
                                src={user.avatar_url}
                                alt="Slack Avatar"
                                className="w-12 h-12 rounded-full border-2 border-gray-200"
                            />
                        )}
                        <div>
                            <p className="text-sm font-medium text-gray-900">{user.name}</p>
                            <p className="text-sm text-gray-500">Slack ID: {user.slack_user_id}</p>
                            {user.last_login_at && (
                                <p className="text-xs text-gray-400">
                                    最終ログイン: {new Date(user.last_login_at).toLocaleString('ja-JP')}
                                </p>
                            )}
                        </div>
                    </div>
                    
                    <div className="bg-green-50 border border-green-200 rounded-md p-3">
                        <p className="text-sm text-green-800">
                            Slackアカウントと正常に連携されています。Slackからの通知やメッセージ機能が利用できます。
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white shadow-sm sm:rounded-lg p-6">
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-bold text-gray-900">Slack連携</h3>
                <div className="flex items-center text-gray-400">
                    <svg className="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="text-sm font-medium">未連携</span>
                </div>
            </div>
            
            <div className="space-y-4">
                <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div className="flex items-start">
                        <svg className="w-6 h-6 text-blue-600 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h4 className="text-sm font-medium text-blue-900 mb-1">Slack連携について</h4>
                            <ul className="text-sm text-blue-800 space-y-1">
                                <li>• Slackアカウントでログイン</li>
                                <li>• Slackチャンネルの履歴取得</li>
                                <li>• メッセージの検索・アーカイブ</li>
                                <li>• 通知の受信</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <button
                    onClick={handleSlackConnect}
                    disabled={isConnecting}
                    className={`
                        w-full flex items-center justify-center px-4 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white
                        ${isConnecting 
                            ? 'bg-gray-400 cursor-not-allowed' 
                            : 'bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500'
                        }
                        transition duration-200
                    `}
                >
                    {isConnecting ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Slackに接続中...
                        </>
                    ) : (
                        <>
                            <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M5.042 15.165a2.528 2.528 0 0 0 2.5 2.5c1.61 0 2.5-.89 2.5-2.5v-1.25h-2.5a2.528 2.528 0 0 0-2.5 2.5m0-5c0-1.61.89-2.5 2.5-2.5h1.25v2.5a2.528 2.528 0 0 1-2.5 2.5 2.528 2.528 0 0 1-2.5-2.5m5-5c0-1.61.89-2.5 2.5-2.5s2.5.89 2.5 2.5v1.25h-2.5a2.528 2.528 0 0 1-2.5-2.5m5 0a2.528 2.528 0 0 0 2.5-2.5c0-1.61-.89-2.5-2.5-2.5h-1.25v2.5c0 1.61.89 2.5 2.5 2.5m0 5a2.528 2.528 0 0 0 2.5 2.5c1.61 0 2.5-.89 2.5-2.5s-.89-2.5-2.5-2.5h-1.25v2.5c0 1.61.89 2.5 2.5 2.5"/>
                            </svg>
                            Slackと連携
                        </>
                    )}
                </button>
                
                <p className="text-xs text-gray-500 text-center">
                    Slackとの連携により、利用規約とプライバシーポリシーに同意したものとみなされます
                </p>
            </div>
        </div>
    );
};

export default SlackIntegration;
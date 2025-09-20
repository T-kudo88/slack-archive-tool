import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function SlackLogin({
    status,
    info,
    errors,
}: {
    status?: string;
    info?: string;
    errors?: { slack?: string };
}) {
    const handleSlackLogin = () => {
        window.location.href = route('slack.redirect');
    };

    return (
        <GuestLayout>
            <Head title="ログイン" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {info && (
                <div className="mb-4 text-sm font-medium text-blue-600">
                    {info}
                </div>
            )}

            {errors?.slack && (
                <div className="mb-4 text-sm font-medium text-red-600">
                    {errors.slack}
                </div>
            )}

            <div className="text-center">
                <h1 className="text-2xl font-bold text-gray-900 mb-6">
                    Slack Archive System
                </h1>
                
                <p className="text-gray-600 mb-8">
                    Slackアカウントでログインしてください
                </p>

                <button
                    onClick={handleSlackLogin}
                    className="inline-flex items-center px-6 py-3 bg-slack-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-slack-700 focus:bg-slack-700 active:bg-slack-900 focus:outline-none focus:ring-2 focus:ring-slack-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    style={{ backgroundColor: '#4A154B' }}
                >
                    <svg 
                        className="w-5 h-5 mr-2" 
                        viewBox="0 0 24 24" 
                        fill="currentColor"
                    >
                        <path d="M5.042 15.165a2.528 2.528 0 0 1-2.52-2.523A2.528 2.528 0 0 1 5.042 10.1h2.52v2.542a2.528 2.528 0 0 1-2.52 2.523z"/>
                        <path d="M6.313 17.7a2.528 2.528 0 0 1 2.521-2.523 2.528 2.528 0 0 1 2.521 2.523v6.349A2.528 2.528 0 0 1 8.834 26.6a2.528 2.528 0 0 1-2.521-2.551V17.7z"/>
                        <path d="M8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834.001a2.528 2.528 0 0 1 2.521 2.521v2.52H8.834z"/>
                        <path d="M6.313 6.313a2.528 2.528 0 0 1-2.521 2.521 2.528 2.528 0 0 1-2.52-2.521 2.528 2.528 0 0 1 2.52-2.521h6.35a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H6.313z"/>
                    </svg>
                    Slackでログイン
                </button>

                <div className="mt-8 text-sm text-gray-500">
                    <p>このシステムはSlack認証を使用しています。</p>
                    <p>ログインするにはSlackワークスペースのメンバーである必要があります。</p>
                </div>
            </div>
        </GuestLayout>
    );
}
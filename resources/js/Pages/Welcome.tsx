import React from 'react';
import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-100">
            <Head title="Welcome" />

            <h1 className="text-2xl font-bold mb-6">Slack Archive App</h1>

            <a
                href="/auth/slack/redirect"
                className="px-6 py-3 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition"
            >
                Slackでログイン
            </a>
        </div>
    );
}

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CORS対応のためのヘッダー設定
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.headers.common['Content-Type'] = 'application/json';

// baseURL を必ず https に固定する
const appUrl = import.meta.env.VITE_APP_URL || '';
window.axios.defaults.baseURL = appUrl.replace(/^http:\/\//, 'https://');

// ngrok環境での追加ヘッダー
if (appUrl.includes('ngrok-free.app') || appUrl.includes('ngrok.io')) {
    window.axios.defaults.headers.common['ngrok-skip-browser-warning'] = 'true';
}

// デバッグログ
console.log("✅ axios baseURL set to:", window.axios.defaults.baseURL);

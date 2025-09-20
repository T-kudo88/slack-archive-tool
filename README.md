📝 Ngrok URL 更新手順（Slack OAuth 用）

Slack OAuth を利用する際、ngrok の URL が変わるとリダイレクト先が一致せず認証エラーが発生します。
そのため、以下の手順で環境を更新してください。

⸻

1. .env を更新

.env 内の URL を新しい ngrok URL に置き換える。

```env
APP_URL=https://xxxxxx.ngrok-free.app
VITE_URL=https://xxxxxx.ngrok-free.app
VITE_APP_URL=https://xxxxxx.ngrok-free.app
ASSET_URL=https://xxxxxx.ngrok-free.app

SLACK_REDIRECT_URI=https://xxxxxx.ngrok-free.app/auth/slack/callback
```

⸻

2. Slack App 側を更新

Slack API 設定画面 → OAuth & Permissions
「Redirect URLs」に新しい URL を設定して保存する。

例:

```
https://xxxxxx.ngrok-free.app/auth/slack/callback
```

⸻

3. Laravel キャッシュをクリア

```bash
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

⸻

4. Ziggy を再生成

```bash
docker-compose exec app php artisan ziggy:generate resources/js/ziggy.js
```

⸻

5. フロントを再ビルド

```bash
npm run dev
```

⸻

6. 動作確認
    1. resources/js/ziggy.js 内の "url": "https://xxxxxx.ngrok-free.app" が新しくなっていることを確認
    2. ブラウザでログインして 正しいリダイレクト先に飛ぶか を確認

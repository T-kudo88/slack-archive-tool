# Slack Archive - Production Deployment Guide

VPS向けDocker Compose本番環境の構築・運用ガイド

## 🎯 システム構成

### アーキテクチャ概要
```
Internet → Nginx (SSL/HTTP2) → PHP-FPM → PostgreSQL
                                  ↓           ↓
                               Queue Worker  Redis
                                  ↓
                              Scheduler
```

### コンテナ構成
- **nginx**: リバースプロキシ・SSL終端・静的ファイル配信
- **php**: Laravel アプリケーション (PHP-FPM)
- **queue**: Laravel キューワーカー
- **scheduler**: Laravel スケジューラー
- **postgres**: PostgreSQL データベース
- **redis**: キャッシュ・セッション・キュー
- **postgres_backup**: 自動バックアップサービス

## 📋 システム要件

### ハードウェア要件
| 項目 | 最小要件 | 推奨要件 |
|------|----------|----------|
| CPU | 2 core | 4+ core |
| RAM | 4GB | 8GB+ |
| Storage | 50GB | 100GB+ SSD |
| Network | 100Mbps | 1Gbps |

### ソフトウェア要件
- Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- Docker 20.10+
- Docker Compose 2.0+
- Git

## 🚀 デプロイメント手順

### 1. システム準備
```bash
# システム更新
sudo apt update && sudo apt upgrade -y

# Docker インストール
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Docker Compose インストール
sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### 2. プロジェクト取得
```bash
git clone https://github.com/your-repo/slack-archive.git
cd slack-archive
```

### 3. 環境設定
```bash
# 本番環境ファイルコピー
cp .env.production.example .env.production

# 必要な設定を編集
nano .env.production
```

### 4. 自動デプロイメント
```bash
# デプロイスクリプト実行権限付与
chmod +x deploy.sh

# 本番環境デプロイ
sudo ./deploy.sh
```

## ⚙️ 設定詳細

### 環境変数設定 (.env.production)

#### 必須設定項目
```bash
# アプリケーション設定
APP_NAME="Slack Archive"
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

# データベース設定
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=slack_archive
DB_USERNAME=slack_user
DB_PASSWORD=your-strong-password-here

# Redis設定
REDIS_HOST=redis
REDIS_PASSWORD=your-redis-password-here
REDIS_PORT=6379

# Slack OAuth設定
SLACK_CLIENT_ID=your-slack-client-id
SLACK_CLIENT_SECRET=your-slack-client-secret
SLACK_REDIRECT_URI=https://your-domain.com/auth/slack/callback

# メール設定
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server.com
MAIL_PORT=587
MAIL_USERNAME=your-email@your-domain.com
MAIL_PASSWORD=your-email-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

### パフォーマンス調整

#### RAM使用量目安
| VPS RAM | PostgreSQL | Redis | PHP-FPM | 設定ファイル |
|---------|------------|-------|---------|-------------|
| 2GB | 512MB | 512MB | 1GB | 2GB用設定 |
| 4GB | 1GB | 1GB | 2GB | 4GB用設定 |
| 8GB+ | 2GB | 2GB | 4GB | 8GB+用設定 |

#### 設定ファイル編集箇所
```bash
# PostgreSQL
docker/postgres/production/postgresql.conf
- shared_buffers
- effective_cache_size
- maintenance_work_mem

# Redis
docker/redis/production/redis.conf
- maxmemory

# PHP-FPM
docker/php/production/php-fpm.conf
- pm.max_children
- pm.start_servers
```

## 🛡️ セキュリティ設定

### SSL/TLS証明書
```bash
# Let's Encrypt 自動取得（デプロイ時）
# 手動更新の場合
sudo certbot renew
sudo cp /etc/letsencrypt/live/your-domain.com/*.pem /opt/slack-archive/ssl/
```

### ファイアウォール設定
```bash
# UFW設定（デプロイ時自動実行）
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw deny 5432/tcp  # PostgreSQL
sudo ufw deny 6379/tcp  # Redis
```

### セキュリティヘッダー
Nginx設定により以下を自動適用：
- HSTS (Strict-Transport-Security)
- CSP (Content-Security-Policy)
- X-Content-Type-Options
- X-Frame-Options
- X-XSS-Protection

## 📊 監視・メンテナンス

### 管理コマンド
```bash
# サービス状態確認
slack-archive status

# ログ確認
slack-archive logs
slack-archive logs nginx  # 特定サービス

# サービス制御
slack-archive start
slack-archive stop
slack-archive restart

# アプリケーション更新
slack-archive update

# 手動バックアップ
slack-archive backup

# システムクリーンアップ
slack-archive cleanup
```

### ログ確認
```bash
# アプリケーションログ
docker-compose -f docker-compose.production.yml -f docker-security.yml logs -f php

# Nginx アクセスログ
docker-compose -f docker-compose.production.yml -f docker-security.yml exec nginx tail -f /var/log/nginx/access.log

# PostgreSQL ログ
docker-compose -f docker-compose.production.yml -f docker-security.yml logs -f postgres
```

### パフォーマンス監視
```bash
# リソース使用量
docker stats

# データベース統計
docker-compose -f docker-compose.production.yml -f docker-security.yml exec postgres psql -U slack_user -d slack_archive -c "SELECT * FROM pg_stat_activity;"

# Redis統計
docker-compose -f docker-compose.production.yml -f docker-security.yml exec redis redis-cli info stats
```

## 🔄 バックアップ・復旧

### 自動バックアップ
- 6時間毎に自動実行
- 30日間保持
- `/opt/slack-archive/backups` に保存

### 手動バックアップ
```bash
# データベースバックアップ
slack-archive backup

# ファイルバックアップ
sudo tar -czf /opt/backup/slack-archive-files-$(date +%Y%m%d).tar.gz \
  /opt/slack-archive/storage \
  /opt/slack-archive/.env.production
```

### 復旧手順
```bash
# データベース復旧
gunzip -c /opt/slack-archive/backups/slack_archive_YYYYMMDD_HHMMSS.sql.gz | \
docker-compose -f docker-compose.production.yml -f docker-security.yml exec -T postgres \
psql -U slack_user -d slack_archive

# ファイル復旧
sudo tar -xzf backup-file.tar.gz -C /
```

## 🎛️ 運用設定

### SSL証明書自動更新
```bash
# Crontab設定（デプロイ時自動）
0 3 * * * certbot renew --quiet && cp /etc/letsencrypt/live/your-domain.com/*.pem /opt/slack-archive/ssl/ && docker-compose -f /opt/slack-archive/docker-compose.production.yml restart nginx
```

### ログローテーション
```bash
# logrotate設定（デプロイ時自動）
/opt/slack-archive/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
}
```

### システム監視
```bash
# 5分毎監視（デプロイ時自動）
*/5 * * * * /opt/slack-archive/monitor.sh
```

## 🚨 トラブルシューティング

### よくある問題

#### 1. コンテナが起動しない
```bash
# ログ確認
slack-archive logs

# 個別サービス確認
docker-compose -f docker-compose.production.yml -f docker-security.yml ps
```

#### 2. データベース接続エラー
```bash
# PostgreSQL状態確認
docker-compose -f docker-compose.production.yml -f docker-security.yml exec postgres pg_isready

# 接続テスト
docker-compose -f docker-compose.production.yml -f docker-security.yml exec php php artisan tinker
```

#### 3. SSL証明書エラー
```bash
# 証明書確認
sudo certbot certificates

# 手動更新
sudo certbot renew
```

#### 4. パフォーマンス問題
```bash
# リソース使用量確認
docker stats

# スローログ確認
docker-compose -f docker-compose.production.yml -f docker-security.yml exec postgres cat /var/lib/postgresql/data/log/postgresql-*.log | grep "slow"
```

## 📈 スケーリング

### 垂直スケーリング
1. VPSのスペック向上
2. 設定ファイルの調整
3. サービス再起動

### 水平スケーリング準備
- Redis Cluster設定
- PostgreSQL レプリケーション設定
- ロードバランサー導入

## 🔧 メンテナンス

### 定期メンテナンス項目
- [ ] SSL証明書更新確認（月次）
- [ ] バックアップ確認（週次）
- [ ] システム更新（月次）
- [ ] ログサイズ確認（週次）
- [ ] パフォーマンス確認（月次）

### 更新手順
```bash
# アプリケーション更新
cd /opt/slack-archive
git pull
slack-archive update

# システム更新
sudo apt update && sudo apt upgrade -y
sudo reboot  # 必要に応じて
```

## 📞 サポート

### ログ収集
問題報告時は以下の情報を提供してください：

```bash
# システム情報
uname -a
docker --version
docker-compose --version

# サービス状態
slack-archive status

# ログ出力
slack-archive logs > /tmp/slack-archive-logs.txt
```

---

## 📝 設定チェックリスト

### デプロイ前確認
- [ ] ドメイン設定完了
- [ ] DNS設定完了
- [ ] 環境変数設定完了
- [ ] Slack OAuth設定完了
- [ ] メール設定確認

### デプロイ後確認
- [ ] SSL証明書正常
- [ ] アプリケーション動作確認
- [ ] Slack OAuth動作確認
- [ ] バックアップ動作確認
- [ ] 監視設定確認
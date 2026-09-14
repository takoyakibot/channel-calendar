# Channel Calendar

YouTube チャンネルの配信予定・配信履歴を、週ボード／月カレンダーで一覧表示する Laravel 10 アプリです。
管理画面でチャンネル（`@handle` / URL / `UC…`）とグループを登録し、`streams:fetch` が YouTube Data API v3 から配信を取り込みます。

- 公開: `/`（全チャンネル）、`/{group}`、`/{group}/{child}`（グループ別）
- 管理: `/admin/channels` `/admin/groups` `/admin/settings`（要ログイン）
- 取得: `php artisan streams:fetch`（スケジューラで30分ごと。管理画面の「今すぐ取得」でも実行可）

## ローカル開発

```bash
composer install
npm install
cp .env.example .env          # DB_CONNECTION=sqlite ならファイル DB で動きます
php artisan key:generate
php artisan migrate
php artisan db:seed           # admin@example.com / password（local のみ）
npm run build                 # または npm run dev
php artisan serve
php artisan test
```

YouTube API キーは `/admin/settings` から登録するか、`.env` の `YOUTUBE_API_KEY` に書きます（管理画面の値が優先）。

## 本番デプロイ（CoreServer / calendar.alpacasandbag.jp）

サーバーに composer / node は無いため、ローカルでビルドして rsync で送ります（ycs と同じ方式）。

### 初回のみ

1. DirectAdmin で MySQL のデータベースとユーザーを作成する。
2. サーバーの `public_html/.env` を作成する（rsync では送られない）:

   ```env
   APP_NAME="Channel Calendar"
   APP_ENV=production
   APP_KEY=                      # 空のままで可。deploy.sh が生成します
   APP_DEBUG=false
   APP_URL=https://calendar.alpacasandbag.jp
   LOG_CHANNEL=daily
   LOG_LEVEL=warning

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_SOCKET=/var/lib/mysql/mysql.sock
   DB_DATABASE=（作成したDB名）
   DB_USERNAME=（作成したユーザー）
   DB_PASSWORD=（パスワード）

   CACHE_DRIVER=file
   SESSION_DRIVER=file
   QUEUE_CONNECTION=sync

   YOUTUBE_API_KEY=              # 管理画面から登録するなら空で可
   STREAMS_BACKFILL_DAYS=14
   ADMIN_EMAIL=（管理者メール）
   ADMIN_PASSWORD=（管理者パスワード）  # 必須。db:seed 後に削除して良い
   ```

3. `./deploy.sh` を実行する（テスト → ビルド → rsync → migrate）。
4. 管理者ユーザーを作成する:

   ```bash
   ssh -i ~/.ssh/ycs_rsa alpacasandbag@v2007.coreserver.jp \
     'cd domains/calendar.alpacasandbag.jp/public_html && /usr/local/php81/bin/php artisan db:seed --class=AdminUserSeeder --force'
   ```

5. DirectAdmin の cron に以下を追加する（毎分。Laravel のスケジューラが30分ごとに `streams:fetch` を起動します）:

   ```
   * * * * * cd /home/alpacasandbag/domains/calendar.alpacasandbag.jp/public_html && /usr/local/php81/bin/php artisan schedule:run >> storage/logs/cron.log 2>&1
   ```

6. `https://calendar.alpacasandbag.jp/login` でログインし、`/admin/settings` に YouTube API キーを登録して「接続テスト」。

### 2回目以降

```bash
./deploy.sh
```

`.exclude-list` に載っているもの（`.env`、`storage/logs`、`storage/framework`、`tests` など）は転送されません。

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

## 本番デプロイ（CoreServer 系の共有ホスティング）

サーバーに composer / node は無いため、ローカルでビルドして rsync で送ります（ycs と同じ方式）。

### 初回のみ

1. `cp deploy.env.example deploy.env` して、サーバーの SSH 接続先・鍵・配置パス・公開URLを記入する（`deploy.env` は git 管理外）。
2. DirectAdmin で MySQL のデータベースとユーザーを作成する。
3. サーバーの `public_html/.env` を作成する（rsync では送られない）:

   ```env
   APP_NAME="Channel Calendar"
   APP_ENV=production
   APP_KEY=                      # 空のままで可。deploy.sh が生成します
   APP_DEBUG=false
   APP_URL=https://your-domain.example.com
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

4. `./deploy.sh` を実行する（テスト → ビルド → rsync → migrate。APP_KEY が空なら生成される）。
5. 管理者ユーザーを作成する（`deploy.env` の値に読み替える）:

   ```bash
   ssh -i "$SSH_KEY" "$SERVER" "cd $REMOTE_PATH && php artisan db:seed --class=AdminUserSeeder --force"
   ```

   その後 `.env` の `ADMIN_PASSWORD` は空にしてよい。

6. サーバーの cron に以下を追加する（毎分。Laravel のスケジューラが30分ごとに `streams:fetch` を起動します）:

   ```
   * * * * * cd /path/to/public_html && php artisan schedule:run >> storage/logs/cron.log 2>&1
   ```

7. `/login` でログインし、`/admin/settings` に YouTube API キーを登録して「接続テスト」。
8. サブドメインの SSL 証明書（Let's Encrypt）をホスティングの管理画面で発行する。

### 2回目以降

```bash
./deploy.sh
```

`.exclude-list` に載っているもの（`.env`、`storage/logs`、`storage/framework`、`tests` など）は転送されません。

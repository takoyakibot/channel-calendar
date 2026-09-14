#!/bin/bash
#
# 本番デプロイスクリプト（CoreServer 向け、rsync + SSH）
#
# 使い方: cp deploy.env.example deploy.env && vi deploy.env && ./deploy.sh
#
# 処理内容:
#   1. ローカルでビルド（npm run build, composer --no-dev）
#   2. rsync で本番サーバーに転送（.exclude-list の対象は送らない）
#   3. サーバー側で artisan キャッシュクリア → マイグレーション
#
# 前提:
#   - サーバーに composer / node は無いので、ビルド済みの vendor と public/build を丸ごと送る
#   - サーバーの .env は手動で管理する（初回は .env.example を元に作成、rsync では触らない）
#   - 本番CLIのPHPには psr 拡張が入っており composer の psr/log と衝突して artisan が
#     起動できないため、psr の行だけ除いた ini を PHP_INI_SCAN_DIR で読ませる（ycs と同じ対処）
#

set -e
cd "$(dirname "$0")"

# 接続情報は git 管理外の deploy.env に置く（deploy.env.example を参照）
if [ ! -f deploy.env ]; then
  echo "❌ deploy.env がありません。deploy.env.example をコピーして接続情報を記入してください。"
  exit 1
fi
# shellcheck disable=SC1091
. ./deploy.env
: "${SERVER:?deploy.env に SERVER を設定してください}"
: "${SSH_KEY:?deploy.env に SSH_KEY を設定してください}"
: "${REMOTE_PATH:?deploy.env に REMOTE_PATH を設定してください}"
SITE_URL="${SITE_URL:-}"

DEV_DEPS_STRIPPED=0

on_exit() {
  local status=$?
  if [ "$status" -eq 0 ]; then
    return
  fi
  echo ""
  echo "❌ デプロイが途中で失敗しました（終了コード: ${status}）"
  echo "   本番への反映は完了していません。上のログを確認して再実行してください。"
  if [ "$DEV_DEPS_STRIPPED" -eq 1 ]; then
    echo "▶ ローカルの開発用依存を復元します"
    composer install || echo "⚠ 復元に失敗しました。手動で 'composer install' を実行してください"
  fi
}
trap on_exit EXIT

echo "▶ 0. テスト"
php artisan test

echo "▶ 1. ローカルビルド"
npm run build
composer install --no-dev --optimize-autoloader
DEV_DEPS_STRIPPED=1

echo "▶ 1.5. 環境依存ファイルの検知・削除"
for cache_file in bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php; do
  if [ -f "$cache_file" ]; then
    echo "⚠ $cache_file が存在します。ローカル設定が本番に送られるため削除して続行します。"
    rm "$cache_file"
  fi
done
if [ -f "public/hot" ]; then
  echo "⚠ public/hot が存在します（Vite devサーバー用）。削除します。"
  rm "public/hot"
fi

echo "▶ 2. rsync で本番転送"
rsync -avz --exclude-from=".exclude-list" --delete \
  -e "ssh -i $SSH_KEY -p 22" \
  . "$SERVER:$REMOTE_PATH/"

echo "▶ 3. サーバー側でキャッシュクリアとマイグレーション"
ssh -i "$SSH_KEY" -p 22 "$SERVER" "bash -s" <<REMOTE_SCRIPT
set -e
cd "$REMOTE_PATH"

ini_src=\$(php --ini 2>/dev/null | grep -oE '/[^ ,]*alt_php\.ini' | head -1)
if [ -n "\$ini_src" ] && [ -f "\$ini_src" ]; then
  mkdir -p "\$HOME/php-ini-nopsr"
  grep -v '^extension=psr.so' "\$ini_src" > "\$HOME/php-ini-nopsr/alt_php.ini"
  export PHP_INI_SCAN_DIR="\$HOME/php-ini-nopsr"
fi

if [ ! -f .env ]; then
  echo "❌ サーバーに .env がありません。README のデプロイ手順に従って作成してください。"
  exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  echo "--- APP_KEY が未設定のため生成します"
  php artisan key:generate --force
fi

# storage/framework は転送対象外なので、初回は自分で作る
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R ug+rwX storage bootstrap/cache

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "--- 未適用のマイグレーション"
php artisan migrate:status | grep -i pending || echo "（なし）"

php artisan migrate --force
REMOTE_SCRIPT

echo "▶ 4. ローカルの開発用依存を復元"
composer install
DEV_DEPS_STRIPPED=0

echo "✅ デプロイ完了${SITE_URL:+: $SITE_URL}"

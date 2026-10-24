# 月表示の配信結果を画像にして共有する Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 月表示に「📷 配信結果を共有」ボタンを付け、A（見たまま）/ B（まとめ＋カレンダー）/ D（ジャンル別モザイク）の画像をブラウザで作って共有できるようにする（#83）。

**Architecture:** 集計・ジャンル分け・敷き詰めは DOM に依存しない純粋な ES モジュール（`resources/js/share-image/`）にして `node --test` でテストする。D は Canvas 2D で直接描き、A・B は画面外に描いた固定幅の FullCalendar を html2canvas（CDN、共有画面を開いたときだけ読み込む）で写す。カレンダーのページ（Blade 内のスクリプト）は `window.ccShare` にフック（今月の配信・見出し・共有 URL・月カレンダーの描画）を出し、共有モジュールはそれだけを使う。

**Tech Stack:** Laravel 10 / Blade、FullCalendar 6.1.11（CDN）、Vite 5（新しいエントリーを追加）、html2canvas 1.4.1（CDN）、Node 25 の `node --test`（追加の npm 依存なし）

**Spec:** `docs/superpowers/specs/2026-10-24-share-month-image-design.md`

## Global Constraints

- 画像はブラウザ上で作る。サーバー側で画像を作らない、サムネをサーバー経由で取らない
- サムネは切らない（16:9 のサムネは 16:9 のまま）。ショートだけは真ん中を 9:16 に切り出し、3 本で 1 マス
- D は 16:9・1600×900px。数字のまとめは入れない。ジャンル名はサムネに重ねず、下端の凡例に書く
- ゲームをまとめる初期値 N = 2、カテゴリをまとめる初期値 M = 1（本数。`‹ N ›` で 0 以上に変えられる）
- 優先度の低いカテゴリ：`['メン限', 'コラボ', '参加型', '同時視聴']`（定数 1 か所で変えられるように）
- B の 3 つ目の数字は「最長配信」。差し替えやすいように、表示する数字の一覧を 1 か所の配列で持つ
- 画像は明るい背景で作る（ダークテーマ表示中でも）
- 外部スクリプトは CDN から、バージョン固定（`html2canvas@1.4.1`）
- 文言は日本語。ボタンは「📷 配信結果を共有」「画像を作って共有」「キャンセル」

## Review Focus

1. **その月に配信が 1 本もない** → D・B は「この月の配信はありません」と出し、「画像を作って共有」は押せない（Task 6 のテストで固定）
2. **サムネが読み込めない（削除済み・Twitch の期限切れ・CORS なし）** → そのタイルはチャンネル色の無地パネルで描き、画像作成は失敗しない（Task 5 の確認手順で固定）
3. **月表示の前後の月の日（グリッドの端）に出ている配信** → 画像にも集計にも入れない（Task 6 のテストで固定）
4. **配信がとても多い月（150 本）・ジャンルがとても多い月（20 ジャンル）** → 16:9 からはみ出さない。凡例は 2 行まで、入りきらない分は「ほか n ジャンル」（Task 3・Task 5 で固定）
5. **Web Share が使えない／共有シートをキャンセル** → 使えないときは保存＋投稿画面。キャンセル（`AbortError`）は何もしない（Task 6 で固定）

---

## File Structure

| ファイル | 役割 |
|---|---|
| `app/Http/Controllers/Api/StreamController.php` | `/api/streams` の `extendedProps` に `actual_start_at` を足す |
| `resources/js/share-image/items.js` | カレンダーのイベント → 画像用の配信（`ShareItem`）。何を入れるかの振り分け |
| `resources/js/share-image/genres.js` | D のジャンル分けとまとめ |
| `resources/js/share-image/layout.js` | D の敷き詰め（ブロックの形と位置） |
| `resources/js/share-image/stats.js` | B の数字 |
| `resources/js/share-image/draw.js` | Canvas への描画（D 全体、B の数字の帯、凡例、画像の読み込み） |
| `resources/js/share-image/index.js` | 共有画面（モーダル）、パターン切り替え、A・B の写し取り、共有の流れ。`window.ccShareImage.open()` を出す |
| `resources/views/calendar/index.blade.php` | ボタン・モーダルの枠・`window.ccShare` フック・`@vite` の読み込み・`.cc-light` |
| `vite.config.js` / `package.json` | エントリー追加、`npm run test:js` |
| `tests/js/*.test.js` | 純粋モジュールのテスト |

`ShareItem`（Task 2 以降すべてが使う形）：

```js
/** @typedef {{
 *   id: string, title: string, start: Date,
 *   kind: 'stream'|'upload'|'short'|'guest',
 *   status: 'upcoming'|'live'|'completed'|'posted',
 *   thumb: string|null, color: string, channelName: string,
 *   tags: {name: string, kind: 'game'|'category'}[],
 *   actualStart: Date|null, actualEnd: Date|null,
 * }} ShareItem */
```

---

### Task 1: `/api/streams` に実際の開始時刻を出す

**Files:**
- Modify: `app/Http/Controllers/Api/StreamController.php`（`index()` の `extendedProps`）
- Test: `tests/Feature/Api/StreamApiTest.php`

**Interfaces:**
- Produces: `/api/streams` の各イベントの `extendedProps.actual_start_at`（ISO 8601 文字列 or `null`）

- [ ] **Step 1: 失敗するテストを書く**

```php
public function test_streams_endpoint_includes_the_actual_start(): void
{
    $channel = Channel::factory()->create();
    Stream::factory()->create([
        'channel_id' => $channel->id,
        'scheduled_at' => '2026-10-17 12:00:00',
        'actual_start_at' => '2026-10-17 12:01:34',
        'actual_end_at' => '2026-10-17 13:47:17',
        'status' => 'completed',
    ]);
    Stream::factory()->create(['channel_id' => $channel->id, 'scheduled_at' => '2026-10-18 12:00:00', 'actual_start_at' => null, 'status' => 'upcoming']);

    $events = $this->getJson('/api/streams?start=2026-10-01&end=2026-10-31')->assertOk()->json();

    $this->assertSame('2026-10-17T12:01:34+00:00', $events[0]['extendedProps']['actual_start_at']);
    $this->assertNull($events[1]['extendedProps']['actual_start_at']);
}
```

- [ ] **Step 2: 失敗を確認** — `APP_KEY=base64:$(openssl rand -base64 32) php artisan test --filter=test_streams_endpoint_includes_the_actual_start` → FAIL（キーがない）
- [ ] **Step 3: 実装** — `'actual_start_at' => $stream->actual_start_at?->toIso8601String(),` を `extendedProps` に追加
- [ ] **Step 4: 通ることを確認** — 同じコマンドで PASS、続けて全体 `APP_KEY=… php artisan test` が全件 PASS
- [ ] **Step 5: Commit** — `git commit -m "feat: expose actual_start_at on /api/streams (for #83)"`

---

### Task 2: 画像に入れる配信の振り分けと `npm run test:js`

**Files:**
- Create: `resources/js/share-image/items.js`
- Create: `tests/js/items.test.js`
- Modify: `package.json`（`"test:js": "node --test tests/js/"`）

**Interfaces:**
- Produces: `toShareItems(events: object[], monthStart: Date, monthEnd: Date): ShareItem[]`
  - `events` は FullCalendar の `EventApi` か、同じ形のプレーンなオブジェクト（`{id, title, start, backgroundColor, extendedProps}`）
  - `monthStart` 以上 `monthEnd` 未満に始まるものだけ。並びは開始時刻順

- [ ] **Step 1: 失敗するテストを書く**（`node:test` と `node:assert/strict`）

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toShareItems } from '../../resources/js/share-image/items.js';

const ev = (id, start, props) => ({ id, title: id, start: new Date(start), backgroundColor: '#feb690',
  extendedProps: { channel_name: 'うる', thumbnail_url: 'https://i.ytimg.com/vi/x/default.jpg', tags: [], ...props } });
const OCT = [new Date('2026-10-01T00:00:00+09:00'), new Date('2026-11-01T00:00:00+09:00')];

test('streams, videos, shorts and guest appearances go in; clips and manual entries do not', () => {
  const items = toShareItems([
    ev('s', '2026-10-05T20:00:00+09:00', { type: 'stream', status: 'completed' }),
    ev('u', '2026-10-06T20:00:00+09:00', { type: 'upload', status: 'completed' }),
    ev('sh', '2026-10-07T20:00:00+09:00', { type: 'short', status: 'completed' }),
    ev('g', '2026-10-08T20:00:00+09:00', { type: 'guest', status: 'posted' }),
    ev('c', '2026-10-09T20:00:00+09:00', { type: 'clip', status: 'posted' }),
    ev('m', '2026-10-10T20:00:00+09:00', { status: 'manual' }),
  ], ...OCT);
  assert.deepEqual(items.map(i => [i.id, i.kind]), [['s', 'stream'], ['u', 'upload'], ['sh', 'short'], ['g', 'guest']]);
});

test('days of the previous/next month shown at the grid edges are left out', () => {
  const items = toShareItems([
    ev('sep', '2026-09-30T23:30:00+09:00', { type: 'stream', status: 'completed' }),
    ev('nov', '2026-11-01T00:10:00+09:00', { type: 'stream', status: 'upcoming' }),
    ev('oct', '2026-10-31T23:59:00+09:00', { type: 'stream', status: 'upcoming' }),
  ], ...OCT);
  assert.deepEqual(items.map(i => i.id), ['oct']);
});

test('upcoming and live streams are kept; thumbnails are upgraded to mqdefault; actual times are Dates', () => {
  const [i] = toShareItems([ev('s', '2026-10-05T20:00:00+09:00', {
    type: 'stream', status: 'live', actual_start_at: '2026-10-05T11:01:00+00:00' })], ...OCT);
  assert.equal(i.status, 'live');
  assert.equal(i.thumb, 'https://i.ytimg.com/vi/x/mqdefault.jpg');
  assert.equal(i.actualStart.toISOString(), '2026-10-05T11:01:00.000Z');
  assert.equal(i.actualEnd, null);
});
```

- [ ] **Step 2: 失敗を確認** — `npm run test:js` → FAIL（モジュールがない）
- [ ] **Step 3: 実装** — `kind` は `extendedProps.type`（`status === 'posted'` のときも `type` が `guest`/`clip`）。`actualEnd` は `type === 'stream' && status === 'completed'` のときだけイベントの `end`。サムネの差し替え規則は Blade の `thumbUrl()` と同じ（`/default(_live)?.jpg` → `/mqdefault$1.jpg`、i.ytimg.com のときだけ）
- [ ] **Step 4: 通ることを確認** — `npm run test:js` → PASS
- [ ] **Step 5: Commit** — `git commit -m "feat(share-image): pick the month's items for the image"`

---

### Task 3: D のジャンル分けと敷き詰め

**Files:**
- Create: `resources/js/share-image/genres.js`, `resources/js/share-image/layout.js`
- Create: `tests/js/genres.test.js`, `tests/js/layout.test.js`

**Interfaces:**
- Consumes: `ShareItem`（Task 2）
- Produces:
  - `LOW_PRIORITY_CATEGORIES: string[]`（Global Constraints の 4 つ）
  - `groupByGenre(items: ShareItem[], opts: {gameMax: number, categoryMax: number}): {blocks: Genre[], foldedGames: string[], foldedCategories: string[]}`
    - `Genre = {name: string, items: ShareItem[], cells: number, isOther: boolean}`。`cells` はショートなら `ceil(n/3)`、それ以外は `n`
    - `blocks` の並び：`cells` の多い順、同数は名前順（`localeCompare(…, 'ja')`）、`isOther`（「その他」）は必ず最後
  - `packBlocks(blocks: {cells: number}[], area: {w: number, h: number}): {cols: number, rows: number, tileW: number, tileH: number, placed: {index: number, x: number, y: number, c: number, r: number}[]}`（x, y, c, r はタイル単位、tileW:tileH = 16:9）

- [ ] **Step 1: ジャンル分けの失敗するテストを書く**（`tests/js/genres.test.js`）。`item(tags, kind = 'stream')` のような小さなヘルパーで `ShareItem` を作る

```js
test('a game tag wins over categories; low-priority categories lose to any other tag', () => {
  const { blocks } = groupByGenre([
    item([cat('雑談'), cat('メン限')]), item([cat('メン限'), game('原神')]), item([cat('コラボ'), cat('朝活')]),
  ], { gameMax: 0, categoryMax: 0 });
  assert.deepEqual(new Set(names(blocks)), new Set(['原神', '朝活', '雑談']));
});

test('a stream tagged only with a low-priority category gets that genre, not その他', () => {
  assert.deepEqual(names(groupByGenre([item([cat('メン限')])], { gameMax: 0, categoryMax: 0 }).blocks), ['メン限']);
});

test('untagged items are その他, always last; shorts are ショート with 3 per cell', () => {
  const { blocks } = groupByGenre([item([]), item([], 'short'), item([], 'short'), item([], 'short'), item([], 'short'), item([cat('雑談')])],
    { gameMax: 0, categoryMax: 0 });
  assert.deepEqual(blocks.map(b => [b.name, b.cells]), [['ショート', 2], ['雑談', 1], ['その他', 1]]);
});

test('two or more small games fold into その他ゲーム while a big game keeps its block', () => {
  const r = groupByGenre([...n(3, [game('原神')]), item([game('A')]), item([game('B')])], { gameMax: 2, categoryMax: 0 });
  assert.deepEqual(names(r.blocks), ['原神', 'その他ゲーム']);
  assert.deepEqual(r.foldedGames, ['A', 'B']);
});

test('when every game folds the block is just ゲーム', () => {
  assert.deepEqual(names(groupByGenre([item([game('A')]), item([game('B')])], { gameMax: 2, categoryMax: 0 }).blocks), ['ゲーム']);
});

test('a lone small game keeps its own name', () => {
  assert.deepEqual(names(groupByGenre([...n(3, [game('原神')]), item([game('A')])], { gameMax: 2, categoryMax: 0 }).blocks), ['原神', 'A']);
});

test('small categories join その他; a lone one joins only when untagged items exist', () => {
  assert.deepEqual(names(groupByGenre([item([cat('朝活')]), item([cat('作業')]), ...n(2, [cat('雑談')])], { gameMax: 0, categoryMax: 1 }).blocks), ['雑談', 'その他']);
  assert.deepEqual(names(groupByGenre([item([cat('朝活')]), ...n(2, [cat('雑談')])], { gameMax: 0, categoryMax: 1 }).blocks), ['雑談', '朝活']);
  assert.deepEqual(names(groupByGenre([item([cat('朝活')]), item([]), ...n(2, [cat('雑談')])], { gameMax: 0, categoryMax: 1 }).blocks), ['雑談', 'その他']);
});
```

- [ ] **Step 2: 失敗を確認** — `npm run test:js` → FAIL
- [ ] **Step 3: `groupByGenre` を実装**（`genres.js`）。規則は spec の「ジャンルの決め方」。ゲームのまとめを先に、カテゴリのまとめを後に行う。「ショート」「その他」「その他ゲーム」「ゲーム」はまとめの対象にしない
- [ ] **Step 4: 通ることを確認** — `npm run test:js` → PASS
- [ ] **Step 5: 敷き詰めの失敗するテストを書く**（`tests/js/layout.test.js`）。共通の検査関数 `assertValid(blocks, res)`：各ブロックが `x + c <= cols`、`y + r <= rows`、`c * r >= cells`、`c * r - cells < Math.min(c, r)`、ブロック同士が重ならない、`tileW / tileH === 16 / 9`、`cols * tileW <= area.w`、`rows * tileH <= area.h`

```js
test('the October example (4,3,3,1,1,2 cells) packs validly into 1600x820', () => {
  const blocks = [4, 3, 3, 2, 1, 1].map(cells => ({ cells }));
  assertValid(blocks, packBlocks(blocks, { w: 1600, h: 820 }));
});
test('150 single-genre-ish items and 20 genres still fit', () => {
  const blocks = Array.from({ length: 20 }, (_, i) => ({ cells: i < 5 ? 20 : 3 }));
  const res = packBlocks(blocks, { w: 1600, h: 820 });
  assertValid(blocks, res);
  assert.ok(res.tileW > 40);
});
test('no blocks gives an empty board', () => {
  assert.deepEqual(packBlocks([], { w: 1600, h: 820 }).placed, []);
});
```

- [ ] **Step 6: 失敗を確認** — `npm run test:js` → FAIL
- [ ] **Step 7: `packBlocks` を実装**（`layout.js`）。モックアップで確かめた貪欲法をそのまま使う：

```js
// For each board width W = 1..totalCells: place blocks in the given order. For a block of n cells try
// every shape c x r with c <= min(n, W), r = ceil(n / c), c*r - n < min(c, r); for each shape take the
// top-most then left-most free spot; keep the shape with the lowest score
//   score = max(currentHeight, y + r) * 100 + |c - r| + y
// Board height H = max over blocks of y + r. Tile width = min(area.w / W, (area.h / H) * 16 / 9).
// Return the W whose tile width is largest (ties: smaller W).
```

- [ ] **Step 8: 通ることを確認** — `npm run test:js` → PASS
- [ ] **Step 9: Commit** — `git commit -m "feat(share-image): genre grouping and treemap-style packing"`

---

### Task 4: B の数字

**Files:**
- Create: `resources/js/share-image/stats.js`, `tests/js/stats.test.js`

**Interfaces:**
- Consumes: `ShareItem`
- Produces:
  - `monthStats(items: ShareItem[]): {streams: number, totalSeconds: number, longestSeconds: number}`
  - `STAT_TILES: {key: 'streams'|'totalSeconds'|'longestSeconds', label: string, format: (v: number) => string}[]` — 順番と表示はこの配列だけで決まる（「配信数」「配信時間」「最長配信」）
  - `formatDuration(seconds: number): string` — `"38時間12分"` / `"52分"` / 0 は `"0分"`

- [ ] **Step 1: 失敗するテストを書く**

```js
test('only live streams count; time uses actual start to end of finished ones', () => {
  const s = monthStats([
    stream('completed', '2026-10-17T12:01:34Z', '2026-10-17T13:47:17Z'),
    stream('completed', '2026-10-10T10:00:00Z', '2026-10-10T16:12:00Z'),
    stream('upcoming', null, null),
    stream('live', '2026-10-24T10:00:00Z', null),
    { ...stream('completed', '2026-10-01T10:00:00Z', '2026-10-01T11:00:00Z'), kind: 'upload' },
  ]);
  assert.deepEqual(s, { streams: 4, totalSeconds: 6343 + 22320, longestSeconds: 22320 });
});
test('formatDuration', () => {
  assert.equal(formatDuration(22320), '6時間12分');
  assert.equal(formatDuration(3120), '52分');
  assert.equal(formatDuration(0), '0分');
});
test('STAT_TILES order is 配信数, 配信時間, 最長配信', () => {
  assert.deepEqual(STAT_TILES.map(t => t.label), ['配信数', '配信時間', '最長配信']);
});
```

- [ ] **Step 2: 失敗を確認** — `npm run test:js` → FAIL
- [ ] **Step 3: 実装**（`actualStart` が無い終了済みの配信は時間に数えない）
- [ ] **Step 4: 通ることを確認** — `npm run test:js` → PASS
- [ ] **Step 5: Commit** — `git commit -m "feat(share-image): month stats for pattern B"`

---

### Task 5: D を Canvas に描く

**Files:**
- Create: `resources/js/share-image/draw.js`
- Create（コミットしない確認用）: `.superpowers/harness/mosaic.html`（`/.superpowers/` は gitignore 済み）

**Interfaces:**
- Consumes: `groupByGenre`・`packBlocks`（Task 3）、`ShareItem`
- Produces:
  - `loadImages(urls: string[]): Promise<Map<string, HTMLImageElement|null>>` — `crossOrigin = 'anonymous'`、失敗は `null`（reject しない）
  - `drawMosaic(canvas: HTMLCanvasElement, items: ShareItem[], opts: {title: string, gameMax: number, categoryMax: number, images: Map}): void` — canvas を 1600×900 にして描く
  - `drawLegend(ctx, entries: {name: string, count: number, color: string}[], box: {x, y, w, h}, title: string): void`（凡例の帯。D 用）
  - `GENRE_PALETTE: string[]`、`OTHER_COLOR = '#c8c2b8'`、`BG_COLOR = '#f4f1ec'`

描き方（モックアップ `mosaic-thresholds.html` と同じ見た目）：
- 背景 `BG_COLOR`。下端 80px を凡例の帯、残りを盤面の領域にして `packBlocks` に渡す。盤面は領域の中央
- ブロックは角丸 3px でジャンル色に塗る。タイルはブロックの中に左上から詰め、各タイルは内側に `max(2, tileW * 0.035)` の余白を空けて 16:9 のまま描く（`drawImage` の縮小のみ、切り抜きなし）
- ショートのマス：元画像の中央 `srcW = srcH * 9 / 16` を切り出して、マスの中に 3 本並べる（モックアップの式と同じ）
- 画像が `null` のタイル：チャンネル色を 30% の濃さにした無地の角丸パネル
- 凡例：左に見出し（太字）、続けて「色の四角・名前・本数」を多い順（「その他」は最後）、右端に `calendar.alpacasandbag.jp`。2 行まで。入りきらない分は「ほか n ジャンル」
- 配信が 0 件なら、盤面に「この月の配信はありません」と中央に書く

- [ ] **Step 1: 確認用ページを作る** — `.superpowers/harness/mosaic.html`：`draw.js` を `<script type="module">` で読み、`/api/streams?start=2026-10-01&end=2026-10-31` の本番のレスポンスを保存した JSON（同じフォルダの `oct.json`、`curl` で取得）から `toShareItems` → `drawMosaic` を呼び、`‹ N ›` ボタン 2 組で `gameMax`/`categoryMax` を変えて描き直す
- [ ] **Step 2: `draw.js` を実装**
- [ ] **Step 3: 見た目を確認** — ワークツリーの直下で `python3 -m http.server 8765` を起動し、`http://localhost:8765/.superpowers/harness/mosaic.html` を開く。確認すること：サムネが切れていない／ショートが縦長 3 本／凡例が重ならない／`N`・`M` を変えるとまとめが変わる／`canvas.toBlob` が例外なく PNG を返す（ページに「PNG を保存」ボタンを置いて確かめる）／`oct.json` の 1 件のサムネ URL を存在しない ID に書き換えると、その 1 枚だけ無地パネルになる
- [ ] **Step 4: Commit** — `git add resources/js/share-image/draw.js && git commit -m "feat(share-image): draw the genre mosaic on canvas"`

---

### Task 6: 共有画面と共有の流れ（D で通しで動かす）

**Files:**
- Create: `resources/js/share-image/index.js`, `tests/js/share-flow.test.js`
- Modify: `resources/views/calendar/index.blade.php`、`vite.config.js`

**Interfaces:**
- Consumes: Task 2〜5 のすべて
- Produces:
  - Blade 側のフック `window.ccShare = { monthItems(): ShareItem[], title(): string, stateUrl(): string, renderMonth(el: HTMLElement): Promise<void> }`（`renderMonth` は Task 7 で使う。ここでは定義だけして `Promise.resolve()` でよい）
  - `window.ccShareImage.open(): void`
  - `shareOrDownload(blob: Blob, filename: string, text: string, url: string, deps = {navigator, open, download}): Promise<'shared'|'cancelled'|'downloaded'>`（`index.js` から export。テスト用に依存を差し替えられる）
  - `shareText(title: string, monthLabel: string, url: string): string` — `"{title} {monthLabel}の配信 {url}"`

Blade の変更：
- ヘッダーの共有ボタン群の近くに `<button type="button" class="video-btn" id="share-image-btn" hidden>📷 配信結果を共有</button>`。`setView()` で月表示のときだけ `hidden = false`
- モーダルの枠は既存の `.modal-overlay` / `.modal` を使い、`max-width` は 44rem（プレビューが見えるように）。中身（タブ・プレビュー用の `<canvas>`・`‹ N ›`・ボタン）は `index.js` が組み立てる
- `window.ccShare.monthItems()`：`calendar.getEvents()` を `toShareItems(…, calendar.view.currentStart, calendar.view.currentEnd)` に通す（フィルターは取得時に `isVisible` で済んでいる）
- `window.ccShare.title()`：spec の「画像の見出し」。グループのページは `$group->name`、トップページは空文字、チャンネル絞り込みで表示中のチャンネルが 1 つだけならそのチャンネル名
- `window.ccShare.stateUrl()`：既存の `stateUrl()`
- `@vite(['resources/js/share-image/index.js'])` を追加し、`vite.config.js` の `input` にも追加

共有画面の動き：
- タブ「A. 見たまま」「B. まとめ＋カレンダー」「D. ジャンル別」。初期は D
- D のときだけ `ゲーム：‹ 2 › 本以下は「その他ゲーム」へ`、`それ以外：‹ 1 › 本以下は「その他」へ`。押すとプレビューを描き直す（`‹` は 0 で止まる）
- 配信が 0 件なら、プレビューに「この月の配信はありません」と出し、「画像を作って共有」を `disabled` にする
- 「画像を作って共有」：ボタンを「作成中…」にして `canvas.toBlob` → `shareOrDownload`。投稿文は `shareText(title, '2026年10月', stateUrl())`
- `shareOrDownload`：`navigator.canShare?.({files: [file]})` が真なら `navigator.share({files, text})`。`AbortError` は `'cancelled'`。それ以外の例外と、`canShare` が偽のときは、PNG を `filename` でダウンロードして `https://x.com/intent/tweet?text=…&url=…` を新しいタブで開き `'downloaded'`

- [ ] **Step 1: 失敗するテストを書く**（`tests/js/share-flow.test.js`。`index.js` は DOM に触れる部分を `open()` の中に閉じ込め、import しただけでは何も起きないようにする）

```js
test('shares the file when the browser can', async () => {
  const calls = [];
  const nav = { canShare: () => true, share: async (d) => calls.push(d) };
  assert.equal(await shareOrDownload(blob, 'channel-calendar-2026-10.png', 'text', 'https://x', { navigator: nav, open: fail, download: fail }), 'shared');
  assert.equal(calls[0].files[0].name, 'channel-calendar-2026-10.png');
});
test('a cancelled share does nothing else', async () => {
  const nav = { canShare: () => true, share: async () => { throw Object.assign(new Error(), { name: 'AbortError' }); } };
  assert.equal(await shareOrDownload(blob, 'f.png', 't', 'u', { navigator: nav, open: fail, download: fail }), 'cancelled');
});
test('falls back to download + X intent when files cannot be shared', async () => {
  const opened = [], saved = [];
  const res = await shareOrDownload(blob, 'channel-calendar-2026-10.png', '天和うる 2026年10月の配信', 'https://c/amawauru?view=month',
    { navigator: {}, open: (u) => opened.push(u), download: (b, name) => saved.push(name) });
  assert.equal(res, 'downloaded');
  assert.deepEqual(saved, ['channel-calendar-2026-10.png']);
  assert.match(opened[0], /^https:\/\/x\.com\/intent\/tweet\?/);
  assert.match(decodeURIComponent(opened[0]), /天和うる 2026年10月の配信/);
});
test('shareText', () => {
  assert.equal(shareText('天和うる', '2026年10月', 'https://u'), '天和うる 2026年10月の配信 https://u');
  assert.equal(shareText('', '2026年10月', 'https://u'), '2026年10月の配信 https://u');
});
```

（`blob` は `new Blob(['x'], {type: 'image/png'})`、`fail` は呼ばれたら `assert.fail` する関数。ファイル名は `channel-calendar-YYYY-MM.png`）

- [ ] **Step 2: 失敗を確認** — `npm run test:js` → FAIL
- [ ] **Step 3: `index.js` と Blade の変更を実装**（A・B のタブはこの時点では「準備中」と出すだけでよい）
- [ ] **Step 4: 通ることを確認** — `npm run test:js` → PASS、`npm run build` が成功
- [ ] **Step 5: 画面で確認** — ローカルで動かす：`.env.example` を `.env` にコピーし `DB_CONNECTION=sqlite`、`php artisan key:generate && php artisan migrate`、`php artisan tinker` で今月にタグ付きの配信を 20 件ほど作る（`Stream::factory()` と `Tag`、ショート 4 件・タグなし 2 件を含める）。`php artisan serve` と `npm run dev` で開き、月表示でだけボタンが出る／D のプレビューが出る／`‹ N ›` が効く／「画像を作って共有」で PC では PNG 保存と X の投稿画面が開く、を確認
- [ ] **Step 6: Commit** — `git commit -m "feat: share the month view as a genre mosaic image (#83)"`

---

### Task 7: A（見たまま）と B（まとめ＋カレンダー）

**Files:**
- Modify: `resources/js/share-image/index.js`、`resources/js/share-image/draw.js`、`resources/views/calendar/index.blade.php`

**Interfaces:**
- Consumes: `window.ccShare.renderMonth`（ここで本実装）、`monthStats`・`STAT_TILES`（Task 4）
- Produces:
  - `window.ccShare.renderMonth(el: HTMLElement): Promise<void>` — `el` の中に、今の月・今の絞り込みで、**サムネ付き**の FullCalendar（月表示）を幅 1200px で描き、中の画像がすべて読み込み終わる（または失敗する）まで待つ
  - `drawStatsBand(ctx, stats, box: {x, y, w, h}, title: string): void`（`draw.js`。B の上段。`STAT_TILES` の順に 3 つの数字）

Blade の変更：
- 既存の `eventContent` の中身を `monthEventContent(arg, withThumbs)` に切り出し、本体のカレンダーは `monthEventContent(arg, showThumbs)`、画面外のカレンダーは `monthEventContent(arg, true)` を使う
- 画面外のカレンダーは `events` に `calendar.getEvents()` の今月分をそのまま渡す（再取得しない）。`headerToolbar: false`、`height: 'auto'`
- `.cc-light` クラス：`:root` の明るいテーマの `--cc-*` をそのまま並べ直したルール（`.dark .cc-light` にも効くように）。画面外の要素は `position: fixed; left: -10000px; top: 0; width: 1200px` で `.cc-light` を付ける

共有画面の動き：
- A：`renderMonth` → `html2canvas(el, {useCORS: true, scale: 2, backgroundColor: '#ffffff'})`。html2canvas は `https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js` を、共有画面を初めて開いたときに `<script>` で読み込む
- B：A の画像の上に、見出しと 3 つの数字の帯（高さ 160px × 2 倍）を足した canvas を作る
- プレビューは生成した canvas を縮小して見せる。A・B は作成に時間がかかるので、タブを押したときに作り、作成中は「作成中…」

- [ ] **Step 1: `monthEventContent` の切り出し** — 本体の月表示の見た目が変わらないこと（サムネのオン・オフ両方）をブラウザで確認してから次へ
- [ ] **Step 2: `renderMonth`・`.cc-light`・`drawStatsBand`・A/B のタブを実装**
- [ ] **Step 3: 画面で確認** — Task 6 Step 5 のローカル環境で：ダークテーマでも A・B の画像が明るい／サムネ表示オフでも画像にはサムネが入る／B の数字が `monthStats` と一致する／スマホ幅（DevTools の 390px）で開いても A の画像は 1200px 幅のレイアウトで読める／前後の月の日に出ている配信が B の数字に入っていない
- [ ] **Step 4: 全体のテスト** — `npm run test:js`、`APP_KEY=… php artisan test`、`npm run build` がすべて成功
- [ ] **Step 5: Commit** — `git commit -m "feat: patterns A and B for the month share image (#83)"`

---

## 完了後

- `superpowers:finishing-a-development-branch` に従い、PR を作る（base は `main`。本文に `Closes #83`。既定ブランチが `main` ではないため自動では閉じないので、マージ後に手で閉じる）
- 画面だけの変更＋ API への項目追加なので、マージ後のデプロイ・本番での確認（天和うるの 10 月で D を作る）まで進める

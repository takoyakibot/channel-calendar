# Channel Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** YouTube配信者の配信予定を取得しカレンダー表示するLaravelアプリケーションを構築する。

**Architecture:** Laravel 10をベースに、YouTube Data API v3で配信予定を定期取得しMySQLに保存。公開カレンダーページはFullCalendar.jsで月表示し、管理者のみがチャンネルCRUDを行う。フロントエンドはBlade + Tailwind CSS + vanilla JS。

**Tech Stack:** Laravel 10, PHP 8.1+, MySQL 5.7+, Tailwind CSS, FullCalendar.js (CDN), YouTube Data API v3 (`google/apiclient`), Vite, Laravel Breeze

**Spec:** `docs/superpowers/specs/2026-09-14-channel-calendar-design.md`

## Global Constraints

- PHP 8.1+, Laravel 10.x
- MySQL 5.7+
- Alpine.js は使用しない — Breezeインストール後に除去する
- フロントエンドのインタラクションはvanilla JSで実装
- FullCalendar.js はCDN経由で読み込む（npmパッケージではない）
- YouTube API Keyは `.env` の `YOUTUBE_API_KEY` で管理
- ユーザー登録機能は無効化（管理者はseederで作成）

## File Structure

```
app/
  Console/
    Commands/
      FetchStreams.php          # streams:fetch artisan command
  Http/
    Controllers/
      Admin/
        ChannelController.php   # Admin CRUD for channels
      Api/
        StreamController.php    # Public API: streams JSON
        ChannelController.php   # Public API: channels JSON
      CalendarController.php    # Public calendar page
    Middleware/
      (Breeze defaults)
    Requests/
      StoreChannelRequest.php   # Validation for channel create
      UpdateChannelRequest.php  # Validation for channel update
  Models/
    Channel.php
    Stream.php
  Services/
    YouTubeService.php          # YouTube API wrapper

config/
  services.php                  # (modify) Add youtube api key

database/
  migrations/
    xxxx_create_channels_table.php
    xxxx_create_streams_table.php
  seeders/
    AdminUserSeeder.php

resources/
  views/
    calendar/
      index.blade.php           # Public calendar page
    admin/
      channels/
        index.blade.php         # Channel list
        create.blade.php        # Add channel form
        edit.blade.php          # Edit channel form
    layouts/
      app.blade.php             # (modify) Remove Alpine.js

routes/
  web.php                       # (modify) Add calendar + admin routes
  api.php                       # (modify) Add public API routes

tests/
  Feature/
    Services/
      YouTubeServiceTest.php
    Commands/
      FetchStreamsTest.php
    Api/
      StreamApiTest.php
      ChannelApiTest.php
    Admin/
      ChannelCrudTest.php
    CalendarPageTest.php

.env.example                    # (modify) Add YOUTUBE_API_KEY
```

---

### Task 1: Project Scaffolding & Database

Laravel プロジェクト作成、Breeze導入、Alpine.js除去、マイグレーション作成、モデル定義。

**Files:**
- Create: `database/migrations/xxxx_create_channels_table.php`
- Create: `database/migrations/xxxx_create_streams_table.php`
- Create: `app/Models/Channel.php`
- Create: `app/Models/Stream.php`
- Create: `database/seeders/AdminUserSeeder.php`
- Modify: `resources/views/layouts/app.blade.php` (Alpine.js除去)
- Modify: `.env.example` (YOUTUBE_API_KEY追加)
- Modify: `config/services.php` (youtube設定追加)

**Interfaces:**
- Produces: `Channel` model with `id`, `channel_id`, `name`, `thumbnail_url`, `color`, `is_active` attributes, `streams()` hasMany relation, `scopeActive(Builder $query): Builder`
- Produces: `Stream` model with `id`, `channel_id`, `video_id`, `title`, `description`, `thumbnail_url`, `scheduled_at`, `actual_start_at`, `actual_end_at`, `status` attributes, `channel()` belongsTo relation

- [ ] **Step 1: Create Laravel project**

```bash
cd /Users/aokiyuuta/work/channel-calendar
composer create-project laravel/laravel:^10.0 . --prefer-dist
```

Note: カレントディレクトリに`.git`が既にあるので、Laravelが作る`.gitignore`等はそのまま使う。もし「ディレクトリが空でない」エラーが出たら一時ディレクトリに作成してコピーする。

- [ ] **Step 2: Install dependencies**

```bash
composer require google/apiclient:^2.0
composer require laravel/breeze:^1.29 --dev
php artisan breeze:install blade
npm install
```

- [ ] **Step 3: Remove Alpine.js**

`resources/views/layouts/app.blade.php` と `resources/views/layouts/guest.blade.php` を開き、Alpine.jsへの参照を削除する。

`package.json` の `devDependencies` から `alpinejs` を削除する。

`resources/js/app.js` から Alpine の import と初期化コードを削除する:

```javascript
// 削除対象:
// import Alpine from 'alpinejs';
// window.Alpine = Alpine;
// Alpine.start();
```

Breezeのテンプレート内の `x-dropdown`, `x-modal` 等のAlpine.jsコンポーネントは管理画面で使わないため、後のタスクで管理画面を独自に作る際に問題ない。ナビゲーションのドロップダウン等はvanilla JSで置き換える。

```bash
npm install
npm run build
```

- [ ] **Step 4: Add YOUTUBE_API_KEY to .env.example and config**

`.env.example` の末尾に追加:
```
YOUTUBE_API_KEY=
```

`config/services.php` の配列に追加:
```php
'youtube' => [
    'api_key' => env('YOUTUBE_API_KEY'),
],
```

- [ ] **Step 5: Create channels migration**

```bash
php artisan make:migration create_channels_table
```

マイグレーションファイルの内容:

```php
public function up(): void
{
    Schema::create('channels', function (Blueprint $table) {
        $table->id();
        $table->string('channel_id', 255)->unique();
        $table->string('name', 255);
        $table->string('thumbnail_url', 1024)->nullable();
        $table->string('color', 7)->default('#3B82F6');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('channels');
}
```

- [ ] **Step 6: Create streams migration**

```bash
php artisan make:migration create_streams_table
```

```php
public function up(): void
{
    Schema::create('streams', function (Blueprint $table) {
        $table->id();
        $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
        $table->string('video_id', 255)->unique();
        $table->string('title', 1024);
        $table->text('description')->nullable();
        $table->string('thumbnail_url', 1024)->nullable();
        $table->dateTime('scheduled_at');
        $table->dateTime('actual_start_at')->nullable();
        $table->dateTime('actual_end_at')->nullable();
        $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
        $table->timestamps();

        $table->index('scheduled_at');
        $table->index('status');
    });
}

public function down(): void
{
    Schema::dropIfExists('streams');
}
```

- [ ] **Step 7: Create Channel model**

Create `app/Models/Channel.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'name',
        'thumbnail_url',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 8: Create Stream model**

Create `app/Models/Stream.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stream extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'video_id',
        'title',
        'description',
        'thumbnail_url',
        'scheduled_at',
        'actual_start_at',
        'actual_end_at',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
```

- [ ] **Step 9: Create AdminUserSeeder**

Create `database/seeders/AdminUserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );
    }
}
```

`database/seeders/DatabaseSeeder.php` の `run()` に追加:

```php
$this->call(AdminUserSeeder::class);
```

- [ ] **Step 10: Create model factories for testing**

Create `database/factories/ChannelFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_id' => 'UC' . $this->faker->regexify('[A-Za-z0-9]{22}'),
            'name' => $this->faker->name(),
            'thumbnail_url' => $this->faker->imageUrl(),
            'color' => $this->faker->hexColor(),
            'is_active' => true,
        ];
    }
}
```

Create `database/factories/StreamFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class StreamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'video_id' => $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'thumbnail_url' => $this->faker->imageUrl(),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'status' => 'upcoming',
        ];
    }
}
```

- [ ] **Step 11: Run migrations and verify**

```bash
php artisan migrate
php artisan test
```

すべてのBreezeデフォルトテストがパスすることを確認。

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: scaffold Laravel project with Breeze, channels/streams models and migrations"
```

---

### Task 2: YouTube API Service

YouTube Data API v3のラッパーサービスクラスを作成。チャンネル情報取得と配信予定検索を担当。

**Files:**
- Create: `app/Services/YouTubeService.php`
- Create: `tests/Feature/Services/YouTubeServiceTest.php`

**Interfaces:**
- Consumes: `config('services.youtube.api_key')` for API authentication
- Produces: `YouTubeService::getChannelInfo(string $channelId): array` — returns `['name' => string, 'thumbnail_url' => string]`
- Produces: `YouTubeService::searchStreams(string $channelId, string $eventType): array` — returns array of `['video_id' => string, 'title' => string, 'thumbnail_url' => string]`
- Produces: `YouTubeService::getVideoDetails(array $videoIds): array` — returns array of `['video_id' => string, 'scheduled_at' => string, 'actual_start_at' => ?string, 'actual_end_at' => ?string, 'status' => string]`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Services/YouTubeServiceTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Services\YouTubeService;
use Google\Service\YouTube;
use Google\Service\YouTube\Resource\Channels;
use Google\Service\YouTube\Resource\Search;
use Google\Service\YouTube\Resource\Videos;
use Google\Service\YouTube\ChannelListResponse;
use Google\Service\YouTube\Channel as YouTubeChannel;
use Google\Service\YouTube\ChannelSnippet;
use Google\Service\YouTube\ThumbnailDetails;
use Google\Service\YouTube\Thumbnail;
use Google\Service\YouTube\SearchListResponse;
use Google\Service\YouTube\SearchResult;
use Google\Service\YouTube\ResourceId;
use Google\Service\YouTube\SearchResultSnippet;
use Google\Service\YouTube\VideoListResponse;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoLiveStreamingDetails;
use Mockery;
use Tests\TestCase;

class YouTubeServiceTest extends TestCase
{
    private YouTube $mockYouTube;
    private YouTubeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockYouTube = Mockery::mock(YouTube::class);
        $this->service = new YouTubeService($this->mockYouTube);
    }

    public function test_get_channel_info_returns_name_and_thumbnail(): void
    {
        $thumbnail = new Thumbnail();
        $thumbnail->setUrl('https://example.com/thumb.jpg');
        $thumbnails = new ThumbnailDetails();
        $thumbnails->setDefault($thumbnail);
        $snippet = new ChannelSnippet();
        $snippet->setTitle('Test Channel');
        $snippet->setThumbnails($thumbnails);
        $channel = new YouTubeChannel();
        $channel->setSnippet($snippet);
        $response = new ChannelListResponse();
        $response->setItems([$channel]);

        $mockChannels = Mockery::mock(Channels::class);
        $mockChannels->shouldReceive('listChannels')
            ->with('snippet', ['id' => 'UC_test123'])
            ->andReturn($response);
        $this->mockYouTube->channels = $mockChannels;

        $result = $this->service->getChannelInfo('UC_test123');

        $this->assertEquals('Test Channel', $result['name']);
        $this->assertEquals('https://example.com/thumb.jpg', $result['thumbnail_url']);
    }

    public function test_search_streams_returns_video_list(): void
    {
        $resourceId = new ResourceId();
        $resourceId->setVideoId('vid123');
        $snippet = new SearchResultSnippet();
        $snippet->setTitle('Test Stream');
        $thumbDetail = new ThumbnailDetails();
        $thumb = new Thumbnail();
        $thumb->setUrl('https://example.com/stream.jpg');
        $thumbDetail->setDefault($thumb);
        $snippet->setThumbnails($thumbDetail);
        $item = new SearchResult();
        $item->setId($resourceId);
        $item->setSnippet($snippet);
        $response = new SearchListResponse();
        $response->setItems([$item]);

        $mockSearch = Mockery::mock(Search::class);
        $mockSearch->shouldReceive('listSearch')
            ->with('snippet', Mockery::on(function ($params) {
                return $params['channelId'] === 'UC_test123'
                    && $params['type'] === 'video'
                    && $params['eventType'] === 'upcoming';
            }))
            ->andReturn($response);
        $this->mockYouTube->search = $mockSearch;

        $result = $this->service->searchStreams('UC_test123', 'upcoming');

        $this->assertCount(1, $result);
        $this->assertEquals('vid123', $result[0]['video_id']);
        $this->assertEquals('Test Stream', $result[0]['title']);
    }

    public function test_get_video_details_returns_streaming_info(): void
    {
        $snippet = new VideoSnippet();
        $snippet->setTitle('Test Stream');
        $details = new VideoLiveStreamingDetails();
        $details->setScheduledStartTime('2026-09-15T19:00:00Z');
        $details->setActualStartTime(null);
        $details->setActualEndTime(null);
        $video = new Video();
        $video->setId('vid123');
        $video->setSnippet($snippet);
        $video->setLiveStreamingDetails($details);
        $response = new VideoListResponse();
        $response->setItems([$video]);

        $mockVideos = Mockery::mock(Videos::class);
        $mockVideos->shouldReceive('listVideos')
            ->with('snippet,liveStreamingDetails', ['id' => 'vid123'])
            ->andReturn($response);
        $this->mockYouTube->videos = $mockVideos;

        $result = $this->service->getVideoDetails(['vid123']);

        $this->assertCount(1, $result);
        $this->assertEquals('vid123', $result[0]['video_id']);
        $this->assertEquals('2026-09-15T19:00:00Z', $result[0]['scheduled_at']);
        $this->assertEquals('upcoming', $result[0]['status']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Services/YouTubeServiceTest.php
```

Expected: FAIL — `YouTubeService` class not found.

- [ ] **Step 3: Implement YouTubeService**

Create `app/Services/YouTubeService.php`:

```php
<?php

namespace App\Services;

use Google\Service\YouTube;

class YouTubeService
{
    public function __construct(private YouTube $youtube)
    {
    }

    public function getChannelInfo(string $channelId): array
    {
        $response = $this->youtube->channels->listChannels('snippet', [
            'id' => $channelId,
        ]);

        $items = $response->getItems();
        if (empty($items)) {
            throw new \RuntimeException("Channel not found: {$channelId}");
        }

        $channel = $items[0];
        $snippet = $channel->getSnippet();

        return [
            'name' => $snippet->getTitle(),
            'thumbnail_url' => $snippet->getThumbnails()->getDefault()->getUrl(),
        ];
    }

    public function searchStreams(string $channelId, string $eventType): array
    {
        $response = $this->youtube->search->listSearch('snippet', [
            'channelId' => $channelId,
            'type' => 'video',
            'eventType' => $eventType,
            'order' => 'date',
            'maxResults' => 50,
        ]);

        return array_map(function ($item) {
            $snippet = $item->getSnippet();
            return [
                'video_id' => $item->getId()->getVideoId(),
                'title' => $snippet->getTitle(),
                'thumbnail_url' => $snippet->getThumbnails()->getDefault()->getUrl(),
            ];
        }, $response->getItems());
    }

    public function getVideoDetails(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        $response = $this->youtube->videos->listVideos('snippet,liveStreamingDetails', [
            'id' => implode(',', $videoIds),
        ]);

        return array_map(function ($video) {
            $details = $video->getLiveStreamingDetails();
            $actualStart = $details?->getActualStartTime();
            $actualEnd = $details?->getActualEndTime();

            if ($actualEnd) {
                $status = 'completed';
            } elseif ($actualStart) {
                $status = 'live';
            } else {
                $status = 'upcoming';
            }

            return [
                'video_id' => $video->getId(),
                'title' => $video->getSnippet()->getTitle(),
                'scheduled_at' => $details?->getScheduledStartTime(),
                'actual_start_at' => $actualStart,
                'actual_end_at' => $actualEnd,
                'status' => $status,
            ];
        }, $response->getItems());
    }
}
```

Register in `app/Providers/AppServiceProvider.php` `register()`:

```php
$this->app->singleton(\App\Services\YouTubeService::class, function ($app) {
    $client = new \Google\Client();
    $client->setDeveloperKey(config('services.youtube.api_key'));
    $youtube = new \Google\Service\YouTube($client);
    return new \App\Services\YouTubeService($youtube);
});
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Services/YouTubeServiceTest.php
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/YouTubeService.php app/Providers/AppServiceProvider.php tests/Feature/Services/YouTubeServiceTest.php
git commit -m "feat: add YouTubeService wrapping YouTube Data API v3"
```

---

### Task 3: streams:fetch Artisan Command

定期バッチコマンド。全アクティブチャンネルの配信予定を取得しDBにupsertする。

**Files:**
- Create: `app/Console/Commands/FetchStreams.php`
- Create: `tests/Feature/Commands/FetchStreamsTest.php`
- Modify: `app/Console/Kernel.php` (scheduler registration)

**Interfaces:**
- Consumes: `Channel::active()->get()` from Task 1
- Consumes: `YouTubeService::searchStreams(string, string): array` from Task 2
- Consumes: `YouTubeService::getVideoDetails(array): array` from Task 2
- Produces: Artisan command `streams:fetch` that populates `streams` table

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Commands/FetchStreamsTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FetchStreamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_streams_creates_new_streams(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'upcoming')
            ->andReturn([
                ['video_id' => 'vid1', 'title' => 'Stream 1', 'thumbnail_url' => 'https://example.com/1.jpg'],
            ]);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'live')
            ->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')
            ->with(['vid1'])
            ->andReturn([
                [
                    'video_id' => 'vid1',
                    'title' => 'Stream 1 Full',
                    'scheduled_at' => '2026-09-15T19:00:00Z',
                    'actual_start_at' => null,
                    'actual_end_at' => null,
                    'status' => 'upcoming',
                ],
            ]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid1',
            'title' => 'Stream 1 Full',
            'status' => 'upcoming',
        ]);
    }

    public function test_fetch_streams_updates_existing_stream(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid1',
            'title' => 'Old Title',
            'status' => 'upcoming',
        ]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'upcoming')
            ->andReturn([]);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'live')
            ->andReturn([
                ['video_id' => 'vid1', 'title' => 'Now Live', 'thumbnail_url' => 'https://example.com/1.jpg'],
            ]);
        $mockService->shouldReceive('getVideoDetails')
            ->with(['vid1'])
            ->andReturn([
                [
                    'video_id' => 'vid1',
                    'title' => 'Now Live',
                    'scheduled_at' => '2026-09-15T19:00:00Z',
                    'actual_start_at' => '2026-09-15T19:02:00Z',
                    'actual_end_at' => null,
                    'status' => 'live',
                ],
            ]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid1',
            'title' => 'Now Live',
            'status' => 'live',
        ]);
        $this->assertDatabaseCount('streams', 1);
    }

    public function test_fetch_streams_skips_inactive_channels(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_inactive', 'is_active' => false]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldNotReceive('searchStreams');

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();
    }

    public function test_fetch_streams_marks_old_upcoming_as_completed(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid_old',
            'status' => 'upcoming',
            'scheduled_at' => now()->subHours(25),
        ]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')->andReturn([]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid_old',
            'status' => 'completed',
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Commands/FetchStreamsTest.php
```

Expected: FAIL — command `streams:fetch` not registered.

- [ ] **Step 3: Implement FetchStreams command**

Create `app/Console/Commands/FetchStreams.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class FetchStreams extends Command
{
    protected $signature = 'streams:fetch';
    protected $description = 'Fetch upcoming and live streams from YouTube for all active channels';

    public function handle(YouTubeService $youtube): int
    {
        $channels = Channel::active()->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels found.');
            return self::SUCCESS;
        }

        foreach ($channels as $channel) {
            $this->info("Fetching streams for: {$channel->name}");

            try {
                $this->fetchForChannel($youtube, $channel);
            } catch (\Throwable $e) {
                $this->error("Failed for {$channel->name}: {$e->getMessage()}");
            }
        }

        $this->markOldStreamsCompleted();

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function fetchForChannel(YouTubeService $youtube, Channel $channel): void
    {
        $upcoming = $youtube->searchStreams($channel->channel_id, 'upcoming');
        $live = $youtube->searchStreams($channel->channel_id, 'live');

        $allResults = array_merge($upcoming, $live);
        if (empty($allResults)) {
            return;
        }

        $videoIds = array_column($allResults, 'video_id');
        $details = $youtube->getVideoDetails($videoIds);

        $thumbnailMap = [];
        foreach ($allResults as $result) {
            $thumbnailMap[$result['video_id']] = $result['thumbnail_url'];
        }

        foreach ($details as $detail) {
            if ($detail['scheduled_at'] === null) {
                continue;
            }

            Stream::updateOrCreate(
                ['video_id' => $detail['video_id']],
                [
                    'channel_id' => $channel->id,
                    'title' => $detail['title'],
                    'thumbnail_url' => $thumbnailMap[$detail['video_id']] ?? null,
                    'scheduled_at' => $detail['scheduled_at'],
                    'actual_start_at' => $detail['actual_start_at'],
                    'actual_end_at' => $detail['actual_end_at'],
                    'status' => $detail['status'],
                ]
            );
        }
    }

    private function markOldStreamsCompleted(): void
    {
        Stream::where('status', 'upcoming')
            ->where('scheduled_at', '<', now()->subHours(24))
            ->update(['status' => 'completed']);
    }
}
```

- [ ] **Step 4: Register scheduler**

In `app/Console/Kernel.php`, add to `schedule()`:

```php
$schedule->command('streams:fetch')->everyThirtyMinutes();
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Commands/FetchStreamsTest.php
```

Expected: 4 tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/FetchStreams.php app/Console/Kernel.php tests/Feature/Commands/FetchStreamsTest.php
git commit -m "feat: add streams:fetch command for periodic YouTube API polling"
```

---

### Task 4: Public API Endpoints

FullCalendarとチャンネルフィルターが消費するJSON APIを実装。

**Files:**
- Create: `app/Http/Controllers/Api/StreamController.php`
- Create: `app/Http/Controllers/Api/ChannelController.php`
- Create: `tests/Feature/Api/StreamApiTest.php`
- Create: `tests/Feature/Api/ChannelApiTest.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `Stream` model, `Channel` model from Task 1
- Produces: `GET /api/streams?start={Y-m-d}&end={Y-m-d}` — returns FullCalendar event format JSON `[{id, title, start, end, url, color, extendedProps: {channel_name, thumbnail_url, status}}]`
- Produces: `GET /api/channels` — returns `[{id, name, color, thumbnail_url}]`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Api/StreamApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_streams_endpoint_returns_fullcalendar_format(): void
    {
        $channel = Channel::factory()->create(['name' => 'Test Ch', 'color' => '#FF0000']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid1',
            'title' => 'Test Stream',
            'scheduled_at' => '2026-09-15 19:00:00',
            'status' => 'upcoming',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'title' => 'Test Stream',
            'color' => '#FF0000',
        ]);
        $response->assertJsonStructure([
            ['id', 'title', 'start', 'url', 'color', 'extendedProps' => ['channel_name', 'status']],
        ]);
    }

    public function test_streams_endpoint_filters_by_date_range(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-09-15 19:00:00',
        ]);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-10-15 19:00:00',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_streams_endpoint_excludes_inactive_channels(): void
    {
        $active = Channel::factory()->create(['is_active' => true]);
        $inactive = Channel::factory()->create(['is_active' => false]);
        Stream::factory()->create([
            'channel_id' => $active->id,
            'scheduled_at' => '2026-09-15 19:00:00',
        ]);
        Stream::factory()->create([
            'channel_id' => $inactive->id,
            'scheduled_at' => '2026-09-15 19:00:00',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(1);
    }
}
```

Create `tests/Feature/Api/ChannelApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_channels_endpoint_returns_active_channels(): void
    {
        Channel::factory()->create(['name' => 'Active Ch', 'is_active' => true]);
        Channel::factory()->create(['name' => 'Inactive Ch', 'is_active' => false]);

        $response = $this->getJson('/api/channels');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Active Ch']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Api/
```

Expected: FAIL — routes not defined.

- [ ] **Step 3: Implement API controllers**

Create `app/Http/Controllers/Api/StreamController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $streams = Stream::with('channel')
            ->whereHas('channel', fn ($q) => $q->where('is_active', true))
            ->whereBetween('scheduled_at', [$request->start, $request->end])
            ->orderBy('scheduled_at')
            ->get();

        $events = $streams->map(fn (Stream $stream) => [
            'id' => $stream->id,
            'title' => $stream->title,
            'start' => $stream->scheduled_at->toIso8601String(),
            'end' => $stream->actual_end_at?->toIso8601String(),
            'url' => "https://www.youtube.com/watch?v={$stream->video_id}",
            'color' => $stream->channel->color,
            'extendedProps' => [
                'channel_name' => $stream->channel->name,
                'thumbnail_url' => $stream->thumbnail_url,
                'status' => $stream->status,
            ],
        ]);

        return response()->json($events);
    }
}
```

Create `app/Http/Controllers/Api/ChannelController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;

class ChannelController extends Controller
{
    public function index(): JsonResponse
    {
        $channels = Channel::active()
            ->select('id', 'name', 'color', 'thumbnail_url')
            ->orderBy('name')
            ->get();

        return response()->json($channels);
    }
}
```

- [ ] **Step 4: Add API routes**

In `routes/api.php`:

```php
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\ChannelController;

Route::get('/streams', [StreamController::class, 'index']);
Route::get('/channels', [ChannelController::class, 'index']);
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Api/
```

Expected: 4 tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/ routes/api.php tests/Feature/Api/
git commit -m "feat: add public API endpoints for streams and channels"
```

---

### Task 5: Admin Channel Management

管理者用のチャンネルCRUD。認証必須。チャンネルID入力時にYouTube APIで名前とサムネイルを自動取得。

**Files:**
- Create: `app/Http/Controllers/Admin/ChannelController.php`
- Create: `app/Http/Requests/StoreChannelRequest.php`
- Create: `app/Http/Requests/UpdateChannelRequest.php`
- Create: `resources/views/admin/channels/index.blade.php`
- Create: `resources/views/admin/channels/create.blade.php`
- Create: `resources/views/admin/channels/edit.blade.php`
- Create: `tests/Feature/Admin/ChannelCrudTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `Channel` model from Task 1
- Consumes: `YouTubeService::getChannelInfo(string): array` from Task 2
- Produces: Admin web UI at `/admin/channels` (CRUD)

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Admin/ChannelCrudTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\User;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChannelCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_guest_cannot_access_admin_channels(): void
    {
        $response = $this->get('/admin/channels');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_channel_list(): void
    {
        Channel::factory()->create(['name' => 'Test Channel']);

        $response = $this->actingAs($this->admin)->get('/admin/channels');

        $response->assertOk();
        $response->assertSee('Test Channel');
    }

    public function test_admin_can_create_channel(): void
    {
        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('getChannelInfo')
            ->with('UC_new')
            ->andReturn(['name' => 'New Channel', 'thumbnail_url' => 'https://example.com/new.jpg']);
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)->post('/admin/channels', [
            'channel_id' => 'UC_new',
            'color' => '#FF0000',
        ]);

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', [
            'channel_id' => 'UC_new',
            'name' => 'New Channel',
            'color' => '#FF0000',
        ]);
    }

    public function test_admin_can_update_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', [
            'id' => $channel->id,
            'color' => '#00FF00',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/channels/{$channel->id}");

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Admin/ChannelCrudTest.php
```

Expected: FAIL — routes not defined.

- [ ] **Step 3: Create form request validators**

Create `app/Http/Requests/StoreChannelRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel_id' => 'required|string|max:255|unique:channels,channel_id',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }
}
```

Create `app/Http/Requests/UpdateChannelRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
        ];
    }
}
```

- [ ] **Step 4: Implement Admin ChannelController**

Create `app/Http/Controllers/Admin/ChannelController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelRequest;
use App\Http\Requests\UpdateChannelRequest;
use App\Models\Channel;
use App\Services\YouTubeService;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::orderBy('name')->get();
        return view('admin.channels.index', compact('channels'));
    }

    public function create()
    {
        return view('admin.channels.create');
    }

    public function store(StoreChannelRequest $request, YouTubeService $youtube)
    {
        $info = $youtube->getChannelInfo($request->channel_id);

        Channel::create([
            'channel_id' => $request->channel_id,
            'name' => $info['name'],
            'thumbnail_url' => $info['thumbnail_url'],
            'color' => $request->color,
        ]);

        return redirect('/admin/channels')->with('success', 'チャンネルを追加しました。');
    }

    public function edit(Channel $channel)
    {
        return view('admin.channels.edit', compact('channel'));
    }

    public function update(UpdateChannelRequest $request, Channel $channel)
    {
        $channel->update([
            'color' => $request->color,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect('/admin/channels')->with('success', 'チャンネルを更新しました。');
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();
        return redirect('/admin/channels')->with('success', 'チャンネルを削除しました。');
    }
}
```

- [ ] **Step 5: Add admin routes**

In `routes/web.php`, add inside auth middleware:

```php
use App\Http\Controllers\Admin\ChannelController as AdminChannelController;

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::resource('channels', AdminChannelController::class)->except(['show']);
});
```

- [ ] **Step 6: Create Blade views**

Create `resources/views/admin/channels/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャンネル管理</h2>
            <a href="{{ url('/admin/channels/create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                チャンネル追加
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">サムネイル</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">チャンネル名</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">カラー</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状態</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($channels as $channel)
                        <tr>
                            <td class="px-6 py-4">
                                @if ($channel->thumbnail_url)
                                    <img src="{{ $channel->thumbnail_url }}" alt="" class="w-10 h-10 rounded-full">
                                @endif
                            </td>
                            <td class="px-6 py-4 font-medium">{{ $channel->name }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-block w-6 h-6 rounded" style="background-color:{{ $channel->color }}"></span>
                                {{ $channel->color }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs {{ $channel->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $channel->is_active ? '有効' : '無効' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 space-x-2">
                                <a href="{{ url("/admin/channels/{$channel->id}/edit") }}" class="text-blue-600 hover:underline text-sm">編集</a>
                                <form method="POST" action="{{ url("/admin/channels/{$channel->id}") }}" style="display:inline;"
                                      onsubmit="return confirm('本当に削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">削除</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
```

Create `resources/views/admin/channels/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャンネル追加</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ url('/admin/channels') }}">
                    @csrf
                    <div class="mb-4">
                        <label for="channel_id" class="block text-sm font-medium text-gray-700 mb-1">YouTubeチャンネルID</label>
                        <input type="text" name="channel_id" id="channel_id" value="{{ old('channel_id') }}"
                               class="w-full border-gray-300 rounded-md shadow-sm" placeholder="UC..." required>
                    </div>
                    <div class="mb-6">
                        <label for="color" class="block text-sm font-medium text-gray-700 mb-1">カレンダー表示色</label>
                        <input type="color" name="color" id="color" value="{{ old('color', '#3B82F6') }}"
                               class="h-10 w-20 border-gray-300 rounded">
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">追加</button>
                        <a href="{{ url('/admin/channels') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

Create `resources/views/admin/channels/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャンネル編集: {{ $channel->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="mb-6 flex items-center gap-4">
                    @if ($channel->thumbnail_url)
                        <img src="{{ $channel->thumbnail_url }}" alt="" class="w-16 h-16 rounded-full">
                    @endif
                    <div>
                        <p class="font-medium text-lg">{{ $channel->name }}</p>
                        <p class="text-sm text-gray-500">{{ $channel->channel_id }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ url("/admin/channels/{$channel->id}") }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label for="color" class="block text-sm font-medium text-gray-700 mb-1">カレンダー表示色</label>
                        <input type="color" name="color" id="color" value="{{ old('color', $channel->color) }}"
                               class="h-10 w-20 border-gray-300 rounded">
                    </div>
                    <div class="mb-6">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $channel->is_active) ? 'checked' : '' }}
                                   class="rounded border-gray-300">
                            <span class="text-sm text-gray-700">有効</span>
                        </label>
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">更新</button>
                        <a href="{{ url('/admin/channels') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Admin/ChannelCrudTest.php
```

Expected: 5 tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/ app/Http/Requests/ resources/views/admin/ routes/web.php tests/Feature/Admin/
git commit -m "feat: add admin channel CRUD with auth protection"
```

---

### Task 6: Public Calendar Page

FullCalendar.jsを使った公開カレンダーページ。チャンネルフィルター付き。

**Files:**
- Create: `app/Http/Controllers/CalendarController.php`
- Create: `resources/views/calendar/index.blade.php`
- Create: `tests/Feature/CalendarPageTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `GET /api/streams` from Task 4
- Consumes: `GET /api/channels` from Task 4
- Produces: Public calendar page at `/`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/CalendarPageTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    public function test_calendar_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Channel Calendar');
        $response->assertSee('fullcalendar');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/CalendarPageTest.php
```

Expected: FAIL — Breeze default welcome page doesn't contain 'Channel Calendar'.

- [ ] **Step 3: Implement CalendarController**

Create `app/Http/Controllers/CalendarController.php`:

```php
<?php

namespace App\Http\Controllers;

class CalendarController extends Controller
{
    public function index()
    {
        return view('calendar.index');
    }
}
```

- [ ] **Step 4: Update route**

In `routes/web.php`, replace the default `/` route:

```php
use App\Http\Controllers\CalendarController;

Route::get('/', [CalendarController::class, 'index']);
```

- [ ] **Step 5: Create calendar Blade view**

Create `resources/views/calendar/index.blade.php`:

```blade
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Channel Calendar</title>
    @vite(['resources/css/app.css'])
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <style>
        .channel-filter { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .channel-filter label { display: flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; cursor: pointer; border: 1px solid #e5e7eb; }
        .channel-filter label:hover { background-color: #f3f4f6; }
        .fc-event { cursor: pointer; }
        .tooltip { position: absolute; z-index: 50; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); pointer-events: none; max-width: 300px; font-size: 0.875rem; }
        .tooltip .channel-name { font-weight: 600; margin-bottom: 0.25rem; }
        .tooltip .stream-title { color: #374151; }
        .tooltip .stream-time { color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; }
        .status-badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 600; text-transform: uppercase; }
        .status-live { background: #fef2f2; color: #dc2626; }
        .status-upcoming { background: #eff6ff; color: #2563eb; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Channel Calendar</h1>
            @auth
                <a href="{{ url('/admin/channels') }}" class="text-sm text-blue-600 hover:underline">管理画面</a>
            @endauth
        </div>

        <div id="channel-filter" class="channel-filter"></div>
        <div id="calendar"></div>
    </div>

    <div id="tooltip" class="tooltip" hidden></div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');
        var tooltipEl = document.getElementById('tooltip');
        var filterEl = document.getElementById('channel-filter');
        var hiddenChannels = {};

        fetch('/api/channels')
            .then(function (res) { return res.json(); })
            .then(function (channels) {
                channels.forEach(function (ch) {
                    var label = document.createElement('label');
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = true;
                    checkbox.dataset.channelId = ch.id;
                    checkbox.addEventListener('change', function () {
                        if (this.checked) {
                            delete hiddenChannels[ch.id];
                        } else {
                            hiddenChannels[ch.id] = true;
                        }
                        calendar.refetchEvents();
                    });

                    var dot = document.createElement('span');
                    dot.style.display = 'inline-block';
                    dot.style.width = '0.75rem';
                    dot.style.height = '0.75rem';
                    dot.style.borderRadius = '50%';
                    dot.style.backgroundColor = ch.color;

                    var text = document.createTextNode(ch.name);
                    label.appendChild(checkbox);
                    label.appendChild(dot);
                    label.appendChild(text);
                    filterEl.appendChild(label);
                });
            });

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'ja',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: function (info, successCallback, failureCallback) {
                fetch('/api/streams?start=' + info.startStr.slice(0, 10) + '&end=' + info.endStr.slice(0, 10))
                    .then(function (res) { return res.json(); })
                    .then(function (events) {
                        var filtered = events.filter(function (ev) {
                            return !hiddenChannels[ev.extendedProps.channel_id];
                        });
                        successCallback(filtered);
                    })
                    .catch(failureCallback);
            },
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.open(info.event.url, '_blank');
                }
            },
            eventMouseEnter: function (info) {
                var props = info.event.extendedProps;
                var time = new Date(info.event.start).toLocaleString('ja-JP');
                var statusClass = props.status === 'live' ? 'status-live' : 'status-upcoming';
                var statusText = props.status === 'live' ? 'LIVE' : props.status.toUpperCase();

                tooltipEl.innerHTML =
                    '<div class="channel-name">' + escapeHtml(props.channel_name) + '</div>' +
                    '<div class="stream-title">' + escapeHtml(info.event.title) + '</div>' +
                    '<div class="stream-time">' + time + ' <span class="status-badge ' + statusClass + '">' + statusText + '</span></div>';
                tooltipEl.hidden = false;

                var rect = info.el.getBoundingClientRect();
                tooltipEl.style.top = (rect.bottom + window.scrollY + 8) + 'px';
                tooltipEl.style.left = (rect.left + window.scrollX) + 'px';
            },
            eventMouseLeave: function () {
                tooltipEl.hidden = true;
            },
            height: 'auto',
        });

        calendar.render();
    });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
</body>
</html>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test tests/Feature/CalendarPageTest.php
```

Expected: PASS.

- [ ] **Step 7: Verify all tests pass**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/CalendarController.php resources/views/calendar/ routes/web.php tests/Feature/CalendarPageTest.php
git commit -m "feat: add public calendar page with FullCalendar.js"
```

---

### Task 7: Disable Registration & Final Wiring

Breezeの登録機能を無効化し、ナビゲーションのAlpine.js依存をvanilla JSに置き換え。全体の動作確認。

**Files:**
- Modify: `routes/auth.php` (registration routes削除)
- Modify: `resources/views/layouts/navigation.blade.php` (Alpine → vanilla JS)

**Interfaces:**
- Consumes: All prior tasks
- Produces: Complete, deployable application

- [ ] **Step 1: Remove registration routes**

In `routes/auth.php`, remove or comment out these lines:

```php
// Remove these:
// Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
// Route::post('register', [RegisteredUserController::class, 'store']);
```

- [ ] **Step 2: Replace Alpine.js in navigation with vanilla JS**

In `resources/views/layouts/navigation.blade.php`, replace any `x-dropdown` / `@click` Alpine directives with vanilla JS.

Find the settings dropdown button and add an `onclick` handler:

```html
<button onclick="document.getElementById('user-dropdown').hidden = !document.getElementById('user-dropdown').hidden"
        class="inline-flex items-center ...">
```

Replace the dropdown content div to use `id="user-dropdown" hidden`:

```html
<div id="user-dropdown" hidden class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
```

Similarly for the mobile hamburger menu, replace Alpine with:

```html
<button onclick="document.getElementById('mobile-menu').hidden = !document.getElementById('mobile-menu').hidden">
```

And on the mobile menu div:

```html
<div id="mobile-menu" hidden>
```

- [ ] **Step 3: Add navigation link to admin channels**

In the navigation's authenticated links section, add:

```blade
<a href="{{ url('/admin/channels') }}" class="...">チャンネル管理</a>
```

- [ ] **Step 4: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 5: Build frontend assets**

```bash
npm run build
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: disable registration, replace Alpine.js with vanilla JS in navigation"
```

# Channel Calendar - Design Spec

## Overview

YouTube配信者の配信予定を取得し、月表示カレンダーで一覧表示するWebアプリケーション。
管理者がチャンネルを登録し、定期バッチでYouTube Data APIから配信予定を取得、公開カレンダーに表示する。

## Tech Stack

- **Backend**: Laravel 10, PHP 8.1+
- **Frontend**: Blade + Tailwind CSS + vanilla JavaScript
- **Calendar**: FullCalendar.js (CDN)
- **Database**: MySQL 5.7+
- **API**: YouTube Data API v3 (`google/apiclient`)
- **Build**: Vite
- **Auth**: Laravel Breeze (admin only)

Reference: https://github.com/takoyakibot/ycs (same server deployment)

## Data Model

### users (Laravel Breeze standard)

管理者認証用。Breezeのデフォルトマイグレーションをそのまま使用。

### channels

| Column | Type | Description |
|--------|------|-------------|
| id | bigint (PK) | Auto increment |
| channel_id | varchar(255) | YouTube channel ID (UC...) |
| name | varchar(255) | Channel name (auto-fetched from API) |
| thumbnail_url | varchar(1024) | Channel thumbnail URL |
| color | varchar(7) | Calendar display color (#hex) |
| is_active | boolean | Whether to fetch streams for this channel |
| created_at | timestamp | |
| updated_at | timestamp | |

- Unique constraint on `channel_id`

### streams

| Column | Type | Description |
|--------|------|-------------|
| id | bigint (PK) | Auto increment |
| channel_id | bigint (FK) | References channels.id |
| video_id | varchar(255) | YouTube video ID |
| title | varchar(1024) | Stream title |
| description | text, nullable | Stream description |
| thumbnail_url | varchar(1024), nullable | Stream thumbnail |
| scheduled_at | datetime | Scheduled start time |
| actual_start_at | datetime, nullable | Actual start time |
| actual_end_at | datetime, nullable | Actual end time |
| status | enum('upcoming','live','completed') | Stream status |
| created_at | timestamp | |
| updated_at | timestamp | |

- Unique constraint on `video_id`
- Index on `scheduled_at`
- Index on `status`

## Screens

### 1. Calendar Page (Public) — `/`

- FullCalendar.js month view
- Events colored by channel
- Click event → open YouTube video in new tab
- Event tooltip showing stream title, channel name, scheduled time
- Channel filter (checkboxes to show/hide channels)
- Month/week/day view toggle (FullCalendar built-in)
- API endpoint: `GET /api/streams?start={date}&end={date}` returns JSON for FullCalendar

### 2. Channel Management (Auth Required) — `/admin/channels`

- List of registered channels with name, thumbnail, color, active status
- Add channel: input YouTube channel ID → auto-fetch name/thumbnail via API
- Edit channel: change color, toggle active
- Delete channel: soft confirmation with vanilla JS `confirm()`

### 3. Login — `/login`

- Laravel Breeze standard login page
- Registration disabled (seed admin user via artisan command)

## Batch Processing

### Command: `streams:fetch`

```
php artisan streams:fetch
```

1. Fetch all active channels from `channels` table
2. For each channel:
   a. YouTube Search API: `search.list` with `channelId`, `type=video`, `eventType=upcoming`, `order=date`
   b. YouTube Search API: `search.list` with `channelId`, `type=video`, `eventType=live`
   c. YouTube Videos API: `videos.list` with video IDs to get `liveStreamingDetails` (scheduled start, actual start/end)
3. Upsert results into `streams` table (match on `video_id`)
4. Mark streams not returned and older than 24h past scheduled time as `completed`

### Scheduling

- Run every 30 minutes via Laravel Scheduler
- `$schedule->command('streams:fetch')->everyThirtyMinutes()`
- Server cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`

## API Endpoints

### Public

- `GET /api/streams?start={Y-m-d}&end={Y-m-d}` — Returns streams in FullCalendar event format
- `GET /api/channels` — Returns active channels (for filter UI)

### Admin (auth required)

- `GET /admin/channels` — Channel list page
- `POST /admin/channels` — Create channel
- `PUT /admin/channels/{id}` — Update channel
- `DELETE /admin/channels/{id}` — Delete channel

## Environment Variables

```
YOUTUBE_API_KEY=          # YouTube Data API v3 key
```

Added to `.env.example` alongside standard Laravel env vars.

## Deployment

Same server as ycs. Standard Laravel deployment:

```bash
composer install --no-dev
npm install && npm run build
php artisan migrate
php artisan db:seed  # creates admin user
```

## Out of Scope

- User registration (admin only, seeded)
- Notification/push for upcoming streams
- Past stream archive browsing
- Multi-language support
- RSS/iCal export

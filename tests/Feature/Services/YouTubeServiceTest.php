<?php

namespace Tests\Feature\Services;

use App\Services\YouTubeService;
use Google\Service\YouTube;
use Google\Service\YouTube\Resource\Channels;
use Google\Service\YouTube\Resource\PlaylistItems;
use Google\Service\YouTube\Resource\Videos;
use Google\Service\YouTube\ChannelListResponse;
use Google\Service\YouTube\Channel as YouTubeChannel;
use Google\Service\YouTube\ChannelSnippet;
use Google\Service\YouTube\ThumbnailDetails;
use Google\Service\YouTube\Thumbnail;
use Google\Service\YouTube\PlaylistItem;
use Google\Service\YouTube\PlaylistItemListResponse;
use Google\Service\YouTube\PlaylistItemSnippet;
use Google\Service\YouTube\ResourceId;
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

    private function channelListResponse(string $id, string $title, ?string $customUrl): ChannelListResponse
    {
        $snippet = new ChannelSnippet();
        $snippet->setTitle($title);
        $snippet->setCustomUrl($customUrl);
        $channel = new YouTubeChannel();
        $channel->setId($id);
        $channel->setSnippet($snippet);
        $response = new ChannelListResponse();
        $response->setItems([$channel]);

        return $response;
    }

    public function test_find_channel_by_handle_uses_for_handle_and_returns_resolved_id(): void
    {
        $mockChannels = Mockery::mock(Channels::class);
        $mockChannels->shouldReceive('listChannels')
            ->with('snippet', ['forHandle' => '@some_channel'])
            ->andReturn($this->channelListResponse('UC_resolved', 'Some Channel', '@some_channel'));
        $this->mockYouTube->channels = $mockChannels;

        $result = $this->service->findChannel('handle', '@some_channel');

        $this->assertSame('UC_resolved', $result['channel_id']);
        $this->assertSame('@some_channel', $result['handle']);
        $this->assertSame('Some Channel', $result['name']);
        $this->assertNull($result['thumbnail_url']);
    }

    public function test_find_channel_by_id_uses_id_and_returns_handle_from_custom_url(): void
    {
        $mockChannels = Mockery::mock(Channels::class);
        $mockChannels->shouldReceive('listChannels')
            ->with('snippet', ['id' => 'UC_test123'])
            ->andReturn($this->channelListResponse('UC_test123', 'Test Channel', '@testchannel'));
        $this->mockYouTube->channels = $mockChannels;

        $result = $this->service->findChannel('id', 'UC_test123');

        $this->assertSame('UC_test123', $result['channel_id']);
        $this->assertSame('@testchannel', $result['handle']);
    }

    public function test_find_channel_throws_when_nothing_matches(): void
    {
        $response = new ChannelListResponse();
        $response->setItems([]);
        $mockChannels = Mockery::mock(Channels::class);
        $mockChannels->shouldReceive('listChannels')->andReturn($response);
        $this->mockYouTube->channels = $mockChannels;

        $this->expectException(\RuntimeException::class);

        $this->service->findChannel('handle', '@nobody');
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

    public function test_get_channel_info_returns_null_thumbnail_when_missing(): void
    {
        $snippet = new ChannelSnippet();
        $snippet->setTitle('Test Channel');
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
        $this->assertNull($result['thumbnail_url']);
    }

    public function test_list_recent_upload_ids_reads_the_uploads_playlist(): void
    {
        $items = [];
        foreach (['vidA', 'vidB'] as $id) {
            $resourceId = new ResourceId();
            $resourceId->setVideoId($id);
            $snippet = new PlaylistItemSnippet();
            $snippet->setResourceId($resourceId);
            $item = new PlaylistItem();
            $item->setSnippet($snippet);
            $items[] = $item;
        }
        $response = new PlaylistItemListResponse();
        $response->setItems($items);

        $mockPlaylistItems = Mockery::mock(PlaylistItems::class);
        $mockPlaylistItems->shouldReceive('listPlaylistItems')
            ->with('snippet', ['playlistId' => 'UU_test123', 'maxResults' => 50])
            ->andReturn($response);
        $this->mockYouTube->playlistItems = $mockPlaylistItems;

        $result = $this->service->listRecentUploadIds('UC_test123');

        $this->assertSame(['vidA', 'vidB'], $result);
    }

    public function test_list_recent_upload_ids_returns_empty_for_channel_without_uploads(): void
    {
        $response = new PlaylistItemListResponse();
        $response->setItems([]);

        $mockPlaylistItems = Mockery::mock(PlaylistItems::class);
        $mockPlaylistItems->shouldReceive('listPlaylistItems')->andReturn($response);
        $this->mockYouTube->playlistItems = $mockPlaylistItems;

        $this->assertSame([], $this->service->listRecentUploadIds('UC_test123'));
    }

    public function test_list_members_only_upload_ids_reads_the_uumo_playlist(): void
    {
        $resourceId = new ResourceId();
        $resourceId->setVideoId('memVid');
        $snippet = new PlaylistItemSnippet();
        $snippet->setResourceId($resourceId);
        $item = new PlaylistItem();
        $item->setSnippet($snippet);
        $response = new PlaylistItemListResponse();
        $response->setItems([$item]);

        $mockPlaylistItems = Mockery::mock(PlaylistItems::class);
        $mockPlaylistItems->shouldReceive('listPlaylistItems')
            ->with('snippet', ['playlistId' => 'UUMO_test123', 'maxResults' => 50])
            ->andReturn($response);
        $this->mockYouTube->playlistItems = $mockPlaylistItems;

        $this->assertSame(['memVid'], $this->service->listMembersOnlyUploadIds('UC_test123'));
    }

    public function test_list_members_only_upload_ids_is_empty_when_channel_has_no_membership_playlist(): void
    {
        // YouTube answers 404 playlistNotFound for channels without a membership programme.
        $mockPlaylistItems = Mockery::mock(PlaylistItems::class);
        $mockPlaylistItems->shouldReceive('listPlaylistItems')
            ->andThrow(new \Google\Service\Exception('The playlist identified with the request\'s playlistId parameter cannot be found.', 404));
        $this->mockYouTube->playlistItems = $mockPlaylistItems;

        $this->assertSame([], $this->service->listMembersOnlyUploadIds('UC_test123'));
    }

    public function test_list_members_only_upload_ids_rethrows_other_errors(): void
    {
        $mockPlaylistItems = Mockery::mock(PlaylistItems::class);
        $mockPlaylistItems->shouldReceive('listPlaylistItems')
            ->andThrow(new \Google\Service\Exception('quotaExceeded', 403));
        $this->mockYouTube->playlistItems = $mockPlaylistItems;

        $this->expectException(\Google\Service\Exception::class);

        $this->service->listMembersOnlyUploadIds('UC_test123');
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
        $this->assertArrayHasKey('thumbnail_url', $result[0]);
        $this->assertNull($result[0]['thumbnail_url']);
    }

    public function test_get_video_details_includes_video_thumbnail(): void
    {
        $thumb = new Thumbnail();
        $thumb->setUrl('https://example.com/video.jpg');
        $thumbs = new ThumbnailDetails();
        $thumbs->setDefault($thumb);
        $snippet = new VideoSnippet();
        $snippet->setTitle('Ended Stream');
        $snippet->setThumbnails($thumbs);
        $details = new VideoLiveStreamingDetails();
        $details->setScheduledStartTime('2026-09-10T19:00:00Z');
        $details->setActualStartTime('2026-09-10T19:01:00Z');
        $details->setActualEndTime('2026-09-10T20:30:00Z');
        $video = new Video();
        $video->setId('vid_ended');
        $video->setSnippet($snippet);
        $video->setLiveStreamingDetails($details);
        $response = new VideoListResponse();
        $response->setItems([$video]);

        $mockVideos = Mockery::mock(Videos::class);
        $mockVideos->shouldReceive('listVideos')->andReturn($response);
        $this->mockYouTube->videos = $mockVideos;

        $result = $this->service->getVideoDetails(['vid_ended']);

        $this->assertEquals('https://example.com/video.jpg', $result[0]['thumbnail_url']);
        $this->assertEquals('completed', $result[0]['status']);
        $this->assertTrue($result[0]['is_broadcast']);
    }

    public function test_get_video_details_uses_actual_start_when_a_live_stream_was_never_scheduled(): void
    {
        // A stream started on the spot (no reservation frame) has liveStreamingDetails
        // but no scheduledStartTime.
        $snippet = new VideoSnippet();
        $snippet->setTitle('Guerrilla stream');
        $details = new VideoLiveStreamingDetails();
        $details->setActualStartTime('2026-09-17T10:05:16Z');
        $video = new Video();
        $video->setId('vid_live');
        $video->setSnippet($snippet);
        $video->setLiveStreamingDetails($details);
        $response = new VideoListResponse();
        $response->setItems([$video]);

        $mockVideos = Mockery::mock(Videos::class);
        $mockVideos->shouldReceive('listVideos')->andReturn($response);
        $this->mockYouTube->videos = $mockVideos;

        $result = $this->service->getVideoDetails(['vid_live']);

        $this->assertTrue($result[0]['is_broadcast']);
        $this->assertEquals('live', $result[0]['status']);
        $this->assertEquals('2026-09-17T10:05:16Z', $result[0]['scheduled_at']);
        $this->assertEquals('2026-09-17T10:05:16Z', $result[0]['actual_start_at']);
    }

    public function test_get_video_details_marks_plain_uploads_as_non_broadcasts(): void
    {
        $snippet = new VideoSnippet();
        $snippet->setTitle('Just a short');
        $video = new Video();
        $video->setId('vid_short');
        $video->setSnippet($snippet);
        $response = new VideoListResponse();
        $response->setItems([$video]);

        $mockVideos = Mockery::mock(Videos::class);
        $mockVideos->shouldReceive('listVideos')->andReturn($response);
        $this->mockYouTube->videos = $mockVideos;

        $result = $this->service->getVideoDetails(['vid_short']);

        $this->assertFalse($result[0]['is_broadcast']);
        $this->assertNull($result[0]['scheduled_at']);
    }
}

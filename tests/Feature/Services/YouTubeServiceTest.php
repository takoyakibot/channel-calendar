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

    public function test_search_streams_returns_null_thumbnail_when_missing(): void
    {
        $resourceId = new ResourceId();
        $resourceId->setVideoId('vid123');
        $snippet = new SearchResultSnippet();
        $snippet->setTitle('Test Stream');
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
        $this->assertNull($result[0]['thumbnail_url']);
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StreamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $start = Carbon::parse($request->start)->startOfDay();
        $end = Carbon::parse($request->end)->endOfDay();

        $streams = Stream::with('channel')
            ->whereHas('channel', fn ($q) => $q->where('is_active', true))
            ->whereBetween('scheduled_at', [$start, $end])
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
                'channel_id' => $stream->channel_id,
                'channel_name' => $stream->channel->name,
                'thumbnail_url' => $stream->thumbnail_url,
                'status' => $stream->status,
            ],
        ]);

        return response()->json($events);
    }
}

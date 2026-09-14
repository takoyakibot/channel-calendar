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

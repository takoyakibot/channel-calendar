<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /** Every tag, for pickers. */
    public function index(): JsonResponse
    {
        return response()->json(
            Tag::orderBy('kind')->orderBy('name')->get()->map(fn (Tag $t) => $t->toArrayForApi())->values()
        );
    }
}

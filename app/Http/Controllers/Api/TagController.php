<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /** Every tag, for pickers. Aliases come along so the picker can match spelling variants. */
    public function index(): JsonResponse
    {
        return response()->json(
            Tag::with('aliases')->orderBy('kind')->orderBy('name')->get()
                ->map(fn (Tag $t) => $t->toArrayForApi() + ['aliases' => $t->aliases->pluck('alias')->values()->all()])
                ->values()
        );
    }
}

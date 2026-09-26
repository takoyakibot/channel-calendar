<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A bracket term seen in titles that no tag, alias or ignore rule covers yet. */
class UnmatchedTerm extends Model
{
    protected $fillable = ['term', 'display', 'count', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A normalised spelling that resolves to a tag. */
class TagAlias extends Model
{
    protected $fillable = ['tag_id', 'alias', 'created_by_user_id'];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}

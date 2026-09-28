<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelAlias extends Model
{
    protected $fillable = ['channel_id', 'alias'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}

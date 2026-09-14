<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    use HasFactory;

    public const RESERVED_SLUGS = [
        'admin', 'api', 'login', 'logout', 'dashboard', 'profile', 'register',
        'forgot-password', 'reset-password', 'verify-email', 'email',
        'confirm-password', 'up', 'storage', 'build',
    ];

    protected $fillable = ['name', 'slug'];

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_group');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

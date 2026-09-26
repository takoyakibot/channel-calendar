<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A bracket term marked as noise so it is never suggested for classification. */
class IgnoredTerm extends Model
{
    protected $fillable = ['term', 'display', 'created_by_user_id'];
}

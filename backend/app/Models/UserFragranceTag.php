<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFragranceTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_fragrance_id',
        'tag_name',
    ];

    public function userFragrance(): BelongsTo
    {
        return $this->belongsTo(UserFragrance::class);
    }

    public function scopeByTag($query, string $tagName)
    {
        return $query->where('tag_name', $tagName);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRatingHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_fragrance_id',
        'rating',
        'comments',
        'rated_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'rated_at' => 'datetime',
    ];

    public function userFragrance(): BelongsTo
    {
        return $this->belongsTo(UserFragrance::class);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('rated_at', 'desc');
    }

    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }
}

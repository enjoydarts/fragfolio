<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WearingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_fragrance_id',
        'worn_at',
        'temperature',
        'weather',
        'location',
        'occasion',
        'sprays_count',
        'performance_rating',
        'comments',
    ];

    protected $casts = [
        'worn_at' => 'datetime',
        'temperature' => 'integer',
        'sprays_count' => 'integer',
        'performance_rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userFragrance(): BelongsTo
    {
        return $this->belongsTo(UserFragrance::class);
    }

    public function reactionLogs(): HasMany
    {
        return $this->hasMany(ReactionLog::class);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('worn_at', [$startDate, $endDate]);
    }

    public function scopeByWeather($query, string $weather)
    {
        return $query->where('weather', $weather);
    }
}

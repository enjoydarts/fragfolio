<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ja',
        'name_en',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function fragrances(): BelongsToMany
    {
        return $this->belongsToMany(
            Fragrance::class,
            'fragrance_season_mappings',
            'season_id',
            'fragrance_id'
        )->withPivot('rating')->withTimestamps();
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ja' ? $this->name_ja : $this->name_en;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

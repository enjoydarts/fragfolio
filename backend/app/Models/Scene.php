<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Scene extends Model
{
    use HasFactory;

    /**
     * Model properties from database
     */
    public ?string $name_en = null;
    public ?string $name_ja = null;
    public ?string $description_en = null;
    public ?string $description_ja = null;

    protected $fillable = [
        'name_ja',
        'name_en',
        'description_ja',
        'description_en',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function fragrances(): BelongsToMany
    {
        return $this->belongsToMany(
            Fragrance::class,
            'fragrance_scene_mappings',
            'scene_id',
            'fragrance_id'
        )->withPivot('rating')->withTimestamps();
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ja' ? $this->name_ja : $this->name_en;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ja' ? $this->description_ja : $this->description_en;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

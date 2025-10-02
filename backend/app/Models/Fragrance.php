<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $brand_id
 * @property string $name_ja
 * @property string $name_en
 * @property string|null $description_ja
 * @property string|null $description_en
 * @property int|null $concentration_type_id
 * @property int|null $release_year
 * @property string|null $image
 * @property bool $is_discontinued
 * @property bool $is_active
 */
class Fragrance extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'name_ja',
        'name_en',
        'description_ja',
        'description_en',
        'concentration_type_id',
        'release_year',
        'image',
        'is_discontinued',
        'is_active',
    ];

    protected $casts = [
        'release_year' => 'integer',
        'is_discontinued' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function concentrationType(): BelongsTo
    {
        return $this->belongsTo(ConcentrationType::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            FragranceCategory::class,
            'fragrance_category_mappings',
            'fragrance_id',
            'category_id'
        )->withTimestamps();
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(
            FragranceNote::class,
            'fragrance_note_mappings',
            'fragrance_id',
            'note_id'
        )->withPivot('note_position', 'intensity')->withTimestamps();
    }

    public function scenes(): BelongsToMany
    {
        return $this->belongsToMany(
            Scene::class,
            'fragrance_scene_mappings',
            'fragrance_id',
            'scene_id'
        )->withPivot('rating')->withTimestamps();
    }

    public function seasons(): BelongsToMany
    {
        return $this->belongsToMany(
            Season::class,
            'fragrance_season_mappings',
            'fragrance_id',
            'season_id'
        )->withPivot('rating')->withTimestamps();
    }

    public function userFragrances(): HasMany
    {
        return $this->hasMany(UserFragrance::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ja' ? $this->name_ja : $this->name_en;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ja' ? $this->description_ja : $this->description_en;
    }

    public function getTopNotesAttribute()
    {
        return $this->notes()->wherePivot('note_position', 'top')->get();
    }

    public function getMiddleNotesAttribute()
    {
        return $this->notes()->wherePivot('note_position', 'middle')->get();
    }

    public function getBaseNotesAttribute()
    {
        return $this->notes()->wherePivot('note_position', 'base')->get();
    }

    public function getSingleNotesAttribute()
    {
        return $this->notes()->wherePivot('note_position', 'single')->get();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_discontinued', false);
    }
}

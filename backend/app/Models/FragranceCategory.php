<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FragranceCategory extends Model
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
        'parent_id',
        'sort_order',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FragranceCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FragranceCategory::class, 'parent_id');
    }

    public function fragrances(): BelongsToMany
    {
        return $this->belongsToMany(
            Fragrance::class,
            'fragrance_category_mappings',
            'category_id',
            'fragrance_id'
        )->withTimestamps();
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ja' ? $this->name_ja : $this->name_en;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ja' ? $this->description_ja : $this->description_en;
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

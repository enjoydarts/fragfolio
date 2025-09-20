<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
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
        'country',
        'founded_year',
        'website',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'founded_year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function fragrances(): HasMany
    {
        return $this->hasMany(Fragrance::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ja' ? $this->name_ja : $this->name_en;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ja' ? $this->description_ja : $this->description_en;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConcentrationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ja',
        'name_en',
        'description_ja',
        'description_en',
        'oil_concentration_min',
        'oil_concentration_max',
        'sort_order',
    ];

    protected $casts = [
        'oil_concentration_min' => 'decimal:2',
        'oil_concentration_max' => 'decimal:2',
        'sort_order' => 'integer',
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

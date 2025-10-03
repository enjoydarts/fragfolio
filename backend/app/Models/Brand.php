<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_ja
 * @property string $name_en
 * @property string|null $description_ja
 * @property string|null $description_en
 * @property string|null $country
 * @property int|null $founded_year
 * @property string|null $website
 * @property string|null $logo
 * @property bool $is_active
 */
class Brand extends Model
{
    use HasFactory;

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

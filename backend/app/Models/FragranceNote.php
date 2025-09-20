<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FragranceNote extends Model
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
        'category',
    ];

    public function fragrances(): BelongsToMany
    {
        return $this->belongsToMany(
            Fragrance::class,
            'fragrance_note_mappings',
            'note_id',
            'fragrance_id'
        )->withPivot('note_position', 'intensity')->withTimestamps();
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ja' ? $this->name_ja : $this->name_en;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ja' ? $this->description_ja : $this->description_en;
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}

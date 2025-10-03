<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $fragrance_id
 * @property string|null $purchase_date
 * @property float|null $volume_ml
 * @property float|null $purchase_price
 * @property string|null $purchase_place
 * @property float|null $current_volume_ml
 * @property string $possession_type
 * @property int|null $duration_hours
 * @property string|null $projection
 * @property int|null $user_rating
 * @property string|null $comments
 * @property string|null $bottle_image
 * @property string|null $box_image
 * @property bool $is_active
 */
class UserFragrance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fragrance_id',
        'purchase_date',
        'volume_ml',
        'purchase_price',
        'purchase_place',
        'current_volume_ml',
        'possession_type',
        'duration_hours',
        'projection',
        'user_rating',
        'comments',
        'bottle_image',
        'box_image',
        'is_active',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'volume_ml' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'current_volume_ml' => 'decimal:2',
        'duration_hours' => 'integer',
        'user_rating' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fragrance(): BelongsTo
    {
        return $this->belongsTo(Fragrance::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(UserFragranceTag::class);
    }

    public function wearingLogs(): HasMany
    {
        return $this->hasMany(WearingLog::class);
    }

    public function ratingHistory(): HasMany
    {
        return $this->hasMany(UserRatingHistory::class);
    }

    public function getRemainingPercentageAttribute(): float
    {
        if (! $this->volume_ml || $this->volume_ml <= 0) {
            return 0;
        }

        return ($this->current_volume_ml / $this->volume_ml) * 100;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPossessionType($query, string $type)
    {
        return $query->where('possession_type', $type);
    }
}

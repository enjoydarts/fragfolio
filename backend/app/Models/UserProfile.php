<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    /**
     * Model properties from database
     */
    protected $fillable = [
        'user_id',
        'display_name',
        'avatar',
        'bio',
        'date_of_birth',
        'gender',
        'country',
        'language',
        'timezone',
        'notification_preferences',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'notification_preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }
}

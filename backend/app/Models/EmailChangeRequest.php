<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $new_email
 * @property string $token
 * @property bool $verified
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class EmailChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'new_email',
        'token',
        'verified',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function canComplete(): bool
    {
        return $this->verified && ! $this->isExpired();
    }
}

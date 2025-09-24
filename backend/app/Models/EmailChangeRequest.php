<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailChangeRequest extends Model
{
    use HasFactory;

    public string $new_email;

    public string $token;

    public bool $verified;

    public string $expires_at;

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

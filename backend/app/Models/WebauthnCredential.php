<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebauthnCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'counter',
        'device_name',
        'last_used_at',
    ];

    protected $casts = [
        'counter' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateCounter(int $newCounter): void
    {
        if ($newCounter > $this->counter) {
            $this->update([
                'counter' => $newCounter,
                'last_used_at' => now(),
            ]);
        }
    }

    public function scopeByCredentialId($query, string $credentialId)
    {
        return $query->where('credential_id', $credentialId);
    }
}

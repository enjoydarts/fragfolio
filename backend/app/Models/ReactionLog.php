<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReactionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'wearing_log_id',
        'reactor_type',
        'reaction_type',
        'comments',
    ];

    public function wearingLog(): BelongsTo
    {
        return $this->belongsTo(WearingLog::class);
    }

    public function scopePositive($query)
    {
        return $query->where('reaction_type', 'positive');
    }

    public function scopeNegative($query)
    {
        return $query->where('reaction_type', 'negative');
    }

    public function scopeByReactorType($query, string $type)
    {
        return $query->where('reactor_type', $type);
    }

    public function scopeByReactionType($query, string $type)
    {
        return $query->where('reaction_type', $type);
    }
}

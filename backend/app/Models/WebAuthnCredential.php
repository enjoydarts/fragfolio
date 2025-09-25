<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laragear\WebAuthn\Models\WebAuthnCredential as BaseWebAuthnCredential;

class WebAuthnCredential extends BaseWebAuthnCredential
{
    use HasFactory;

    protected $fillable = [
        'alias',
        'counter',
        'rp_id',
        'origin',
        'transports',
        'aaguid',
        'public_key',
        'attestation_format',
        'certificates',
        'disabled_at',
    ];
}

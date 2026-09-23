<?php

namespace App\Models;

use Database\Factories\QrTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrToken extends Model
{
    /** @use HasFactory<QrTokenFactory> */
    use HasFactory;

    protected $fillable = ['admission_ticket_id', 'token', 'revoked_at'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function admissionTicket(): BelongsTo
    {
        return $this->belongsTo(AdmissionTicket::class);
    }

    /**
     * 48 bytes of CSRNG output, base62-ish via Str::random (which itself
     * draws from random_bytes) - unguessable and never derived from the
     * ticket's id/number (section 13/53: the QR must not be a source of
     * truth a client can compute or predict).
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved, always-up-to-date customer list: nothing is stored about who
 * belongs to it, only the rules. Membership is recomputed from the rules
 * every time (see App\Domain\Crm\GuestSegmentQuery).
 */
class GuestSegment extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'match_type', 'conditions'];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unlike this warehouse's other per-day metric tables, `date` here is the
 * sync window's end date (a snapshot), not a real daily breakdown - see
 * SyncSearchConsoleDataJob::syncSearchAppearance() for why: Google's API
 * rejects the searchAppearance dimension combined with date.
 */
class SeoSearchAppearanceMetric extends Model
{
    use HasFactory;

    protected $fillable = ['seo_project_id', 'date', 'search_appearance', 'clicks', 'impressions', 'ctr', 'position'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'ctr' => 'decimal:4',
            'position' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }
}

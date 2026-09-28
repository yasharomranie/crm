<?php

namespace App\Models;

use Database\Factories\SeoPeriodicReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rendered, print-ready snapshot report for one project/period, built by
 * App\Domain\Seo\PeriodicReportService - stored as HTML (same house
 * convention as resources/views/reports/print.blade.php: no PDF library,
 * the viewer opens it and uses their browser's own print-to-PDF) so the
 * numbers stay exactly what they were that period even as the warehouse
 * keeps changing.
 */
class SeoPeriodicReport extends Model
{
    /** @use HasFactory<SeoPeriodicReportFactory> */
    use HasFactory;

    protected $fillable = ['seo_project_id', 'period_start', 'period_end', 'html', 'generated_at'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }
}

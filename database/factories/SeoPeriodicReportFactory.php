<?php

namespace Database\Factories;

use App\Models\SeoPeriodicReport;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoPeriodicReport>
 */
class SeoPeriodicReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'seo_project_id' => SeoProject::factory(),
            'period_start' => $start->toDateString(),
            'period_end' => $start->clone()->endOfMonth()->toDateString(),
            'html' => '<html><body>گزارش نمونه</body></html>',
            'generated_at' => now(),
        ];
    }
}

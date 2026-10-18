<?php

namespace Database\Factories;

use App\Models\AiScheduledReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiScheduledReport>
 */
class AiScheduledReportFactory extends Factory
{
    protected $model = AiScheduledReport::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prompt' => 'امروز چه کسانی هنوز ورود نزده‌اند؟',
            'run_at_time' => '08:00',
            'is_active' => true,
        ];
    }
}

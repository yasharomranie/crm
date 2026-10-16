<?php

namespace Database\Factories;

use App\Enums\DealStage;
use App\Models\DealAutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealAutomationRule>
 */
class DealAutomationRuleFactory extends Factory
{
    protected $model = DealAutomationRule::class;

    public function definition(): array
    {
        return [
            'name' => 'پیگیری پس از ورود به مرحله',
            'trigger_stage' => DealStage::NewLead,
            'task_title' => 'تماس با مشتری برای {deal}',
            'due_after_hours' => 24,
            'is_active' => true,
        ];
    }
}

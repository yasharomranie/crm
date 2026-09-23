<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReminder>
 */
class EventReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'days_before' => fake()->numberBetween(0, 3),
            'remind_time' => '09:00:00',
            'notify_push' => true,
            'notify_sms' => false,
            'notify_email' => false,
            'sent_at' => null,
        ];
    }
}

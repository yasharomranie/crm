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
            'minutes_before' => fake()->randomElement([60, 60 * 24, 60 * 24 * 7]),
            'notify_push' => true,
            'notify_sms' => false,
            'notify_email' => false,
            'sent_at' => null,
        ];
    }
}

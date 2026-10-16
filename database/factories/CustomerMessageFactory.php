<?php

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\CustomerMessage;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerMessage>
 */
class CustomerMessageFactory extends Factory
{
    protected $model = CustomerMessage::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'channel' => MessageChannel::Email,
            'direction' => MessageDirection::Outbound,
            'subject' => 'موضوع آزمایشی',
            'body' => 'متن پیام آزمایشی',
            'status' => MessageDeliveryStatus::Sent,
        ];
    }
}

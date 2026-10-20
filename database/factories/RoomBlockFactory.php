<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBlock>
 */
class RoomBlockFactory extends Factory
{
    protected $model = RoomBlock::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(8)->toDateString(),
            'reason' => 'تعمیر سرویس بهداشتی',
        ];
    }
}

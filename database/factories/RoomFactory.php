<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'department_id' => Department::factory(),
            'name' => fake()->unique()->bothify('اتاق ###'),
            'status' => RoomStatus::Available,
            'is_active' => true,
        ];
    }
}

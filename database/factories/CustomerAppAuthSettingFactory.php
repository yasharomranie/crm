<?php

namespace Database\Factories;

use App\Models\CustomerAppAuthSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAppAuthSetting>
 */
class CustomerAppAuthSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'otp_enabled' => true,
            'password_enabled' => true,
            'google_enabled' => true,
        ];
    }
}

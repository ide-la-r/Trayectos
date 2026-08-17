<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => 'Cuadrilla '.fake()->city(),
            'currency' => 'EUR',
            'driver_pays_own_share' => true,
            'invite_code' => strtoupper(Str::random(8)),
            'created_by' => User::factory(),
        ];
    }

    public function driverRidesFree(): static
    {
        return $this->state(fn () => ['driver_pays_own_share' => false]);
    }
}

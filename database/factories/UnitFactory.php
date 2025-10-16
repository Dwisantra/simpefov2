<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'instansi' => fake()->randomElement(['wiradadi', 'raffa']),
            'is_active' => true,
        ];
    }
}

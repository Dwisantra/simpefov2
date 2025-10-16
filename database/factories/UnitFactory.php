<?php

namespace Database\Factories;

use App\Enums\ManagerCategory;
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
        $managerCategories = array_map(
            fn (ManagerCategory $category) => $category->value,
            ManagerCategory::cases()
        );

        return [
            'name' => fake()->unique()->company(),
            'instansi' => fake()->randomElement(['wiradadi', 'raffa']),
            'is_active' => true,
            'manager_category_id' => fake()->randomElement($managerCategories),
        ];
    }
}

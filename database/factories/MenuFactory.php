<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'parent_id' => 0,
            'type' => Menu::TYPE_DIRECTORY,
            'name' => $this->faker->unique()->word(),
            'path' => $this->faker->word(),
            'locale' => 'menu.' . $this->faker->word(),
            'sort' => 0,
            'is_active' => true,
        ];
    }
}

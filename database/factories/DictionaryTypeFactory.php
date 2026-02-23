<?php

namespace Database\Factories;

use App\Models\DictionaryType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DictionaryTypeFactory extends Factory
{
    protected $model = DictionaryType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'code' => $this->faker->unique()->lexify('????_????'),
            'description' => $this->faker->sentence(),
            'sort' => 0,
            'is_active' => true,
        ];
    }
}

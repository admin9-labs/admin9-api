<?php

namespace Database\Factories;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DictionaryItemFactory extends Factory
{
    protected $model = DictionaryItem::class;

    public function definition(): array
    {
        return [
            'dictionary_type_id' => DictionaryType::factory(),
            'label' => $this->faker->word(),
            'value' => $this->faker->unique()->slug(1),
            'sort' => 0,
            'is_active' => true,
        ];
    }
}

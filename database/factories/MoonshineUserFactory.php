<?php

namespace Database\Factories;

use MoonShine\Laravel\Database\Factories\MoonshineUserFactory as Factory;
use Override;

class MoonshineUserFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $definition = parent::definition();
        $definition['avatar'] = $this->faker->imageUrl();

        return $definition;
    }
}

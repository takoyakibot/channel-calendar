<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_id' => 'UC' . $this->faker->regexify('[A-Za-z0-9]{22}'),
            'name' => $this->faker->name(),
            'thumbnail_url' => $this->faker->imageUrl(),
            'color' => '#' . ltrim($this->faker->hexColor(), '#'),
            'is_active' => true,
        ];
    }
}

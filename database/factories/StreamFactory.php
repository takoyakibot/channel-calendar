<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class StreamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'video_id' => $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'thumbnail_url' => $this->faker->imageUrl(),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'status' => 'upcoming',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'student_number' => $this->faker->unique()->numerify('2024-#####'),
            'email' => $this->faker->unique()->safeEmail(),
            'section' => 'BSIT-1A',
            'qr_token' => Str::random(32),
            'schedule' => [],
        ];
    }
}

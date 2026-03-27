<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $icons = ['LayoutGrid', 'BookOpen', 'Calculator', 'FlaskConical', 'Users', 'Calendar', 'ChartBar', 'Database', 'Activity', 'Star'];
        $colors = ['emerald', 'amber', 'indigo', 'rose', 'blue', 'zinc', 'violet', 'cyan'];

        return [
            'name' => $this->faker->unique()->randomElement([
                'Information Technology', 'Advanced Mathematics', 'Physics 101', 'General Science',
                'Computer Science', 'Data Structures', 'Web Development', 'Mobile Programming',
                'Database Management', 'Software Engineering',
            ]),
            'icon' => $this->faker->randomElement($icons),
            'color' => $this->faker->randomElement($colors),
            'description' => $this->faker->sentence(),
            'schedule' => [
                ['day' => 'Mon', 'start' => '08:00', 'end' => '10:00'],
                ['day' => 'Wed', 'start' => '08:00', 'end' => '10:00'],
            ],
        ];
    }
}

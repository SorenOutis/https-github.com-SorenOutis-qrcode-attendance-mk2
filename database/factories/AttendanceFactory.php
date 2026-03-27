<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'scanned_at' => now(),
            'status' => 'present',
            'is_manual' => false,
            'slot_start' => now()->startOfHour(),
            'slot_end' => now()->endOfHour(),
        ];
    }
}

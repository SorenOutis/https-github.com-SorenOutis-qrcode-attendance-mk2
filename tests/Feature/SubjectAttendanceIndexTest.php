<?php

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

test('authenticated users can view complete subject attendance cards', function () {
    $user = User::factory()->create();

    $algorithms = Subject::factory()->create([
        'name' => 'Algorithms',
        'icon' => 'FlaskConical',
        'color' => 'blue',
    ]);

    $networking = Subject::factory()->create([
        'name' => 'Networking',
        'icon' => 'Database',
        'color' => 'emerald',
    ]);

    $student = Student::factory()->create([
        'schedule' => [
            ['day' => 'Monday', 'subject_id' => $algorithms->id, 'start' => '08:00', 'end' => '09:00'],
            ['day' => 'Tuesday', 'subject_id' => $networking->id, 'start' => '10:00', 'end' => '11:00'],
        ],
    ]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $algorithms->id,
        'status' => 'Present',
        'scanned_at' => CarbonImmutable::now()->subDay(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $algorithms->id,
        'status' => 'Late',
        'scanned_at' => CarbonImmutable::now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $networking->id,
        'status' => 'Absent',
        'scanned_at' => CarbonImmutable::now(),
    ]);

    $this->actingAs($user)
        ->get('/subject-attendance')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SubjectAttendance/Index')
            ->has('subjects', 2)
            ->where('subjects.0.name', 'Algorithms')
            ->where('subjects.0.icon', 'FlaskConical')
            ->where('subjects.0.color', 'blue')
            ->where('subjects.0.enrolled', 1)
            ->where('subjects.0.present', 1)
            ->where('subjects.0.late', 1)
            ->where('subjects.0.absent', 0)
            ->where('subjects.0.attendance_rate', 100)
            ->has('subjects.0.daily', 2)
            ->where('subjects.1.name', 'Networking')
            ->where('subjects.1.icon', 'Database')
            ->where('subjects.1.color', 'emerald')
            ->where('subjects.1.enrolled', 1)
            ->where('subjects.1.present', 0)
            ->where('subjects.1.late', 0)
            ->where('subjects.1.absent', 1)
            ->where('subjects.1.attendance_rate', 0)
            ->has('subjects.1.daily', 1)
        );
});

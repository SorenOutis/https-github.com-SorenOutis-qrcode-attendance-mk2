<?php

use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('subject attendance leaderboard is paginated at 25 students per page', function () {
    $user = User::factory()->create();

    $subject = Subject::factory()->create([
        'name' => 'Database Management',
    ]);

    Student::factory()->count(30)->create([
        'schedule' => [
            ['day' => 'Monday', 'subject_id' => $subject->id, 'start' => '08:00', 'end' => '09:00'],
        ],
    ]);

    $this->actingAs($user)
        ->get("/subject-attendance/{$subject->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SubjectAttendance/Show')
            ->has('students.data', 25)
            ->where('students.current_page', 1)
            ->where('students.last_page', 2)
            ->where('students.per_page', 25)
            ->where('students.total', 30)
        );
});

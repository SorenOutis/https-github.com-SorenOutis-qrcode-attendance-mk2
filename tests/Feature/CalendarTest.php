<?php

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calendar page renders successfully with data', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->create();
    $student = Student::factory()->create();

    Attendance::factory()->create([
        'subject_id' => $subject->id,
        'student_id' => $student->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('calendar.index'));

    $response->assertStatus(200);
});

test('calendar page renders successfully even with soft-deleted student', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->create();
    $student = Student::factory()->create();

    Attendance::factory()->create([
        'subject_id' => $subject->id,
        'student_id' => $student->id,
    ]);

    $student->delete(); // Soft delete

    $response = $this->actingAs($user)
        ->get(route('calendar.index'));

    $response->assertStatus(200);
    $response->assertSee('Unknown Student');
});

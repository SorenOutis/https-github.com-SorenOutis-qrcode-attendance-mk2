<?php

use App\Models\Attendance;
use App\Models\Rating;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('welcome page displays correct stats and ratings', function () {
    $user = User::factory()->create();
    $subject = Subject::create(['name' => 'Math', 'code' => 'M101', 'teacher_id' => $user->id, 'color' => '#000000']);
    $student = Student::create(['name' => 'John Doe', 'student_number' => '12345', 'qr_token' => 'abc']);

    for ($i = 0; $i < 5; $i++) {
        Attendance::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => 'Present',
            'scanned_at' => now(),
            'created_at' => today(),
        ]);
    }

    Rating::create(['name' => 'A', 'score' => 5, 'message' => 'Good']);
    Rating::create(['name' => 'B', 'score' => 3, 'message' => 'Ok']);

    $this->get('/')
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('stats', fn (AssertableInertia $page) => $page
                ->where('total_scans', 5)
                ->where('present_today', 5)
                ->where('average_rating', 4)
                ->where('total_ratings', 2)
            )
        );
});

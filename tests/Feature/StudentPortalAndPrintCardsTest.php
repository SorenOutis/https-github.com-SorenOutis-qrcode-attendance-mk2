<?php

use App\Models\Student;
use App\Models\User;

test('student portal is publicly accessible by token', function () {
    $student = Student::create([
        'name' => 'Portal Student',
        'student_number' => '2026-0001',
        'email' => 'portal@example.com',
        'section' => 'BSIT-1A',
        'qr_token' => 'portal-token',
        'schedule' => [
            ['day' => 'Monday', 'start' => '09:00', 'end' => '10:00'],
        ],
    ]);

    $response = $this->get(route('student.portal', ['token' => $student->qr_token]));
    $response->assertOk();
});

test('student portal returns 404 for invalid token', function () {
    $response = $this->get(route('student.portal', ['token' => 'nope']));
    $response->assertNotFound();
});

test('print cards page requires authentication', function () {
    $response = $this->get(route('students.print-cards'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit print cards page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('students.print-cards'));
    $response->assertOk();
});

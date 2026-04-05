<?php

use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;

test('scans at or after 15 minutes of schedule start are marked as Late', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $student = Student::create([
        'name' => 'Test Student',
        'student_number' => '2021-0001',
        'email' => 'test@example.com',
        'section' => 'BSIT-1A',
        'qr_token' => 'test-token',
        'schedule' => [
            [
                'day' => 'Monday',
                'start' => '13:00',
                'end' => '14:00',
            ],
        ],
    ]);

    // Monday, March 16, 2026.
    $baseDate = '2026-03-16';

    // 1:14 PM -> Present
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 13:14:59"));
    $response = $this->post(route('attendance.scan'), ['token' => 'test-token']);
    $response->assertJsonPath('attendance.status', 'Present');

    // Reset for next scan
    $student->attendances()->delete();

    // 1:15 PM -> Late (as per user request: "1:15 is considered late")
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 13:15:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'test-token']);
    $response->assertJsonPath('attendance.status', 'Late');

    // Reset for next scan
    $student->attendances()->delete();

    // 1:19 PM -> Late
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 13:19:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'test-token']);
    $response->assertJsonPath('attendance.status', 'Late');
});

test('handles multiple slots correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $subject1 = Subject::create(['name' => 'Math']);
    $subject2 = Subject::create(['name' => 'Science']);

    $student = Student::create([
        'name' => 'Multi Slot Student',
        'student_number' => '2021-0002',
        'email' => 'multi@example.com',
        'qr_token' => 'multi-token',
        'schedule' => [
            ['day' => 'Monday', 'start' => '09:00', 'end' => '10:00', 'subject_id' => $subject1->id],
            ['day' => 'Monday', 'start' => '13:00', 'end' => '14:00', 'subject_id' => $subject2->id],
        ],
    ]);

    $baseDate = '2026-03-16'; // Monday

    // 1. First slot check-in (9:05 AM) -> Present
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 09:05:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'multi-token']);
    $response->assertJsonPath('attendance.status', 'Present');
    $response->assertJsonPath('attendance.slot_start', '09:00');
    $response->assertJsonPath('attendance.subject.id', $subject1->id);

    // 2. Second scan at 9:10 AM -> Now counts for the 2nd slot (Early Check-in)
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 09:10:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'multi-token']);
    $response->assertJsonPath('attendance.status', 'Present');
    $response->assertJsonPath('attendance.slot_start', '13:00');
    $response->assertJsonPath('attendance.subject.id', $subject2->id);

    // 3. Final scan (2:05 PM) -> Time Out
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 14:05:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'multi-token']);
    $response->assertJsonPath('attendance.status', 'Time Out');

    // 5. Subsequent scan -> Error
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$baseDate} 15:05:00"));
    $response = $this->post(route('attendance.scan'), ['token' => 'multi-token']);
    $response->assertStatus(422);
    $response->assertJsonPath('message', 'You have already completed your attendance (Time Out) for today.');
});

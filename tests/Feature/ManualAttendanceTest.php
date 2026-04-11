<?php

use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->subject = Subject::factory()->create(['name' => 'Test Subject']);
});

test('can view manage attendance index page', function () {
    $this->actingAs($this->user)
        ->get('/manage-attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ManageAttendance/Index')
            ->has('subjects')
        );
});

test('can view manage attendance show page for a specific subject and date', function () {
    $date = CarbonImmutable::parse('next Monday')->toDateString();

    // Create a student enrolled in this subject on Monday
    Student::create([
        'name' => 'John Doe',
        'student_number' => '12345',
        'qr_token' => Str::uuid()->toString(),
        'schedule' => [
            [
                'day' => 'Monday',
                'start' => '09:00',
                'end' => '10:30',
                'subject_id' => $this->subject->id,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->get("/manage-attendance/{$this->subject->id}/{$date}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ManageAttendance/Show')
            ->has('subject')
            ->has('date')
            ->has('students', 1)
        );
});

test('can auto-save toggle a manual attendance record', function () {
    $date = CarbonImmutable::parse('next Monday')->toDateString();

    $student = Student::create([
        'name' => 'Jane Doe',
        'student_number' => '123456',
        'qr_token' => Str::uuid()->toString(),
        'schedule' => [
            [
                'day' => 'Monday',
                'start' => '09:00',
                'end' => '10:30',
                'subject_id' => $this->subject->id,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/manage-attendance/toggle', [
            'student_id' => $student->id,
            'subject_id' => $this->subject->id,
            'date' => $date,
            'status' => 'Late',
            'slot_start' => '09:00',
            'slot_end' => '10:30',
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('attendances', [
        'student_id' => $student->id,
        'subject_id' => $this->subject->id,
        'status' => 'Late',
        'is_manual' => true,
    ]);
});

<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualAttendanceController extends Controller
{
    public function index(): Response
    {
        $subjects = Subject::orderBy('name')->get(['id', 'name']);
        
        return Inertia::render('ManageAttendance/Index', [
            'subjects' => $subjects,
        ]);
    }

    public function show(Subject $subject, string $date): Response
    {
        // Parse the date to get the day of the week
        $parsedDate = CarbonImmutable::parse($date);
        $dayOfWeek = $parsedDate->format('l');

        // Get all students (we filter by subject in the collection to bypass SQLite JSON array bugs)
        $enrolledStudents = Student::query()
            ->orderBy('name')
            ->get(['id', 'name', 'student_number', 'schedule']);

        // Filter students who actually have this subject on the specified day
        // And extract their schedule slot for this specific subject and day
        $students = $enrolledStudents->map(function ($student) use ($subject, $dayOfWeek, $parsedDate) {
            $slots = collect($student->schedule ?? [])
                ->filter(fn ($s) => 
                    isset($s['subject_id'], $s['day']) && 
                    $s['subject_id'] == $subject->id && 
                    $s['day'] === $dayOfWeek
                )
                ->values();

            // If the student doesn't have a schedule for this subject on this day, we return null so we can filter them out
            if ($slots->isEmpty()) {
                return null;
            }

            // A student shouldn't have multiple slots for the same subject on the same day usually,
            // but we'll take the first one.
            $slot = $slots->first();

            // Find existing attendance for this student on this day for this subject
            $attendance = Attendance::query()
                ->where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->whereDate('scanned_at', $parsedDate->toDateString())
                ->first(['id', 'status', 'is_manual', 'remarks', 'scanned_at']);

            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'slot_start' => $slot['start'] ?? null,
                'slot_end' => $slot['end'] ?? null,
                'attendance' => $attendance,
            ];
        })->filter()->values();

        return Inertia::render('ManageAttendance/Show', [
            'subject' => ['id' => $subject->id, 'name' => $subject->name],
            'date' => $parsedDate->toDateString(),
            'students' => $students,
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:Present,Late,Absent,Excused'],
            'slot_start' => ['nullable', 'date_format:H:i'],
            'slot_end' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $parsedDate = CarbonImmutable::parse($validated['date']);

        // Check if an attendance record already exists for this date and subject
        $attendance = Attendance::query()
            ->where('student_id', $validated['student_id'])
            ->where('subject_id', $validated['subject_id'])
            ->whereDate('scanned_at', $parsedDate->toDateString())
            ->first();

        // If 'scanned_at' shouldn't be overridden if they actually scanned.
        // But since this is a manual override, perhaps we keep original `scanned_at` if it exists,
        // otherwise we just use the date.
        
        if ($attendance) {
            $attendance->update([
                'status' => $validated['status'],
                'is_manual' => true,
                'remarks' => $validated['remarks'] ?? $attendance->remarks,
            ]);
        } else {
            // Provide a base scanned_at matching the date they selected, maybe set to 00:00:00 or current time on that date
            $attendance = Attendance::create([
                'student_id' => $validated['student_id'],
                'subject_id' => $validated['subject_id'],
                'scanned_at' => $parsedDate->setTimeFrom(CarbonImmutable::now()), // keep current time but on that specific date
                'status' => $validated['status'],
                'is_manual' => true,
                'remarks' => $validated['remarks'] ?? null,
                'slot_start' => $validated['slot_start'] ?? null,
                'slot_end' => $validated['slot_end'] ?? null,
                // 'slot_index' could be added if needed, but we don't strictly require it here
            ]);
        }

        return response()->json([
            'success' => true,
            'attendance' => $attendance,
        ]);
    }
}

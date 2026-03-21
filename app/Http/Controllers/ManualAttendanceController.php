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
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualAttendanceController extends Controller
{
    public function index(): Response
    {
        $subjects = Subject::query()->select('id', 'name')->orderBy('name', 'asc')->get();

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
            ->select('id', 'name', 'student_number', 'schedule')
            ->orderBy('name', 'asc')
            ->get();

        // Filter students who actually have this subject on the specified day
        // And extract their schedule slot for this specific subject and day
        $students = $enrolledStudents->map(function ($student) use ($subject, $dayOfWeek, $parsedDate) {
            $allSlots = collect($student->schedule ?? []);
            
            // Check if they have THIS subject anywhere in their schedule
            $subjectSlots = $allSlots->filter(fn ($s) => isset($s['subject_id']) && $s['subject_id'] == $subject->id);

            if ($subjectSlots->isEmpty()) {
                return null;
            }

            // Find slot for today if it exists
            $todaySlot = $subjectSlots->firstWhere('day', $dayOfWeek);

            // Find existing attendance for this student on this day for this subject
            $attendance = Attendance::query()
                ->select('id', 'status', 'is_manual', 'remarks', 'scanned_at')
                ->where('student_id', '=', $student->id)
                ->where('subject_id', '=', $subject->id)
                ->whereDate('scanned_at', '=', $parsedDate->toDateString())
                ->first();

            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'slot_start' => $todaySlot['start'] ?? null,
                'slot_end' => $todaySlot['end'] ?? null,
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
            'status' => ['nullable', 'string', 'in:Present,Late,Absent,Excused'],
            'slot_start' => ['nullable', 'date_format:H:i'],
            'slot_end' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $parsedDate = CarbonImmutable::parse($validated['date']);

        // Check if an attendance record already exists for this date and subject
        $attendance = Attendance::query()
            ->where('student_id', '=', $validated['student_id'])
            ->where('subject_id', '=', $validated['subject_id'])
            ->whereDate('scanned_at', '=', $parsedDate->toDateString())
            ->first();

        // If status is null, we want to REMOVE the attendance record
        if (empty($validated['status'])) {
            if ($attendance) {
                $attendance->delete();
            }
            return response()->json([
                'success' => true,
                'attendance' => null,
                'removed' => true,
            ]);
        }

        if ($attendance) {
            $attendance->update([
                'status' => $validated['status'],
                'is_manual' => true,
                'remarks' => $validated['remarks'] ?? $attendance->remarks,
            ]);
        } else {
            // Provide a base scanned_at matching the date they selected
            $attendance = Attendance::create([
                'student_id' => $validated['student_id'],
                'subject_id' => $validated['subject_id'],
                'scanned_at' => $parsedDate->setTimeFrom(CarbonImmutable::now()),
                'status' => $validated['status'],
                'is_manual' => true,
                'remarks' => $validated['remarks'] ?? null,
                'slot_start' => $validated['slot_start'] ?? null,
                'slot_end' => $validated['slot_end'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'attendance' => $attendance,
        ]);
    }

    public function markAllAbsent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'students' => ['required', 'array'],
            'students.*.id' => ['required', 'exists:students,id'],
            'students.*.slot_start' => ['nullable', 'date_format:H:i'],
            'students.*.slot_end' => ['nullable', 'date_format:H:i'],
        ]);

        $subjectId = $validated['subject_id'];
        $parsedDate = CarbonImmutable::parse($validated['date']);
        $now = CarbonImmutable::now();

        $absentRecordsCreated = 0;

        foreach ($validated['students'] as $studentData) {
            $studentId = $studentData['id'];

            $exists = Attendance::query()
                ->where('student_id', '=', $studentId)
                ->where('subject_id', '=', $subjectId)
                ->whereDate('scanned_at', '=', $parsedDate->toDateString())
                ->exists();

            if (! $exists) {
                Attendance::create([
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'scanned_at' => $parsedDate->setTimeFrom($now),
                    'status' => 'Absent',
                    'is_manual' => true,
                    'slot_start' => $studentData['slot_start'] ?? null,
                    'slot_end' => $studentData['slot_end'] ?? null,
                ]);
                $absentRecordsCreated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully marked {$absentRecordsCreated} remaining student(s) as Absent.",
        ]);
    }

    public function exportCsv(Subject $subject, string $date): StreamedResponse
    {
        $parsedDate = CarbonImmutable::parse($date);
        $filename = "{$subject->name}_Attendance_{$parsedDate->toDateString()}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return new StreamedResponse(function () use ($subject, $parsedDate) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Student Name', 'Student Number', 'Section', 'Status', 'Time', 'Remarks']);

            Attendance::with('student')
                ->where('subject_id', $subject->id)
                ->whereDate('scanned_at', $parsedDate->toDateString())
                ->chunk(100, function ($attendances) use ($handle, $parsedDate) {
                    foreach ($attendances as $attendance) {
                        $student = $attendance->student;
                        fputcsv($handle, [
                            $parsedDate->toDateString(),
                            $student ? $student->name : 'Unknown/Deleted',
                            $student ? $student->student_number : 'N/A',
                            $student ? $student->section : 'N/A',
                            $attendance->status,
                            $attendance->scanned_at->toTimeString(),
                            $attendance->remarks,
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}

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
    public function index(Request $request): Response
    {
        $date = $request->input('date', now()->toDateString());
        $parsedDate = CarbonImmutable::parse($date);
        $dayOfWeek = $parsedDate->format('l');

        $subjects = Subject::query()
            ->select('id', 'name', 'icon', 'color', 'description')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($subject) use ($date) {
                // Get all students who have this subject in their schedule
                // We fetch IDs and schedules first to be robust against SQLite JSON issues
                $enrolledStudentIds = Student::query()
                    ->get(['id', 'schedule'])
                    ->filter(fn ($s) => collect($s->schedule ?? [])->contains('subject_id', $subject->id))
                    ->pluck('id');

                $enrolledCount = $enrolledStudentIds->count();

                // Get attendance for these students on this specific date
                $presentCount = Attendance::query()
                    ->where('subject_id', $subject->id)
                    ->whereDate('scanned_at', $date)
                    ->whereIn('student_id', $enrolledStudentIds)
                    ->count();

                $subject->stats = [
                    'enrolled' => $enrolledCount,
                    'present' => $presentCount,
                    'absent' => max(0, $enrolledCount - $presentCount),
                    'attendance_rate' => $enrolledCount > 0 ? round(($presentCount / $enrolledCount) * 100) : 0,
                ];

                return $subject;
            });

        return Inertia::render('ManageAttendance/Index', [
            'subjects' => $subjects,
            'filters' => [
                'date' => $date,
            ],
        ]);
    }

    public function show(Subject $subject, string $date): Response
    {
        // Parse the date to get the day of the week
        $parsedDate = CarbonImmutable::parse($date);
        $dayOfWeek = $parsedDate->format('l');

        // Get students who have THIS subject in their schedule (on any day)
        // We use a two-step approach for SQLite robustness and performance
        $enrolledIds = Student::query()
            ->get(['id', 'schedule'])
            ->filter(fn ($s) => collect($s->schedule ?? [])->contains('subject_id', $subject->id))
            ->pluck('id');

        $students = Student::query()
            ->select(['id', 'name', 'student_number', 'schedule', 'qr_token', 'photo'])
            ->whereIn('id', $enrolledIds)
            ->orderBy('name', 'asc')
            ->get();

        // Get past attendance sessions for trend analysis (last 5 dates with records for this subject)
        $pastDates = Attendance::query()
            ->where('subject_id', $subject->id)
            ->whereDate('scanned_at', '<', $parsedDate->toDateString())
            ->distinct()
            ->orderBy('scanned_at', 'desc')
            ->limit(5)
            ->pluck('scanned_at')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->reverse()
            ->values();

        // Bulk fetch today's attendance
        $todayAttendances = Attendance::query()
            ->where('subject_id', $subject->id)
            ->whereDate('scanned_at', $parsedDate->toDateString())
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['id', 'student_id', 'status', 'is_manual', 'remarks', 'scanned_at'])
            ->keyBy('student_id');

        // Bulk fetch historical attendance for trends
        $historicalAttendances = Attendance::query()
            ->where('subject_id', $subject->id)
            ->whereIn('scanned_at', $pastDates)
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['student_id', 'status', 'scanned_at'])
            ->groupBy('student_id');

        $mappedStudents = $students->map(function ($student) use ($subject, $dayOfWeek, $todayAttendances, $historicalAttendances, $pastDates) {
            $allSlots = collect($student->schedule ?? []);
            $todaySlot = $allSlots->first(fn ($s) => isset($s['subject_id'], $s['day']) && $s['subject_id'] == $subject->id && $s['day'] === $dayOfWeek);

            $attendance = $todayAttendances->get($student->id);
            $studentHistory = $historicalAttendances->get($student->id, collect());

            // Calculate trend (status for last 5 sessions)
            $trend = $pastDates->map(function ($date) use ($studentHistory) {
                $record = $studentHistory->first(fn ($h) => $h->scanned_at->toDateString() === $date);

                return $record ? $record->status : 'Unmarked';
            });

            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'photo' => $student->photo,
                'slot_start' => $todaySlot['start'] ?? null,
                'slot_end' => $todaySlot['end'] ?? null,
                'qr_token' => $student->qr_token,
                'attendance' => $attendance,
                'trend' => $trend,
            ];
        });

        return Inertia::render('ManageAttendance/Show', [
            'subject' => ['id' => $subject->id, 'name' => $subject->name],
            'date' => $parsedDate->toDateString(),
            'students' => $mappedStudents,
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:Present,Late,Time Out,Absent,Excused'],
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

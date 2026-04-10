<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubjectAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $startDate = $request->get('start')
            ? CarbonImmutable::parse($request->get('start'))
            : CarbonImmutable::now()->subDays(30);
        $endDate = $request->get('end')
            ? CarbonImmutable::parse($request->get('end'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $subjects = Subject::query()
            ->select(['id', 'name', 'icon', 'color', 'description'])
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'icon', 'color', 'description'])
            ->map(function ($subject) use ($startDate, $endDate) {
                $enrolledIds = Student::query()
                    ->select(['id', 'schedule'])
                    ->get(['id', 'schedule'])
                    ->filter(fn ($s) => collect($s->schedule ?? [])->contains('subject_id', $subject->id))
                    ->pluck('id');

                $enrolledCount = $enrolledIds->count();

                $attendances = Attendance::query()
                    ->where('subject_id', '=', (int) $subject->id, 'and')
                    ->whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()], 'and', false)
                    ->whereIn('student_id', $enrolledIds->toArray(), 'and', false)
                    ->select(['status'])
                    ->get(['status']);

                $totalRecords = $attendances->count();
                $presentCount = $attendances->whereIn('status', ['Present', 'present'])->count();
                $lateCount = $attendances->whereIn('status', ['Late', 'late'])->count();
                $absentCount = $attendances->whereIn('status', ['Absent', 'absent'])->count();
                $excusedCount = $attendances->whereIn('status', ['Excused', 'excused'])->count();

                $positiveCount = $presentCount + $lateCount + $excusedCount;
                $rate = $totalRecords > 0 ? round(($positiveCount / $totalRecords) * 100, 1) : 0;

                $daily = Attendance::query()
                    ->where('subject_id', '=', (int) $subject->id, 'and')
                    ->where('scanned_at', '>=', $startDate->toDateTimeString(), 'and')
                    ->where('scanned_at', '<=', $endDate->toDateTimeString(), 'and')
                    ->whereIn('student_id', $enrolledIds->toArray(), 'and', false)
                    ->selectRaw('DATE(scanned_at) as date, count(*) as count')
                    ->groupBy('date')
                    ->orderBy('date', 'asc')
                    ->get(['date', 'count']);

                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'icon' => $subject->icon,
                    'color' => $subject->color,
                    'description' => $subject->description,
                    'enrolled' => $enrolledCount,
                    'attendance_rate' => $rate,
                    'total_records' => $totalRecords,
                    'present' => $presentCount,
                    'late' => $lateCount,
                    'absent' => $absentCount,
                    'excused' => $excusedCount,
                    'daily' => $daily,
                ];
            });

        return Inertia::render('SubjectAttendance/Index', [
            'subjects' => $subjects,
            'filters' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ]);
    }

    public function show(Request $request, Subject $subject): Response
    {
        $startDate = $request->get('start')
            ? CarbonImmutable::parse($request->get('start'))
            : CarbonImmutable::now()->subDays(30);
        $endDate = $request->get('end')
            ? CarbonImmutable::parse($request->get('end'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $enrolledIds = Student::query()
            ->get(['id', 'schedule'])
            ->filter(fn ($s) => collect($s->schedule ?? [])->contains('subject_id', $subject->id))
            ->pluck('id');

        $daily = Attendance::query()
            ->where('subject_id', '=', (int) $subject->id, 'and')
            ->where('scanned_at', '>=', $startDate->toDateTimeString(), 'and')
            ->where('scanned_at', '<=', $endDate->toDateTimeString(), 'and')
            ->whereIn('student_id', $enrolledIds->toArray(), 'and', false)
            ->selectRaw('DATE(scanned_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get(['date', 'count']);

        $statusDistribution = Attendance::query()
            ->where('subject_id', '=', (int) $subject->id, 'and')
            ->where('scanned_at', '>=', $startDate->toDateTimeString(), 'and')
            ->where('scanned_at', '<=', $endDate->toDateTimeString(), 'and')
            ->whereIn('student_id', $enrolledIds->toArray(), 'and', false)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get(['status', 'count']);

        $studentStats = Student::query()
            ->whereIn('id', $enrolledIds->toArray(), 'and', false)
            ->select(['id', 'name', 'student_number', 'email', 'section', 'schedule', 'photo'])
            ->get(['id', 'name', 'student_number', 'email', 'section', 'schedule', 'photo'])
            ->map(function ($student) use ($subject, $startDate, $endDate) {
                $records = Attendance::query()
                    ->where('subject_id', '=', (int) $subject->id, 'and')
                    ->where('student_id', '=', (int) $student->id, 'and')
                    ->where('scanned_at', '>=', $startDate->toDateTimeString(), 'and')
                    ->where('scanned_at', '<=', $endDate->toDateTimeString(), 'and')
                    ->get(['status']);

                $total = $records->count();
                $positive = $records->whereIn('status', ['Present', 'present', 'Late', 'late', 'Excused', 'excused'])->count();
                $rate = $total > 0 ? round(($positive / $total) * 100, 1) : 0;

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'student_number' => $student->student_number,
                    'email' => $student->email,
                    'section' => $student->section,
                    'schedule' => $student->schedule,
                    'photo' => $student->photo,
                    'total_records' => $total,
                    'attendance_rate' => $rate,
                    'present' => $records->whereIn('status', ['Present', 'present'])->count(),
                    'late' => $records->whereIn('status', ['Late', 'late'])->count(),
                    'absent' => $records->whereIn('status', ['Absent', 'absent'])->count(),
                    'excused' => $records->whereIn('status', ['Excused', 'excused'])->count(),
                ];
            })
            ->sortBy('attendance_rate')
            ->values();

        $perPage = 25;
        $currentPage = max(1, (int) $request->integer('page', 1));
        $paginatedStudents = new LengthAwarePaginator(
            $studentStats->forPage($currentPage, $perPage)->values(),
            $studentStats->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ],
        );

        return Inertia::render('SubjectAttendance/Show', [
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'icon' => $subject->icon,
                'color' => $subject->color,
                'description' => $subject->description,
                'schedule' => $subject->schedule,
            ],
            'daily' => $daily,
            'statusDistribution' => $statusDistribution,
            'students' => $paginatedStudents,
            'allStudents' => Student::orderBy('name')->get(['id', 'name', 'student_number', 'email', 'section', 'schedule']),
            'otherSubjects' => Subject::where('id', '!=', $subject->id)->orderBy('name')->get(['id', 'name', 'schedule']),
            'enrolled' => $enrolledIds->count(),
            'filters' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ]);
    }

    public function moveStudents(Request $request, Subject $subject): RedirectResponse
    {
        $request->validate([
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['required', 'exists:students,id'],
            'to_subject_id' => ['required', 'exists:subjects,id', 'different:subject_id'],
        ]);

        $toSubject = Subject::findOrFail($request->to_subject_id);
        $studentIds = $request->student_ids;

        DB::transaction(function () use ($subject, $toSubject, $studentIds) {
            $students = Student::whereIn('id', $studentIds)->get();

            foreach ($students as $student) {
                $schedule = collect($student->schedule ?? []);

                // 1. Remove current subject from schedule
                $newSchedule = $schedule->filter(fn ($slot) => (int) ($slot['subject_id'] ?? 0) !== (int) $subject->id);

                // 2. Add new subject schedule
                if (count($toSubject->schedule ?? []) > 0) {
                    foreach ($toSubject->schedule as $slot) {
                        $newSchedule->push([
                            'day' => $slot['day'],
                            'subject_id' => $toSubject->id,
                            'start' => $slot['start'],
                            'end' => $slot['end'],
                        ]);
                    }
                } else {
                    // Placeholder if no schedule defined
                    $newSchedule->push([
                        'day' => 'Monday',
                        'subject_id' => $toSubject->id,
                        'start' => '08:00',
                        'end' => '09:00',
                    ]);
                }

                $student->update([
                    'schedule' => $newSchedule->values()->all(),
                ]);
            }
        });

        return back()->with('success', 'Students moved successfully');
    }

    public function removeStudents(Request $request, Subject $subject): RedirectResponse
    {
        $request->validate([
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['required', 'exists:students,id'],
        ]);

        $studentIds = $request->student_ids;

        DB::transaction(function () use ($subject, $studentIds) {
            $students = Student::whereIn('id', $studentIds)->get();

            foreach ($students as $student) {
                $schedule = collect($student->schedule ?? []);

                // Remove current subject from schedule
                $newSchedule = $schedule->filter(fn ($slot) => (int) ($slot['subject_id'] ?? 0) !== (int) $subject->id);

                $student->update([
                    'schedule' => $newSchedule->values()->all(),
                ]);
            }
        });

        return back()->with('success', 'Students removed from subject successfully');
    }
}

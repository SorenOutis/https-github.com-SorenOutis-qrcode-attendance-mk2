<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'enrolled' => $enrolledIds->count(),
            'filters' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentAnalyticsController extends Controller
{
    public function show(Request $request, Student $student): Response
    {
        $days = (int) $request->get('days', 30);
        $startDate = CarbonImmutable::now()->subDays($days);
        $endDate = CarbonImmutable::now()->endOfDay();

        $subjects = Subject::query()
            ->select(['id', 'name'])
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        /** @var array<int, array{date: string, present: int, absent: int, late: int, total: int}> */
        $dailyTrend = Attendance::query()
            ->where('student_id', '=', (int) $student->id, 'and')
            ->whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()], 'and', false)
            ->selectRaw('DATE(scanned_at) as date, status, count(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date', 'asc')
            ->get(['date', 'status', 'count'])
            ->groupBy('date')
            ->map(function ($items, $date) {
                $statusCounts = $items->pluck('count', 'status');

                return [
                    'date' => $date,
                    'present' => (int) ($statusCounts->get('Present', 0) + $statusCounts->get('present', 0)),
                    'late' => (int) ($statusCounts->get('Late', 0) + $statusCounts->get('late', 0)),
                    'absent' => (int) ($statusCounts->get('Absent', 0) + $statusCounts->get('absent', 0)),
                    'excused' => (int) ($statusCounts->get('Excused', 0) + $statusCounts->get('excused', 0)),
                ];
            })
            ->values();

        $subjectBreakdown = $subjects->map(function ($subject) use ($student, $startDate, $endDate) {
            $records = Attendance::query()
                ->where('student_id', '=', (int) $student->id, 'and')
                ->where('subject_id', '=', (int) $subject->id, 'and')
                ->whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()], 'and', false)
                ->select(['status'])
                ->get(['status']);

            $total = $records->count();
            $positive = $records->whereIn('status', ['Present', 'present', 'Late', 'late', 'Excused', 'excused'])->count();
            $rate = $total > 0 ? round(($positive / $total) * 100, 1) : 0;

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'total_records' => $total,
                'attendance_rate' => $rate,
                'present' => $records->whereIn('status', ['Present', 'present'])->count(),
                'late' => $records->whereIn('status', ['Late', 'late'])->count(),
                'absent' => $records->whereIn('status', ['Absent', 'absent'])->count(),
                'excused' => $records->whereIn('status', ['Excused', 'excused'])->count(),
            ];
        })->filter(fn ($s) => $s['total_records'] > 0)->values();

        $allStatuses = DB::table('attendances')
            ->where('student_id', '=', (int) $student->id)
            ->orderBy('scanned_at', 'desc')
            ->pluck('status');

        $currentStreak = 0;
        foreach ($allStatuses as $status) {
            $s = strtolower((string) $status);
            if (in_array($s, ['present', 'late', 'excused'], true)) {
                $currentStreak++;
            } else {
                break;
            }
        }

        $longestStreak = 0;
        $tempStreak = 0;
        foreach ($allStatuses->reverse() as $status) {
            $s = strtolower((string) $status);
            if (in_array($s, ['present', 'late', 'excused'], true)) {
                $tempStreak++;
                $longestStreak = max($longestStreak, $tempStreak);
            } else {
                $tempStreak = 0;
            }
        }

        $totalStats = DB::table('attendances')
            ->where('student_id', '=', (int) $student->id)
            ->select(['status', DB::raw('count(*) as count')])
            ->groupBy('status')
            ->get(['status', DB::raw('count(*) as count')]);

        $totalRecords = $totalStats->sum('count');
        $presentCount = $totalStats->whereIn('status', ['Present', 'present'])->sum('count');
        $lateCount = $totalStats->whereIn('status', ['Late', 'late'])->sum('count');
        $excusedCount = $totalStats->whereIn('status', ['Excused', 'excused'])->sum('count');
        $positiveTotal = $presentCount + $lateCount + $excusedCount;
        $overallPercentage = $totalRecords > 0 ? round(($positiveTotal / $totalRecords) * 100, 1) : 100;

        $heatmap = Attendance::query()
            ->where('student_id', '=', (int) $student->id, 'and')
            ->whereBetween('scanned_at', [CarbonImmutable::now()->subDays(90)->toDateTimeString(), $endDate->toDateTimeString()], 'and', false)
            ->selectRaw('DATE(scanned_at) as date, status')
            ->get(['date', 'status'])
            ->groupBy('date')
            ->map(function ($items, $date) {
                $hasAbsent = $items->whereIn('status', ['Absent', 'absent'])->isNotEmpty();
                $hasPresent = $items->whereIn('status', ['Present', 'present', 'Late', 'late', 'Excused', 'excused'])->isNotEmpty();

                return [
                    'date' => $date,
                    'status' => $hasAbsent && ! $hasPresent ? 'absent' : ($hasPresent ? 'present' : 'mixed'),
                    'count' => $items->count(),
                ];
            })
            ->values();

        return Inertia::render('StudentAnalytics', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'section' => $student->section,
                'photo' => $student->photo,
            ],
            'dailyTrend' => $dailyTrend,
            'subjectBreakdown' => $subjectBreakdown,
            'streak' => [
                'current' => $currentStreak,
                'longest' => $longestStreak,
            ],
            'stats' => [
                'total_records' => (int) $totalRecords,
                'percentage' => $overallPercentage,
                'present' => (int) $presentCount,
                'late' => (int) $lateCount,
                'absent' => (int) ($totalStats->whereIn('status', ['Absent', 'absent'])->sum('count')),
                'excused' => (int) $excusedCount,
            ],
            'heatmap' => $heatmap,
            'filters' => ['days' => $days],
            'subjects' => $subjects,
        ]);
    }
}

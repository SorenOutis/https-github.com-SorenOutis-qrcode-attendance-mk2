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

class DashboardController extends Controller
{
    public function index(): Response
    {
        $date = CarbonImmutable::now()->toDateString();
        $dayOfWeek = CarbonImmutable::now()->format('l');
        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        $search = request('search');
        $onlyScheduled = request('only_scheduled') === 'true';

        $studentsQuery = Student::query();

        if ($search) {
            $studentsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%")
                    ->orWhere('section', 'like', "%{$search}%");
            });
        }

        if ($onlyScheduled) {
            $studentsQuery->where('schedule', 'like', "%{$dayOfWeek}%");
        }

        $status = request('status');
        if ($status) {
            // Filter students who have at least one attendance with this status today
            $studentsQuery->whereHas('attendances', function ($q) use ($status, $date) {
                $q->whereDate('scanned_at', $date)->where('status', $status);
            });
        }

        $students = $studentsQuery
            ->orderBy('name')
            ->paginate(12, [
                'id',
                'name',
                'student_number',
                'email',
                'section',
                'qr_token',
                'schedule',
                'photo',
                'created_at',
            ])
            ->withQueryString();

        $trashedStudents = Student::onlyTrashed()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'student_number',
                'email',
                'section',
                'qr_token',
                'schedule',
                'photo',
                'created_at',
                'deleted_at',
            ]);

        $latestByStudent = Attendance::query()
            ->whereDate('scanned_at', $date)
            ->orderByDesc('scanned_at')
            ->get(['id', 'student_id', 'status', 'scanned_at', 'subject_id'])
            ->groupBy('student_id')
            ->map(fn ($items) => $items->first());

        // All unique statuses and their times recorded today per student
        $statusesByStudent = Attendance::query()
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get(['student_id', 'status', 'scanned_at', 'subject_id'])
            ->groupBy('student_id')
            ->map(fn ($items) => $items->map(fn ($item) => [
                'status' => $item->status,
                'time' => $item->scanned_at->format('h:i A'),
                'subject_id' => $item->subject_id,
            ])->values()->all());

        // Group total attendances by student for percentage calculation
        $allAttendancesByStudent = DB::table('attendances')
            ->select('student_id', 'status', DB::raw('count(*) as count'))
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id');

        $mapStudent = function ($student) use ($latestByStudent, $statusesByStudent, $allAttendancesByStudent) {
            $latest = $latestByStudent->get($student->id);

            $historyStats = $allAttendancesByStudent->get($student->id, collect());
            $totalRecords = $historyStats->sum('count');
            $presentCount = $historyStats->whereIn('status', ['Present', 'present'])->sum('count');
            $lateCount = $historyStats->whereIn('status', ['Late', 'late'])->sum('count');
            $excusedCount = $historyStats->whereIn('status', ['Excused', 'excused'])->sum('count');

            // Calculate attendance percentage (Present + Late + Excused / Total)
            $positiveRecords = $presentCount + $lateCount + $excusedCount;
            $attendancePercentage = $totalRecords > 0 ? (int) round(($positiveRecords / $totalRecords) * 100) : 100;

            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'email' => $student->email,
                'section' => $student->section,
                'qr_token' => $student->qr_token,
                'schedule' => $student->schedule,
                'photo' => $student->photo,
                'created_at' => $student->created_at,
                'deleted_at' => $student->deleted_at,
                'attendance_percentage' => $attendancePercentage,
                'total_records' => $totalRecords,
                'today_statuses' => $statusesByStudent->get($student->id, []),
                'latest_attendance' => $latest
                    ? [
                        'id' => $latest->id,
                        'status' => $latest->status,
                        'scanned_at' => $latest->scanned_at,
                        'subject_id' => $latest->subject_id,
                    ]
                    : null,
            ];
        };

        $recentActivity = Attendance::query()
            ->whereHas('student')
            ->with(['student:id,name,photo', 'subject:id,name'])
            ->orderByDesc('scanned_at')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->student?->name ?? 'Unknown Student',
                'photo' => $item->student?->photo,
                'status' => $item->status,
                'time' => $item->scanned_at->format('h:i A'),
                'subject_id' => $item->subject_id,
                'subject_name' => $item->subject?->name,
            ]);

        // Overall stats for the Chart
        $attendanceStats = DB::table('attendances')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $totalAttendance = (int) $attendanceStats->sum();
        $positiveAttendance = (int) (($attendanceStats['Present'] ?? 0) + ($attendanceStats['present'] ?? 0) + ($attendanceStats['Late'] ?? 0) + ($attendanceStats['late'] ?? 0) + ($attendanceStats['Excused'] ?? 0) + ($attendanceStats['excused'] ?? 0));
        $attendanceRate = $totalAttendance > 0 ? round(($positiveAttendance / $totalAttendance) * 100, 1) : 100;

        $studentsData = $students->getCollection()->map($mapStudent);
        $students->setCollection($studentsData);

        // High-performance at-risk calculation using SQL for global data
        $allAtRisk = Student::query()
            ->select('students.*')
            ->selectRaw('(SELECT COUNT(*) FROM attendances WHERE student_id = students.id) as total_attendance_count')
            ->selectRaw('(SELECT COUNT(*) FROM attendances WHERE student_id = students.id AND status IN ("Present", "present", "Late", "late", "Excused", "excused")) as positive_attendance_count')
            ->get()
            ->map(function ($student) use ($mapStudent) {
                // Manually calculate percentage
                $total = $student->total_attendance_count;
                $positive = $student->positive_attendance_count;
                $percentage = $total > 0 ? (int) round(($positive / $total) * 100) : 100;
                
                $mapped = $mapStudent($student);
                $mapped['attendance_percentage'] = $percentage;
                return $mapped;
            })
            ->filter(fn ($s) => $s['attendance_percentage'] < 80)
            ->sortBy('attendance_percentage');

        return Inertia::render('Dashboard', [
            'subjects' => $subjects,
            'students' => $students,
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
                'only_scheduled' => request('only_scheduled') === 'true',
            ],
            'trashedStudents' => Inertia::defer(fn () => $trashedStudents->map($mapStudent)),
            'atRiskCount' => Inertia::defer(fn () => $allAtRisk->count()),
            'topAtRiskStudents' => Inertia::defer(fn () => $allAtRisk->take(5)->values()->all()),
            'recentActivity' => Inertia::defer(fn () => $recentActivity),
            'attendanceRate' => Inertia::defer(fn () => $attendanceRate),
            'attendanceStats' => Inertia::defer(fn () => [
                'Present' => (int) (($attendanceStats['Present'] ?? 0) + ($attendanceStats['present'] ?? 0)),
                'Late' => (int) (($attendanceStats['Late'] ?? 0) + ($attendanceStats['late'] ?? 0)),
                'Absent' => (int) (($attendanceStats['Absent'] ?? 0) + ($attendanceStats['absent'] ?? 0)),
                'Excused' => (int) (($attendanceStats['Excused'] ?? 0) + ($attendanceStats['excused'] ?? 0)),
            ]),
        ]);
    }
}

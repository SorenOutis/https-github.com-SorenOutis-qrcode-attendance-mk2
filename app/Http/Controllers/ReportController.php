<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports', [
            'subjects' => Subject::query()->orderBy('name', 'asc')->get(['id', 'name']),
        ]);
    }

    public function stats(Request $request)
    {
        $days = $request->get('days', 30);
        $subjectId = $request->get('subject_id');
        $startDate = $request->get('start') ? Carbon::parse($request->get('start')) : Carbon::now()->subDays($days);
        $endDate = $request->get('end') ? Carbon::parse($request->get('end'))->endOfDay() : Carbon::now()->endOfDay();

        $query = Attendance::whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()]);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        // 1. Attendance Rate over time
        $dailystats = (clone $query)
            ->selectRaw('DATE(scanned_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 2. Section Comparison
        $sectionStats = Student::query()->withCount(['attendances' => function ($q) use ($startDate, $endDate, $subjectId) {
            $q->whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()]);
            if ($subjectId) {
                $q->where('subject_id', '=', $subjectId);
            }
        }])
            ->get()
            ->groupBy('section')
            ->map(fn ($students) => $students->sum('attendances_count'));

        // 3. Status distribution
        $statusStats = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        return response()->json([
            'daily' => $dailystats,
            'sections' => $sectionStats,
            'status' => $statusStats,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ];

        return new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Student Name', 'ID Number', 'Section', 'Status', 'Time']);

            Attendance::with('student')
                ->orderByDesc('scanned_at')
                ->chunk(100, function ($attendances) use ($handle) {
                    foreach ($attendances as $attendance) {
                        $student = $attendance->student;
                        fputcsv($handle, [
                            $attendance->scanned_at->toDateString(),
                            $student ? $student->name : 'Unknown/Deleted',
                            $student ? $student->student_number : 'N/A',
                            $student ? $student->section : 'N/A',
                            $attendance->status,
                            $attendance->scanned_at->toTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}

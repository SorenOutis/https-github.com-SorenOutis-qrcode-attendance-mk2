<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $subjectId = $request->query('subject_id');

        $query = Attendance::with(['student', 'subject']);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $attendances = $query->get()
            ->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'student_name' => $attendance->student->name,
                    'status' => $attendance->status,
                    'scanned_at' => $attendance->scanned_at->toIso8601String(),
                    'subject_name' => $attendance->subject?->name ?? 'N/A',
                ];
            });

        return Inertia::render('Calendar', [
            'attendances' => $attendances,
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'subject_id' => $subjectId,
            ],
        ]);
    }
}

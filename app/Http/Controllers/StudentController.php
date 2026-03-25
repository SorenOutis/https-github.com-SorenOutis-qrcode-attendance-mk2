<?php

namespace App\Http\Controllers;

use App\ActivityLogger;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentQrToken;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(): Response
    {
        $date = CarbonImmutable::now()->toDateString();
        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        $students = Student::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'student_number',
                'email',
                'section',
                'qr_token',
                'schedule',
                'created_at',
            ]);

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
            $attendancePercentage = $totalRecords > 0 ? round(($positiveRecords / $totalRecords) * 100) : 100;

            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'email' => $student->email,
                'section' => $student->section,
                'qr_token' => $student->qr_token,
                'schedule' => $student->schedule,
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

        // Overall stats for the Chart
        $attendanceStats = DB::table('attendances')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $studentsData = $students->map($mapStudent);
        $atRiskCount = $studentsData->filter(fn ($s) => $s['attendance_percentage'] < 80)->count();

        return Inertia::render('Dashboard', [
            'subjects' => $subjects,
            'students' => $studentsData,
            'trashedStudents' => $trashedStudents->map($mapStudent),
            'atRiskCount' => $atRiskCount,
            'attendanceStats' => [
                'Present' => $attendanceStats->get('Present', 0) + $attendanceStats->get('present', 0),
                'Late' => $attendanceStats->get('Late', 0) + $attendanceStats->get('late', 0),
                'Absent' => $attendanceStats->get('Absent', 0) + $attendanceStats->get('absent', 0),
                'Excused' => $attendanceStats->get('Excused', 0) + $attendanceStats->get('excused', 0),
            ],
        ]);
    }

    public function printCards(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'string'],
        ]);

        $ids = collect(explode(',', (string) ($validated['ids'] ?? '')))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => $v !== '' && ctype_digit($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        $studentsQuery = Student::query()->orderBy('name');

        if ($ids->isNotEmpty()) {
            $studentsQuery->whereIn('id', $ids);
        }

        $students = $studentsQuery->get([
            'id',
            'name',
            'student_number',
            'section',
            'qr_token',
        ]);

        return Inertia::render('Students/PrintCards', [
            'students' => $students,
            'preselectedIds' => $ids,
        ]);
    }

    public function portal(string $token): Response
    {
        $studentId = StudentQrToken::query()
            ->where('token', $token)
            ->value('student_id');

        $student = Student::query()->findOrFail($studentId, [
            'id',
            'name',
            'student_number',
            'email',
            'section',
            'qr_token',
            'schedule',
        ]);

        $now = CarbonImmutable::now();
        $dayOfWeek = $now->format('l');
        $date = $now->toDateString();

        $todayStatuses = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get(['id', 'status', 'scanned_at', 'subject_id'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'status' => $item->status,
                'time' => $item->scanned_at->format('h:i A'),
                'subject_id' => $item->subject_id,
            ])
            ->values();

        $history = $student->attendances()
            ->latest('scanned_at')
            ->limit(30)
            ->get(['id', 'status', 'scanned_at', 'slot_start', 'slot_end', 'subject_id'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'status' => $a->status,
                'scanned_at' => $a->scanned_at->toISOString(),
                'slot_start' => $a->slot_start?->format('H:i'),
                'slot_end' => $a->slot_end?->format('H:i'),
                'subject_id' => $a->subject_id,
            ]);

        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        $todaySchedule = collect($student->schedule ?? [])
            ->filter(fn ($slot) => isset($slot['day'], $slot['start'], $slot['end']))
            ->filter(fn ($slot) => $slot['day'] === $dayOfWeek)
            ->values()
            ->all();

        // Calculate Stats
        $historyStats = DB::table('attendances')
            ->where('student_id', $student->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
            
        $totalRecords = $historyStats->sum('count');
        $presentCount = $historyStats->whereIn('status', ['Present', 'present'])->sum('count');
        $lateCount = $historyStats->whereIn('status', ['Late', 'late'])->sum('count');
        $excusedCount = $historyStats->whereIn('status', ['Excused', 'excused'])->sum('count');

        $positiveRecords = $presentCount + $lateCount + $excusedCount;
        $attendancePercentage = $totalRecords > 0 ? (int) round(($positiveRecords / $totalRecords) * 100) : 100;

        $streak = 0;
        $attendancesDesc = DB::table('attendances')
            ->where('student_id', $student->id)
            ->orderByDesc('scanned_at')
            ->pluck('status');
            
        foreach ($attendancesDesc as $s) {
            if (in_array(strtolower($s), ['present', 'late', 'excused'])) {
                $streak++;
            } else {
                break;
            }
        }

        return Inertia::render('StudentPortal', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'email' => $student->email,
                'section' => $student->section,
                'qr_token' => $student->qr_token,
            ],
            'stats' => [
                'percentage' => $attendancePercentage,
                'streak' => $streak,
            ],
            'subjects' => $subjects,
            'todaySchedule' => $todaySchedule,
            'todayStatuses' => $todayStatuses,
            'history' => $history,
        ]);
    }

    public function destroy(Student $student)
    {
        $name = $student->name;
        $id = $student->id;
        $student->delete();

        ActivityLogger::log('student.delete', "Moved student to trash: {$name}", ['id' => $id]);

        return redirect()
            ->back()
            ->with('flash', [
                'student_deleted' => true,
            ]);
    }

    public function restore($id)
    {
        $student = Student::withTrashed()->findOrFail($id);
        $student->restore();

        ActivityLogger::log('student.restore', "Restored student from trash: {$student->name}", ['id' => $student->id]);

        return redirect()
            ->back()
            ->with('flash', [
                'student_restored' => true,
            ]);
    }

    public function forceDelete($id)
    {
        $student = Student::withTrashed()->findOrFail($id);
        $name = $student->name;
        $student->forceDelete();

        ActivityLogger::log('student.force-delete', "Permanently deleted student: {$name}", ['id' => $id]);

        return redirect()
            ->back()
            ->with('flash', [
                'student_permanently_deleted' => true,
            ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'student_number' => ['required', 'string', 'max:255', 'unique:students,student_number'],
            'email' => ['nullable', 'email', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'schedule' => ['required', 'array', 'min:1'],
            'schedule.*.day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'schedule.*.subject_id' => ['required', 'exists:subjects,id'],
            'schedule.*.start' => ['required', 'date_format:H:i'],
            'schedule.*.end' => ['required', 'date_format:H:i'],
        ]);

        // Ensure each slot has start < end
        $data['schedule'] = collect($data['schedule'])
            ->filter(fn ($slot) => isset($slot['day'], $slot['subject_id'], $slot['start'], $slot['end']))
            ->map(function ($slot) {
                return [
                    'day' => $slot['day'],
                    'subject_id' => (int) $slot['subject_id'],
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                ];
            })
            ->values()
            ->all();

        $data['qr_token'] = Str::uuid()->toString();

        $student = Student::create($data);

        ActivityLogger::log('student.create', "Created student: {$student->name}", ['id' => $student->id]);

        StudentQrToken::create([
            'student_id' => $student->id,
            'token' => $student->qr_token,
        ]);

        return redirect()
            ->back()
            ->with('flash', [
                'student_created' => true,
                'student_id' => $student->id,
            ]);
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'student_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('students', 'student_number')->ignore($student->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'schedule' => ['required', 'array', 'min:1'],
            'schedule.*.day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'schedule.*.subject_id' => ['required', 'exists:subjects,id'],
            'schedule.*.start' => ['required', 'date_format:H:i'],
            'schedule.*.end' => ['required', 'date_format:H:i'],
        ]);

        $data['schedule'] = collect($data['schedule'])
            ->filter(fn ($slot) => isset($slot['day'], $slot['subject_id'], $slot['start'], $slot['end']))
            ->map(function ($slot) {
                return [
                    'day' => $slot['day'],
                    'subject_id' => (int) $slot['subject_id'],
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                ];
            })
            ->values()
            ->all();

        $student->update($data);

        ActivityLogger::log('student.update', "Updated student: {$student->name}", ['id' => $student->id]);

        return redirect()
            ->back()
            ->with('flash', [
                'student_updated' => true,
                'student_id' => $student->id,
            ]);
    }

    public function regenerateQr(Student $student)
    {
        $nextToken = Str::uuid()->toString();

        $student->update([
            'qr_token' => $nextToken,
        ]);

        StudentQrToken::firstOrCreate([
            'student_id' => $student->id,
            'token' => $nextToken,
        ]);

        return redirect()
            ->back()
            ->with('flash', [
                'qr_regenerated' => true,
                'student_id' => $student->id,
            ]);
    }

    public function attendance(Student $student): JsonResponse
    {
        $history = $student->attendances()
            ->orderByDesc('scanned_at')
            ->get(['id', 'status', 'scanned_at', 'slot_start', 'slot_end'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'status' => $a->status,
                'scanned_at' => $a->scanned_at->toISOString(),
                'slot_start' => $a->slot_start?->format('H:i'),
                'slot_end' => $a->slot_end?->format('H:i'),
            ]);

        return response()->json(['history' => $history]);
    }
}

use App\Exports\AttendanceExport;
use App\Models\Attendance;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Export attendance report as CSV.
     */
    public function attendanceCsv(Request $request): StreamedResponse
    {
        $startDate = $request->get('start')
            ? CarbonImmutable::parse($request->get('start'))
            : CarbonImmutable::now()->subDays(30);
        $endDate = $request->get('end')
            ? CarbonImmutable::parse($request->get('end'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $subjectId = $request->get('subject_id');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ];

        return new StreamedResponse(function () use ($startDate, $endDate, $subjectId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Student Name', 'ID Number', 'Section', 'Subject', 'Status', 'Time', 'Remarks']);

            $query = Attendance::with(['student', 'subject'])
                ->whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
                ->orderBy('scanned_at', 'desc');

            if ($subjectId && $subjectId !== 'all') {
                $query->where('subject_id', '=', $subjectId);
            }

            $query->chunk(100, function ($attendances) use ($handle) {
                foreach ($attendances as $attendance) {
                    $student = $attendance->student;
                    $subject = $attendance->subject;
                    fputcsv($handle, [
                        $attendance->scanned_at->toDateString(),
                        $student ? $student->name : 'Unknown/Deleted',
                        $student ? $student->student_number : 'N/A',
                        $student ? $student->section : 'N/A',
                        $subject ? $subject->name : 'N/A',
                        $attendance->status,
                        $attendance->scanned_at->toTimeString(),
                        $attendance->remarks ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export attendance report as Excel.
     */
    public function attendanceExcel(Request $request)
    {
        $startDate = $request->get('start')
            ? CarbonImmutable::parse($request->get('start'))
            : CarbonImmutable::now()->subDays(30);
        $endDate = $request->get('end')
            ? CarbonImmutable::parse($request->get('end'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $subjectId = $request->get('subject_id');

        $query = Attendance::with(['student', 'subject'])
            ->whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
            ->orderBy('scanned_at', 'desc');

        if ($subjectId && $subjectId !== 'all') {
            $query->where('subject_id', '=', $subjectId);
        }

        $records = $query->get();

        return Excel::download(new AttendanceExport($records), 'attendance_report.xlsx');
    }

    /**
     * Export attendance report as PDF.
     */
    public function attendancePdf(Request $request)
    {
        $startDate = $request->get('start')
            ? CarbonImmutable::parse($request->get('start'))
            : CarbonImmutable::now()->subDays(30);
        $endDate = $request->get('end')
            ? CarbonImmutable::parse($request->get('end'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $subjectId = $request->get('subject_id');

        $query = Attendance::with(['student', 'subject'])
            ->whereBetween('scanned_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
            ->orderBy('scanned_at', 'desc');

        if ($subjectId && $subjectId !== 'all') {
            $query->where('subject_id', '=', $subjectId);
        }

        $records = $query->limit(500)->get(); // Limit PDF for performance

        $pdf = Pdf::loadView('exports.attendance', [
            'records' => $records,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'title' => 'Attendance Report',
        ]);

        return $pdf->download('attendance_report.pdf');
    }

    /**
     * Export student list as CSV.
     */
    public function studentListCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_list.csv"',
        ];

        return new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Student Number', 'Email', 'Section', 'Schedule']);

            Student::query()
                ->orderBy('name')
                ->chunk(100, function ($students) use ($handle) {
                    foreach ($students as $student) {
                        $scheduleStr = collect($student->schedule ?? [])
                            ->map(fn ($s) => ($s['day'] ?? '').' '.($s['start'] ?? '').'-'.($s['end'] ?? ''))
                            ->implode('; ');

                        fputcsv($handle, [
                            $student->name,
                            $student->student_number,
                            $student->email ?? '',
                            $student->section ?? '',
                            $scheduleStr,
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export per-student analytics as CSV.
     */
    public function studentAnalyticsCsv(Student $student): StreamedResponse
    {
        $filename = str_replace(' ', '_', $student->name).'_analytics.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return new StreamedResponse(function () use ($student) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Subject', 'Status', 'Time', 'Slot Start', 'Slot End', 'Remarks']);

            $student->attendances()
                ->with('subject')
                ->orderByDesc('scanned_at')
                ->chunk(100, function ($attendances) use ($handle) {
                    foreach ($attendances as $attendance) {
                        fputcsv($handle, [
                            $attendance->scanned_at->toDateString(),
                            $attendance->subject?->name ?? 'N/A',
                            $attendance->status,
                            $attendance->scanned_at->toTimeString(),
                            $attendance->slot_start?->format('H:i') ?? '',
                            $attendance->slot_end?->format('H:i') ?? '',
                            $attendance->remarks ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}

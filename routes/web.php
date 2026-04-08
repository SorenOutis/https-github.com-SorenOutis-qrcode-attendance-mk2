<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExcuseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ManualAttendanceController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentAnalyticsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\SubjectAttendanceController;
use App\Http\Controllers\SubjectController;
use App\Models\Attendance;
use App\Models\Comment;
use App\Models\Rating;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return inertia('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'comments' => Comment::where('is_public', true)->latest()->take(10)->get(),
        'ratings' => Rating::where('is_public', true)->latest()->take(10)->get(),
        'stats' => [
            'total_scans' => Attendance::count(),
            'present_today' => Attendance::whereDate('created_at', today())->where('status', 'Present')->count(),
            'average_rating' => round((float) Rating::avg('score'), 1),
            'total_ratings' => Rating::count(),
        ],
    ]);
})->name('home');

// Student self-service portal (scan QR to open)
Route::get('portal/{token}', [StudentController::class, 'portal'])
    ->middleware(['throttle:60,1'])
    ->name('student.portal');

// Guest + authenticated users can submit comments
Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
Route::post('ratings', [RatingController::class, 'store'])->name('ratings.store');

// Student excuse submission (token-based, no auth required)
Route::post('excuses', [ExcuseController::class, 'store'])
    ->middleware(['throttle:30,1'])
    ->name('excuses.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::resource('subjects', SubjectController::class)->except(['create', 'show', 'edit']);

    Route::get('students/print-cards', [StudentController::class, 'printCards'])->name('students.print-cards');

    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::post('students/{id}/restore', [StudentController::class, 'restore'])->name('students.restore');
    Route::delete('students/{id}/force-delete', [StudentController::class, 'forceDelete'])->name('students.force-delete');
    Route::post('students/{student}/qr/regenerate', [StudentController::class, 'regenerateQr'])->name('students.qr.regenerate');
    Route::get('students/{student}/attendance', [StudentController::class, 'attendance'])->name('students.attendance');
    Route::post('students/import', [StudentImportController::class, 'store'])->name('students.import');
    Route::get('students/sample', [StudentImportController::class, 'downloadSample'])->name('students.sample');
    Route::get('template/download', [StudentImportController::class, 'downloadSample'])->name('template.download');
    Route::get('template/download', [StudentImportController::class, 'downloadSample'])->name('template.download');

    // Student Analytics
    Route::get('students/{student}/analytics', [StudentAnalyticsController::class, 'show'])->name('students.analytics');

    Route::post('attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');

    Route::get('comments', [CommentController::class, 'index'])->name('comments.index');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('ratings', [RatingController::class, 'index'])->name('ratings.index');
    Route::put('ratings/{rating}', [RatingController::class, 'update'])->name('ratings.update');
    Route::delete('ratings/{rating}', [RatingController::class, 'destroy'])->name('ratings.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('api/reports/stats', [ReportController::class, 'stats'])->name('api.reports.stats');
    Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');

    Route::get('manage-attendance', [ManualAttendanceController::class, 'index'])->name('manage-attendance.index');
    Route::get('manage-attendance/{subject}/{date}', [ManualAttendanceController::class, 'show'])->name('manage-attendance.show');
    Route::post('manage-attendance/toggle', [ManualAttendanceController::class, 'toggle'])->name('manage-attendance.toggle');
    Route::post('manage-attendance/mark-all-absent', [ManualAttendanceController::class, 'markAllAbsent'])->name('manage-attendance.mark-all-absent');
    Route::get('manage-attendance/{subject}/{date}/export', [ManualAttendanceController::class, 'exportCsv'])->name('manage-attendance.export');

    // Subject Attendance
    Route::get('subject-attendance', [SubjectAttendanceController::class, 'index'])->name('subject-attendance.index');
    Route::get('subject-attendance/{subject}', [SubjectAttendanceController::class, 'show'])->name('subject-attendance.show');

    // Exports
    Route::get('exports/attendance', [ExportController::class, 'attendancePdf'])->name('exports.attendance');
    Route::get('exports/students', [ExportController::class, 'studentListCsv'])->name('exports.students');
    Route::get('exports/student-analytics/{student}', [ExportController::class, 'studentAnalyticsCsv'])->name('exports.student-analytics');

    // Excuses (teacher management)
    Route::get('excuses', [ExcuseController::class, 'index'])->name('excuses.index');
    Route::put('excuses/{excuse}', [ExcuseController::class, 'update'])->name('excuses.update');

    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
    Route::post('backups/{file}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::delete('backups/{file}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::get('backups/{file}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::post('backups/upload', [BackupController::class, 'upload'])->name('backups.upload');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
});

require __DIR__.'/settings.php';

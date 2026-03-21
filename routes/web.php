<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ManualAttendanceController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [StudentController::class, 'index'])->name('dashboard');
    Route::resource('subjects', SubjectController::class)->except(['create', 'show', 'edit']);

    Route::get('students/print-cards', [StudentController::class, 'printCards'])->name('students.print-cards');

    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::post('students/{id}/restore', [StudentController::class, 'restore'])->name('students.restore');
    Route::delete('students/{id}/force-delete', [StudentController::class, 'forceDelete'])->name('students.force-delete');
    Route::post('students/{student}/qr/regenerate', [StudentController::class, 'regenerateQr'])->name('students.qr.regenerate');
    Route::get('students/{student}/attendance', [StudentController::class, 'attendance'])->name('students.attendance');

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
});

require __DIR__.'/settings.php';

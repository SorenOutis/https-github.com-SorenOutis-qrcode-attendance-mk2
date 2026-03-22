<?php

namespace App\Http\Controllers;

use App\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class BackupController extends Controller
{
    public function index(): Response
    {
        $backupDir = storage_path('app/private/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $files = File::files($backupDir);

        $backups = collect($files)->map(function ($file) {
            return [
                'name' => $file->getFilename(),
                'size' => $this->formatBytes($file->getSize()),
                'size_bytes' => $file->getSize(),
                'date' => Carbon::createFromTimestamp($file->getMTime())->toIso8601String(),
                'date_formatted' => Carbon::createFromTimestamp($file->getMTime())->format('M d, Y h:i A'),
            ];
        })->sortByDesc('date')->values();

        return Inertia::render('Backup', [
            'backups' => $backups,
        ]);
    }

    public function store(): RedirectResponse
    {
        $backupDir = storage_path('app/private/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $databasePath = database_path('database.sqlite');

        if (! File::exists($databasePath)) {
            return back()->with('error', 'Database file not found.');
        }

        $fileName = 'backup_'.now()->format('Y_m_d_His').'.sqlite';
        $backupPath = $backupDir.'/'.$fileName;

        File::copy($databasePath, $backupPath);

        ActivityLogger::log(
            'backup.create',
            "Created a new database backup: {$fileName}",
            ['filename' => $fileName]
        );

        return back()->with('success', 'Backup created successfully.');
    }

    public function restore($file): RedirectResponse
    {
        $backupPath = storage_path('app/private/backups/'.$file);

        if (! File::exists($backupPath)) {
            return back()->with('error', 'Backup file not found.');
        }

        $databasePath = database_path('database.sqlite');

        // Restore the backup by copying it over the existing database
        File::copy($backupPath, $databasePath);

        ActivityLogger::log(
            'backup.restore',
            "Restored database from backup: {$file}",
            ['filename' => $file]
        );

        return back()->with('success', 'Database restored successfully.');
    }

    public function destroy($file): RedirectResponse
    {
        $backupPath = storage_path('app/private/backups/'.$file);

        if (File::exists($backupPath)) {
            File::delete($backupPath);

            ActivityLogger::log(
                'backup.delete',
                "Deleted backup file: {$file}",
                ['filename' => $file]
            );

            return back()->with('success', 'Backup deleted successfully.');
        }

        return back()->with('error', 'Backup file not found.');
    }

    public function download($file)
    {
        $backupPath = storage_path('app/private/backups/'.$file);

        if (File::exists($backupPath)) {
            return response()->download($backupPath);
        }

        return back()->with('error', 'Backup file not found.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'backup' => ['required', 'file'],
        ]);

        $file = $request->file('backup');

        if (! str_ends_with($file->getClientOriginalName(), '.sqlite') && $file->getClientOriginalExtension() !== 'sqlite') {
            return back()->with('error', 'Invalid file type. Only .sqlite files are allowed.');
        }

        $backupDir = storage_path('app/private/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $fileName = 'backup_uploaded_'.now()->format('Y_m_d_His').'.sqlite';
        $file->move($backupDir, $fileName);

        ActivityLogger::log(
            'backup.upload',
            "Uploaded an external backup file: {$fileName}",
            ['filename' => $fileName]
        );

        return back()->with('success', 'Backup uploaded successfully. You can now restore it.');
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}

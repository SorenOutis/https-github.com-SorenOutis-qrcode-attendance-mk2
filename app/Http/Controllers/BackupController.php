<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function index()
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

    public function store()
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

        return back()->with('success', 'Backup created successfully.');
    }

    public function restore($file)
    {
        $backupPath = storage_path('app/private/backups/'.$file);

        if (! File::exists($backupPath)) {
            return back()->with('error', 'Backup file not found.');
        }

        $databasePath = database_path('database.sqlite');

        // Restore the backup by copying it over the existing database
        File::copy($backupPath, $databasePath);

        return back()->with('success', 'Database restored successfully.');
    }

    public function destroy($file)
    {
        $backupPath = storage_path('app/private/backups/'.$file);

        if (File::exists($backupPath)) {
            File::delete($backupPath);

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

    public function upload(Request $request)
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

        return back()->with('success', 'Backup uploaded successfully. You can now restore it.');
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}

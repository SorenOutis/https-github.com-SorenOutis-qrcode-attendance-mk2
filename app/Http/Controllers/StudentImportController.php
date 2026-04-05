<?php

namespace App\Http\Controllers;

use App\ActivityLogger;
use App\Models\Student;
use App\Models\StudentQrToken;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function store(Request $request): Response|JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);

        // Expected headers: name, student_number, email, section, subjects
        $expected = ['name', 'student_number', 'email', 'section', 'subjects'];

        // Simple map to handle different casings or slight variations if needed
        $headerMap = array_change_key_case(array_flip($header), CASE_LOWER);

        $count = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($row) < 2) {
                    continue;
                } // Skip empty rows

                $data = [];
                foreach ($expected as $key) {
                    if (isset($headerMap[$key])) {
                        $data[$key] = $row[$headerMap[$key]] ?? null;
                    }
                }

                if (empty($data['name']) || empty($data['student_number'])) {
                    $errors[] = "Row {$rowNum}: Name and Student Number are required.";

                    continue;
                }

                // We will update or create the student below instead of skipping

                $data['qr_token'] = Str::uuid()->toString();

                // Handle subjects auto-creation and enrollment
                $schedule = [];
                if (! empty($data['subjects'])) {
                    $subjectNames = array_map('trim', explode(',', $data['subjects']));
                    foreach ($subjectNames as $name) {
                        if (empty($name)) {
                            continue;
                        }

                        $subject = Subject::firstOrCreate(
                            ['name' => strtoupper($name)],
                            [
                                'icon' => 'BookOpen',
                                'color' => 'indigo',
                                'description' => 'Auto-created from import.',
                                'schedule' => [],
                            ]
                        );

                        $schedule[] = [
                            'subject_id' => $subject->id,
                            'slot_index' => 0, // Default to first slot if unknown
                        ];
                    }
                }

                $data['schedule'] = $schedule;

                $student = Student::updateOrCreate(
                    ['student_number' => $data['student_number']],
                    [
                        'name' => $data['name'],
                        'email' => $data['email'] ?? null,
                        'section' => $data['section'] ?? null,
                        'schedule' => $schedule,
                        'qr_token' => $data['qr_token'],
                    ]
                );

                StudentQrToken::updateOrCreate(
                    ['student_id' => $student->id],
                    ['token' => $student->qr_token]
                );

                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error during import: '.$e->getMessage());
        }

        fclose($handle);

        ActivityLogger::log(
            'student.import',
            "Imported {$count} students via CSV.",
            ['count' => $count, 'errors' => $errors]
        );

        if (count($errors) > 0) {
            $msg = "Imported {$count} students, but had ".count($errors).' errors.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg, 'errors' => $errors], 200);
            }

            return back()->with('warning', $msg)->with('import_errors', $errors);
        }

        $msg = "Successfully imported {$count} students.";
        if ($request->expectsJson()) {
            return response()->json(['message' => $msg], 200);
        }

        return back()->with('success', $msg);
    }

    public function downloadSample(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "name,student_number,email,section,subjects\n";
            echo "John Doe,2024-001,john@example.com,BSIT-1A,\"Math, Science\"\n";
            echo "Jane Smith,2024-002,jane@example.com,BSIT-1B,\"English, History\"\n";
        }, 'students_sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\ActivityLogger;
use App\Models\Student;
use App\Models\StudentQrToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);

        // Expected headers: name, student_number, email, section
        $expected = ['name', 'student_number', 'email', 'section'];

        // Simple map to handle different casings or slight variations if needed
        $headerMap = array_flip($header);

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

                // Check if student number exists
                if (Student::where('student_number', $data['student_number'])->exists()) {
                    $errors[] = "Row {$rowNum}: Student Number {$data['student_number']} already exists.";

                    continue;
                }

                $data['qr_token'] = Str::uuid()->toString();
                // Default schedule if none provided - we'll just leave it as an empty array or empty for now
                // if the model allows. If not, we'll need to handle it.
                $data['schedule'] = [];

                $student = Student::create($data);

                StudentQrToken::create([
                    'student_id' => $student->id,
                    'token' => $student->qr_token,
                ]);

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
            return back()->with('warning', "Imported {$count} students, but had ".count($errors).' errors.')
                ->with('import_errors', $errors);
        }

        return back()->with('success', "Successfully imported {$count} students.");
    }

    public function downloadSample(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "name,student_number,email,section\n";
            echo "John Doe,2024-001,john@example.com,BSIT-1A\n";
            echo "Jane Smith,2024-002,jane@example.com,BSIT-1B\n";
        }, 'students_sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Excuse;
use App\Models\Student;
use App\Models\StudentQrToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExcuseController extends Controller
{
    /**
     * Store a new excuse from the student portal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $studentId = StudentQrToken::query()
            ->where('token', '=', (string) $validated['token'], 'and')
            ->value('student_id');

        if (! $studentId) {
            return response()->json(['message' => 'Invalid token.'], 403);
        }

        $excuse = Excuse::create([
            'student_id' => (int) $studentId,
            'date' => $validated['date'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'excuse' => $excuse,
        ]);
    }

    /**
     * List all excuses for the teacher (authenticated).
     */
    public function index(): Response
    {
        $excuses = Excuse::query()
            ->with('student:id,name,student_number,section,photo')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20);

        return Inertia::render('Excuses/Index', [
            'excuses' => $excuses,
        ]);
    }

    /**
     * Approve or reject an excuse.
     */
    public function update(Request $request, Excuse $excuse): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected'],
            'teacher_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $excuse->update([
            'status' => (string) $validated['status'],
            'teacher_notes' => (string) ($validated['teacher_notes'] ?? null),
        ]);

        return back()->with('success', 'Excuse updated successfully.');
    }
}

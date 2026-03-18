<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $subjects = Subject::orderBy('name')->get();
        $students = \App\Models\Student::get(['id', 'name', 'student_number', 'schedule']);

        $subjects->transform(function ($subject) use ($students) {
            $enrolledStudents = $students->filter(function ($student) use ($subject) {
                if (!is_array($student->schedule)) return false;
                foreach ($student->schedule as $slot) {
                    if (isset($slot['subject_id']) && $slot['subject_id'] == $subject->id) {
                        return true;
                    }
                }
                return false;
            })->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'student_number' => $s->student_number,
                ];
            })->values();

            $subject->students = $enrolledStudents;
            return $subject;
        });

        return Inertia::render('Subjects/Index', [
            'subjects' => $subjects,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        Subject::create($request->validated());

        return redirect()->back()->with('flash', [
            'subject_created' => true,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()->back()->with('flash', [
            'subject_updated' => true,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return redirect()->back()->with('flash', [
            'subject_deleted' => true,
        ]);
    }
}

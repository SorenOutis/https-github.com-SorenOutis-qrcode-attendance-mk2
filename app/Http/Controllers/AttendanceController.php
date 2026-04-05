<?php

namespace App\Http\Controllers;

use App\ActivityLogger;
use App\Models\Attendance;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Present,Late,Time Out,Absent'],
        ]);

        $attendance->update([
            'status' => $validated['status'],
        ]);

        ActivityLogger::log('attendance.update', "Updated attendance for student: {$attendance->student->name}", ['id' => $attendance->id, 'status' => $validated['status']]);

        return redirect()->back();
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $student = Student::where('qr_token', $validated['token'])->first();

        if (! $student) {
            return response()->json([
                'message' => 'Invalid or expired QR code.',
            ], 404);
        }

        $appTz = config('app.timezone');
        $now = CarbonImmutable::now($appTz);
        $dayOfWeek = $now->format('l'); // Monday, Tuesday, etc.
        $date = $now->toDateString();
        $time = $now->format('H:i');

        $schedule = collect($student->schedule ?? [])
            ->filter(fn ($slot) => isset($slot['day'], $slot['start'], $slot['end']))
            ->filter(fn ($slot) => $slot['day'] === $dayOfWeek)
            ->values();

        if ($schedule->isEmpty()) {
            return response()->json([
                'message' => "No schedule configured for {$student->name} today ($dayOfWeek).",
            ], 422);
        }

        // Count existing attendance for this student today
        $dailyRecords = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get();

        $totalSlots = $schedule->count();
        $recordsCount = $dailyRecords->count();
        $graceMinutes = 15;

        $minutesEarly = null;
        $minutesLate = null;

        // Find the appropriate slot for the current time
        $targetSlot = null;
        $targetIndex = null;
        $status = 'Time Out'; // Default status if no active slot found

        foreach ($schedule as $index => $slot) {
            // Check if this slot was already recorded today
            $alreadyRecorded = $dailyRecords->contains('slot_index', $index);
            if ($alreadyRecorded) {
                continue;
            }

            $slotEnd = CarbonImmutable::parse($date.' '.$slot['end'], $appTz);

            // If the current time is before the end of this slot, it's our candidate
            if ($now->lessThan($slotEnd)) {
                $targetSlot = (array) $slot;
                $targetIndex = $index;
                break;
            }
        }

        if ($targetSlot) {
            $slot = $targetSlot;
            $slotIndex = $targetIndex;
            $start = CarbonImmutable::parse($date.' '.$slot['start'], $appTz);
            $diffMinutes = (int) abs($now->diffInMinutes($start));

            if ($now->lessThan($start->addMinutes($graceMinutes))) {
                $status = 'Present';
                $minutesEarly = $now->lessThan($start) ? $diffMinutes : 0;
            } else {
                $status = 'Late';
                $minutesLate = $diffMinutes;
            }
        } else {
            // If No active slot remains, it's a Time Out scan
            // Check if they've already Timed Out today
            if ($dailyRecords->contains('status', 'Time Out')) {
                return response()->json([
                    'message' => 'You have already completed your attendance (Time Out) for today.',
                ], 422);
            }

            // Use the last slot as metadata reference for the Time Out record
            $slot = (array) $schedule->last();
            $slotIndex = $totalSlots - 1;
            $status = 'Time Out';
        }

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'subject_id' => $slot['subject_id'] ?? null,
            'scanned_at' => $now,
            'status' => $status,
            'slot_index' => $slotIndex,
            'slot_start' => $slot['start'],
            'slot_end' => $slot['end'],
        ]);

        ActivityLogger::log('attendance.scan', "Attendance scanned for student: {$student->name}", [
            'id' => $attendance->id,
            'status' => $status,
            'subject_id' => $slot['subject_id'] ?? null,
        ]);

        $attendance->load('subject');

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'email' => $student->email,
                'section' => $student->section,
            ],
            'attendance' => [
                'id' => $attendance->id,
                'scanned_at' => $attendance->scanned_at,
                'status' => $attendance->status,
                'slot_start' => $attendance->slot_start->format('H:i'),
                'slot_end' => $attendance->slot_end->format('H:i'),
                'minutes_early' => $minutesEarly,
                'minutes_late' => $minutesLate,
                'subject' => $attendance->subject ? [
                    'id' => $attendance->subject->id,
                    'name' => $attendance->subject->name,
                ] : null,
            ],
        ]);
    }
}

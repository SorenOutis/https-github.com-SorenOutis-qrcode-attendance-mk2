<?php

namespace App\Models;

use Database\Factories\ExcuseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Excuse extends Model
{
    /** @use HasFactory<ExcuseFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'attendance_id',
        'date',
        'reason',
        'status',
        'teacher_notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}

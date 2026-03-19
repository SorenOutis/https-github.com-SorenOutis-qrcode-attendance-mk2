<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'subject_id',
        'scanned_at',
        'status',
        'is_manual',
        'remarks',
        'slot_index',
        'slot_start',
        'slot_end',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'is_manual' => 'boolean',
        'slot_start' => 'datetime:H:i',
        'slot_end' => 'datetime:H:i',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}

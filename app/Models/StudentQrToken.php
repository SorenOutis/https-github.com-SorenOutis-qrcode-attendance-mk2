<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentQrToken extends Model
{
    protected $fillable = [
        'student_id',
        'token',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

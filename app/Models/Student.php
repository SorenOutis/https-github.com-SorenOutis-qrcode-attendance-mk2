<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'student_number',
        'email',
        'section',
        'qr_token',
        'schedule',
        'photo',
    ];

    protected $casts = [
        'schedule' => 'array',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(StudentQrToken::class);
    }
}

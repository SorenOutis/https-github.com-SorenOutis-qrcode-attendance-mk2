<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'color',
        'description',
        'schedule',
    ];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyCourse extends Model
{
    use HasFactory;

    protected $table = 'academy_courses';

    protected $fillable = [
        'name',
        'description',
        'instructor_id',
        'category',
        'duration',
        'duration_unit',
        'price',
        'max_students',
        'status',
        'start_date',
        'end_date',
        'level',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'course_id');
    }
}

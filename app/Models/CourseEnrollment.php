<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'student_name',
        'student_email',
        'student_phone',
        'amount_paid',
        'payment_status',
        'enrolled_at',
        'completion_date',
        'certificate_issued',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'enrolled_at' => 'datetime',
            'completion_date' => 'date',
            'certificate_issued' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'course_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentReplacementItem extends Model
{
    protected $fillable = [
        'request_id',
        'original_subject_id',
        'original_schedule_id',
        'selected_schedule_id',
        'equivalency_id',
    ];

    public function replacementRequest()
    {
        return $this->belongsTo(EnrollmentReplacementRequest::class, 'request_id');
    }

    public function originalSubject()
    {
        return $this->belongsTo(CourseSubject::class, 'original_subject_id');
    }

    public function selectedSchedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'selected_schedule_id');
    }

    public function originalSchedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'original_schedule_id');
    }

    public function equivalency()
    {
        return $this->belongsTo(CourseSubjectEquivalency::class, 'equivalency_id');
    }
}

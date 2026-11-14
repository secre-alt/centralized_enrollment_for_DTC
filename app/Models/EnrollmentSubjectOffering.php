<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentSubjectOffering extends Model
{
    protected $fillable = [
        'enrollment_id',
        'curriculum_subject_id',
        'class_schedule_id',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function curriculumSubject()
    {
        return $this->belongsTo(CourseSubject::class, 'curriculum_subject_id');
    }

    public function classSchedule()
    {
        return $this->belongsTo(ClassSchedule::class);
    }
}

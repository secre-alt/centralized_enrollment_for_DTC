<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    protected $fillable = [
        'course_subject_id',
        'hosting_program_id',
        'section_code',
        'school_year',
        'semester',
        'capacity',
        'is_active',
        'requires_irregular',
        'day_pattern',
        'time_start',
        'time_end',
        'room',
        'instructor_name',
    ];

    public function courseSubject()
    {
        return $this->belongsTo(CourseSubject::class);
    }

    public function hostingProgram()
    {
        return $this->belongsTo(Program::class, 'hosting_program_id');
    }

    public function enrollmentSelections()
    {
        return $this->hasMany(EnrollmentSubjectOffering::class);
    }
}
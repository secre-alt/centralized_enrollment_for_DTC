<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSubjectEquivalency extends Model
{
    protected $fillable = [
        'source_subject_id',
        'equivalent_subject_id',
        'approved_by',
        'approval_reason',
        'requires_irregular',
        'approved_at',
    ];

    protected $casts = [
        'requires_irregular' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function sourceSubject()
    {
        return $this->belongsTo(CourseSubject::class, 'source_subject_id');
    }

    public function equivalentSubject()
    {
        return $this->belongsTo(CourseSubject::class, 'equivalent_subject_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

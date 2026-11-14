<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentReplacementRequest extends Model
{
    protected $fillable = [
        'enrollment_id',
        'requested_by',
        'status',
        'reason',
        'baseline_is_irregular',
        'student_reason',
        'submitted_at',
        'resolved_at',
    ];

    protected $casts = [
        'baseline_is_irregular' => 'boolean',
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items()
    {
        return $this->hasMany(EnrollmentReplacementItem::class, 'request_id');
    }
}

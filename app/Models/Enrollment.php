<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'program_id',
        'year_level',
        'semester',
        'school_year',
        'subject_ids',
        'status',
        'remarks',
        'is_paid',
    ];

    protected $casts = [
        'subject_ids' => 'array',
        'is_paid' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function payment()
    {
        // Always resolve the latest payment attempt.
        // This handles walk-in payments, GCash submissions,
        // rejected payments, and subsequent resubmissions.
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
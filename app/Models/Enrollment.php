<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'user_id', 'program_id', 'year_level', 'semester',
        'subject_ids', 'status', 'remarks', 'is_paid',
    ];

    protected $casts = [
        'subject_ids' => 'array',
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
        // latestOfMany() so "the enrollment's payment" always resolves to
        // its most recent attempt (walk-in, GCash re-submission after a
        // rejection, etc.) instead of an arbitrary row — a plain hasOne()
        // returns whichever row the DB hands back first, which caused the
        // payments table to show stale status/fee data for enrollments
        // with more than one payment record.
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
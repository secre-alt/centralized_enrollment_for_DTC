<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * StudentProfile — student-specific institutional identity.
 *
 * Separates authentication identity (User) from academic identity.
 *
 * users            = account / authentication / role
 * student_profiles = student number, LRN, admission type, program, etc.
 *
 * New fields (migration 2026_09_01_000001):
 *   enrolled_ay      — AY associated with the student's first enrollment.
 *                      Set once, never changed.
 *   graduation_year  — calendar year the student graduated; e.g. 2026.
 *                      Set by Registrar on alumni promotion.
 */
class StudentProfile extends Model
{
    public const MAX_YEAR_LEVEL = 4;

    protected $fillable = [
        'user_id',
        'student_number',
        'lrn',
        'admission_type',
        'gender',
        'birthdate',
        'phone',
        'address',
        'program_id',
        'enrolled_ay',
        'graduation_year',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'year_level',
        'academic_status',
        'graduation_date',
    ];

    protected $casts = [
        'birthdate'       => 'date',
        'graduation_year' => 'integer',
        'year_level'      => 'integer',
        'graduation_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function portalInvitation()
    {
        return $this->hasOne(PortalAccountInvitation::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public static function generateStudentNumber(): string
    {
        $year   = date('Y');
        $prefix = $year . '-';

        $last = static::where('student_number', 'like', $prefix . '%')
            ->orderByDesc('student_number')
            ->value('student_number');

        $seq = $last
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public static function createForEnrollmentIfMissing(User $user, Enrollment $enrollment): void
    {
        if ($user->studentProfile()->exists()) {
            return;
        }

        $application = Application::where('user_id', $user->id)
            ->where('status', 'approved')
            ->latest()
            ->first();

        static::create([
            'user_id'        => $user->id,
            'student_number' => static::generateStudentNumber(),
            'lrn'            => $application?->lrn,
            'admission_type' => $application?->academic_status ?? 'new_student',
            'gender'         => $application?->gender,
            'birthdate'      => $application?->birthdate,
            'phone'          => $application?->phone,
            'address'        => $application?->current_address ?? $application?->address,
            'program_id'     => $enrollment->program_id,
            'enrolled_ay'    => $enrollment->school_year,
        ]);
    }
}

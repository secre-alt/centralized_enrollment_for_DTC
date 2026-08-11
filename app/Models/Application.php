<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $fillable = [
        'reference_no',
        'program_id',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'phone',
        'address',
        'birthdate',
        'guardian_name',
        'guardian_contact',
        'status',
        'reviewed_by',
        'reviewed_at',
        'remarks',
        'user_id',
        'academic_status',
        'gender',
        'birth_place',
        'religion',
        'nationality',
        'lrn',
        'marital_status',
        'current_address',
        'city',
        'province',
        'postal_code',
        'country',
        'father_name',
        'father_occupation',
        'mother_name',
        'mother_occupation',
        'parent_address',
        'parent_contact',
        'spouse_name',
        'occupation',
        'disability',
        'pwd_id',
    ];

    protected $casts = [
        'birthdate'   => 'date',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Applicant's current age, calculated from birthdate. Not stored in the database.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birthdate?->age;
    }

    /**
     * The program the applicant applied for.
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * The account created for this applicant once approved (nullable until then).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The Registrar/Admin user who reviewed this application (nullable until reviewed).
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Uploaded supporting documents for this application.
     */
    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }
}
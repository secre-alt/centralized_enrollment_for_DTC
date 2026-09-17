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

    /**
     * Get the application progress timeline for student-facing display.
     * Maps existing statuses to a visual progress pipeline.
     */
    public function getProgressTimeline(): array
    {
        $timeline = [
            [
                'key' => 'submitted',
                'label' => 'Application Submitted',
                'description' => 'Your application has been received and is awaiting initial review.',
                'status' => 'completed',
                'timestamp' => $this->created_at,
            ],
            [
                'key' => 'under_review',
                'label' => 'Initial Review',
                'description' => 'Your application is being reviewed by the Registrar.',
                'status' => 'pending',
                'timestamp' => null,
            ],
            [
                'key' => 'document_verification',
                'label' => 'Document Verification',
                'description' => 'Supporting documents are being verified.',
                'status' => 'pending',
                'timestamp' => null,
            ],
            [
                'key' => 'final_approval',
                'label' => 'Final Approval',
                'description' => 'Final review and approval decision.',
                'status' => 'pending',
                'timestamp' => null,
            ],
            [
                'key' => 'enrollment_eligibility',
                'label' => 'Enrollment Eligibility',
                'description' => 'Account activation and enrollment access.',
                'status' => 'pending',
                'timestamp' => null,
            ],
        ];

        // Map current application status to timeline progress
        switch ($this->status) {
            case 'submitted':
                // Only first step completed
                $timeline[0]['status'] = 'completed';
                $timeline[1]['status'] = 'current';
                break;

            case 'under_review':
                // First two steps in progress
                $timeline[0]['status'] = 'completed';
                $timeline[1]['status'] = 'current';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[2]['status'] = 'pending';
                break;

            case 'revision_required':
                // Document verification failed, needs action
                $timeline[0]['status'] = 'completed';
                $timeline[1]['status'] = 'completed';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[2]['status'] = 'action_required';
                $timeline[2]['description'] = 'Document correction required. Please review the remarks below.';
                break;

            case 'approved':
                // All steps completed
                $timeline[0]['status'] = 'completed';
                $timeline[1]['status'] = 'completed';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[2]['status'] = 'completed';
                $timeline[2]['timestamp'] = $this->reviewed_at;
                $timeline[3]['status'] = 'completed';
                $timeline[3]['timestamp'] = $this->reviewed_at;
                $timeline[4]['status'] = 'completed';
                $timeline[4]['timestamp'] = $this->reviewed_at;
                break;

            case 'rejected':
                // Process stopped at review stage
                $timeline[0]['status'] = 'completed';
                $timeline[1]['status'] = 'rejected';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[1]['description'] = 'Application was not approved. Please review the remarks below.';
                // Mark remaining steps as skipped
                for ($i = 2; $i < count($timeline); $i++) {
                    $timeline[$i]['status'] = 'skipped';
                }
                break;
        }

        return $timeline;
    }

    /**
     * Get the current status description for students.
     */
    public function getStatusDescription(): string
    {
        return match ($this->status) {
            'submitted' => 'Your application has been submitted and is awaiting initial review.',
            'under_review' => 'Your application is currently being reviewed by the Registrar. No action is needed from you at this time.',
            'revision_required' => 'Your application requires document corrections. Please review the remarks and take the necessary action.',
            'approved' => 'Congratulations! Your application has been approved. You can now proceed with enrollment.',
            'rejected' => 'Your application was not approved. Please review the remarks and contact the Registrar if you have questions.',
            default => 'Application status unknown.',
        };
    }

    /**
     * Check if the application requires student action.
     */
    public function requiresAction(): bool
    {
        return $this->status === 'revision_required';
    }
}
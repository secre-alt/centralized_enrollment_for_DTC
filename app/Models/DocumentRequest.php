<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected static function booted(): void
    {
        static::creating(function ($document) {
            $document->payment_appointment_id = $document->appointment_id;
        });
        static::updating(function ($document) {
            $origin = $document->getRawOriginal('payment_appointment_id');
            if ($origin !== null && $document->isDirty('payment_appointment_id')) {
                throw new \LogicException('The original document collection identity cannot be changed.');
            }
            // Capture the exact existing link before scheduling changes; never infer a historical match.
            if ($origin === null && $document->isDirty('appointment_id')) {
                $document->payment_appointment_id = $document->getRawOriginal('appointment_id');
            }
        });
    }

    public function getPaymentAppointmentIdAttribute($value)
    {
        return $value ?? $this->appointment_id;
    }

    public function paymentAppointment()
    {
        return $this->belongsTo(Appointment::class, 'payment_appointment_id');
    }

    public function pendingClaimAppointment()
    {
        return $this->belongsTo(Appointment::class, 'pending_claim_appointment_id');
    }

    public function appointmentItems()
    {
        return $this->hasMany(AppointmentDocumentItem::class);
    }

    public function usesClaimingWorkflow(): bool
    {
        return DocumentWorkflowEvent::where('action', 'documents.submitted')
            ->whereJsonContains('document_request_ids', $this->id)->exists()
            || $this->appointmentItems()->whereHas('appointment', fn ($query) => $query->whereIn('visit_type', ['submission', 'claiming']))->exists();
    }

    public function scopeForPaymentAppointment($query, int $appointmentId)
    {
        return $query->whereRaw('COALESCE(document_requests.payment_appointment_id, document_requests.appointment_id) = ?', [$appointmentId]);
    }

    protected $fillable = [
        'user_id', 'appointment_id', 'document_type',
        'copies', 'purpose', 'status', 'fee', 'is_paid', 'assessment_required', 'assessment_status',
        'receipt_no', 'remarks', 'payment_method', 'payment_status',
        'amount_received', 'reference_number', 'proof_of_payment',
        'verified_by', 'verified_at', 'payment_submitted_at', 'payment_rejection_reason',
        'rejected_by', 'rejected_at',
    ];

    protected $casts = [
        'assessment_required' => 'boolean',
        'assessment_version' => 'integer',
        'assessed_fee' => 'decimal:2',
        'assessed_at' => 'datetime',
        'released_at' => 'datetime',
        'is_paid' => 'boolean',
        'fee' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'verified_at' => 'datetime',
        'payment_submitted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function assessmentSatisfied(): bool
    {
        return ! $this->assessment_required || ($this->assessment_status === 'complete'
            && $this->assessed_at !== null
            && $this->assessed_fee !== null && $this->assessed_fee === $this->fee);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function usesBundledPayment(): bool
    {
        return $this->payment_appointment_id && AppointmentPayment::where('appointment_id', $this->payment_appointment_id)
            ->where('is_document_bundle', true)->exists();
    }

    public function paymentNotRequired(): bool
    {
        return $this->fee !== null && (float) $this->fee === 0.0 && !$this->is_paid && $this->payment_status !== 'verified';
    }

    public function paymentSatisfied(): bool
    {
        return $this->paymentNotRequired() || ($this->is_paid && $this->payment_status === 'verified');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function paymentProofs()
    {
        return $this->hasMany(DocumentPaymentProof::class);
    }

    public function getDocumentLabelAttribute(): string
    {
        return match($this->document_type) {
            'tor'          => 'Transcript of Records',
            'diploma'      => 'Diploma',
            'certification'=> 'Certification',
            'true_copy'    => 'True Copy of Records',
            default        => ucfirst($this->document_type),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'submitted'  => '#F59E0B',
            'processing' => '#3B82F6',
            'ready'      => '#8B5CF6',
            'released'   => '#22C55E',
            'cancelled'  => '#DC2626',
            default      => '#94A3B8',
        };
    }
}

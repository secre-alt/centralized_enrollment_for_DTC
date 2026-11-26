<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentPaymentProof extends Model
{
    protected $fillable = [
        'document_request_id', 'appointment_payment_id', 'path', 'reference_number',
        'submitted_by', 'submitted_at', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function documentRequest()
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function appointmentPayment()
    {
        return $this->belongsTo(AppointmentPayment::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

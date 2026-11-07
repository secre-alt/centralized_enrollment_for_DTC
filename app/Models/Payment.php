<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'payment_method',
        'reference_number',
        'proof_of_payment',
        'status',
        'rejection_reason',
        'refund_status',
        'refund_amount',
        'refund_reference',
        'refunded_by',
        'refunded_at',
        'processed_by',
        'verified_by',
        'verified_at',
        'amount',
        'receipt_no',
        'paid_at',
    ];

    protected $casts = [
        'paid_at'     => 'datetime',
        'verified_at' => 'datetime',
        'refunded_at' => 'datetime',
        'amount'      => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function refundConfirmer()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function scopeOutstandingForTerm(Builder $query, Enrollment $enrollment): Builder
    {
        return $query
            ->where(function (Builder $payments) {
                $payments->where('status', 'pending')
                    ->orWhere(function (Builder $verified) {
                        $verified->where('status', 'verified')
                            ->where('refund_status', '!=', 'refunded');
                    });
            })
            ->whereHas('enrollment', fn (Builder $related) => $related
                ->where('user_id', $enrollment->user_id)
                ->where('school_year', $enrollment->school_year)
                ->where('semester', $enrollment->semester)
                ->where('id', '!=', $enrollment->id));
    }

    public function scopeUnresolvedRefundsForTerm(Builder $query, Enrollment $enrollment): Builder
    {
        return $query
            ->where('status', 'verified')
            ->where('refund_status', 'pending')
            ->whereHas('enrollment', fn (Builder $related) => $related
                ->where('user_id', $enrollment->user_id)
                ->where('school_year', $enrollment->school_year)
                ->where('semester', $enrollment->semester)
                ->where('status', 'rejected'));
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function refundIsPending(): bool
    {
        return $this->refund_status === 'pending';
    }

    public function refundIsComplete(): bool
    {
        return $this->refund_status === 'refunded';
    }

    public function isGcash(): bool
    {
        return $this->payment_method === 'gcash';
    }

    public function isWalkIn(): bool
    {
        return $this->payment_method === 'walk_in';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentWorkflowEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'document_request_ids' => 'array',
        'before' => 'array',
        'after' => 'array',
        'outcome' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Document workflow events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Document workflow events are append-only.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

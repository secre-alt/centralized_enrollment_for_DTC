<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentLifecycleEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['document_item_ids' => 'array', 'before' => 'array', 'after' => 'array',
        'outcome' => 'array', 'created_at' => 'datetime', 'previous_version' => 'integer', 'resulting_version' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Lifecycle events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Lifecycle events are append-only.'));
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

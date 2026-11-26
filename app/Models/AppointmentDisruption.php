<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentDisruption extends Model
{
    protected $fillable = [
        'appointment_id', 'office_closure_id', 'status', 'notification_key',
        'notification_status', 'notification_attempts', 'notification_available_at',
        'notification_last_error', 'notified_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'notification_attempts' => 'integer',
        'notification_available_at' => 'datetime',
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function officeClosure()
    {
        return $this->belongsTo(OfficeClosure::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

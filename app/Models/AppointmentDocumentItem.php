<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentDocumentItem extends Model
{
    protected $fillable = ['appointment_id', 'document_request_id', 'released_at', 'released_by'];

    protected $casts = ['released_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function documentRequest()
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}

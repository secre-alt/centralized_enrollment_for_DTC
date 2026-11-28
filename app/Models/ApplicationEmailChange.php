<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationEmailChange extends Model
{
    protected $fillable = [
        'application_id',
        'old_email',
        'new_email',
        'token_hash',
        'expires_at',
        'verified_at',
        'previous_remarks',
        'previous_reviewed_by',
        'previous_reviewed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'previous_reviewed_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}

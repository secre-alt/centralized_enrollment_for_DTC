<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableDraft extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['configuration' => 'array', 'proposals' => 'array', 'is_demo' => 'boolean', 'published_at' => 'datetime'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}

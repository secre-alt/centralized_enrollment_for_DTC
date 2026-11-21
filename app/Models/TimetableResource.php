<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableResource extends Model
{
    protected $fillable = ['kind', 'name', 'configuration'];

    protected $casts = ['configuration' => 'array'];
}

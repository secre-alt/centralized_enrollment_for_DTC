<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'instructions', 'eligible_roles', 'is_active', 'requires_approval', 'sort_order'];

    protected $casts = ['eligible_roles' => 'array', 'is_active' => 'boolean', 'requires_approval' => 'boolean'];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}

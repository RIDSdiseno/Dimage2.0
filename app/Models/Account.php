<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'accounts';

    protected $fillable = ['patient_id', 'clinic_id', 'staff_id', 'estado', 'diagnostico', 'observaciones', 'detalle', 'creador_id', 'respuesta'];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

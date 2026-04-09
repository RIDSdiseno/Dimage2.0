<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'patient_id', 'clinic_id', 'odontologo_id', 'radiologo_id',
        'diagnostico', 'observaciones', 'observaciones_2', 'prioridad',
        'estadoradiologo', 'estadoodontologo', 'enviada', 'respondida',
        'sin_diagnostico', 'trx_number', 'radiologo_explicito',
    ];

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'order_staff', 'order_id', 'staff_id');
    }

    public function examinations()
    {
        return $this->belongsToMany(Examination::class, 'examination_order', 'order_id', 'examination_id');
    }

    public function odontologo()
    {
        return $this->belongsTo(Staff::class, 'odontologo_id')->with('user');
    }

    public function radiologo()
    {
        return $this->hasOne(Staff::class, 'id', 'radiologo_id')->with('user');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id')->with('user');
    }

    public function correction()
    {
        return $this->hasOne(Correction::class);
    }

    public function account()
    {
        return $this->hasOne(Account::class);
    }
}

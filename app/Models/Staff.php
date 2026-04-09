<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $table = 'staffs';

    protected $hidden = ['lat', 'long', 'dentist_id', 'externo', 'id_externo'];

    protected $fillable = [
        'user_id',
        'rut',
        'name',
        'specialty',
        'firma',
        'externo',
        'id_externo',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class);
    }

    public function kinds()
    {
        return $this->belongsToMany(Kind::class);
    }

    public function ordersAsOdontologo()
    {
        return $this->hasMany(Order::class, 'odontologo_id');
    }

    public function ordersAsRadiologo()
    {
        return $this->hasMany(Order::class, 'radiologo_id');
    }
}

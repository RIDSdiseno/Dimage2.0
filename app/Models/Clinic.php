<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    protected $table = 'clinics';

    protected $hidden = ['pivot'];

    protected $fillable = ['name', 'user_id', 'holding_id', 'address', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function holding()
    {
        return $this->belongsTo(Holding::class);
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class);
    }

    public function patients()
    {
        return $this->belongsToMany(Patient::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

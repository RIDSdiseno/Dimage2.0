<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $table = 'patients';

    protected $fillable = [
        'id_externo',
        'name',
        'housephone',
        'celphone',
        'workphone',
        'address',
        'lat',
        'long',
        'dateofbirth',
        'tutorname',
        'tutorrelation',
        'rut',
        'email',
        'derivado_de',
    ];

    protected $hidden = ['lat', 'long'];

    protected function casts(): array
    {
        return [
            'dateofbirth' => 'date',
        ];
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'clinic_patient');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Búsqueda por nombre o rut
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('rut', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }
}

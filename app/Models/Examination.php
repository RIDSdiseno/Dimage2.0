<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Examination extends Model
{
    protected $table = 'examinations';
    protected $hidden = ['pivot'];

    protected $fillable = ['name', 'kind_id', 'description', 'group'];

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'examination_order', 'examination_id', 'order_id');
    }

    public function archivos()
    {
        return $this->hasMany(Archivo::class);
    }

    public function archivo()
    {
        return $this->hasOne(Archivo::class);
    }

    public function answer()
    {
        return $this->hasOne(Answer::class);
    }
}

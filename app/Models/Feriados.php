<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriados extends Model
{
    protected $table = 'feriados';
    public $timestamps = false;

    protected $fillable = ['descripcion', 'fecha'];
}

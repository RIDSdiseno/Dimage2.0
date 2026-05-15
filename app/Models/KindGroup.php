<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KindGroup extends Model
{
    protected $table = 'kind_groups';
    protected $fillable = ['nombre', 'tab', 'orden'];

    public function kinds()
    {
        return $this->hasMany(Kind::class, 'group', 'id');
    }
}

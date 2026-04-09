<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kind extends Model
{
    protected $table = 'kinds';
    protected $hidden = ['pivot'];

    protected $fillable = ['name', 'description'];

    public function staff()
    {
        return $this->belongsToMany(Staff::class);
    }
}

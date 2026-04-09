<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Archive extends Model
{
    protected $table = 'archives';

    protected $fillable = ['account_id', 'filename', 'path', 'disk'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}

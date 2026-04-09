<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Correction extends Model
{
    protected $table = 'corrections';

    protected $fillable = ['order_id', 'staff_id', 'description', 'status'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

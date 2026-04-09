<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaminationOrder extends Model
{
    protected $table = 'examination_order';

    protected $fillable = ['examination_id', 'order_id'];
}

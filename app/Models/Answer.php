<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $table = 'answers';

    protected $fillable = ['examination_id', 'content', 'staff_id'];

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }

    public function radiologo()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}

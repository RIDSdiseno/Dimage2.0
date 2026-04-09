<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoldingApikey extends Model
{
    protected $table = 'holding_apikey';

    protected $fillable = ['holding_id', 'apikey', 'descripcion', 'activo'];

    protected $hidden = ['apikey'];

    public function holding()
    {
        return $this->belongsTo(Holding::class);
    }

    public function generaApikey(): string
    {
        $this->apikey = bin2hex(random_bytes(32));
        return $this->apikey;
    }
}

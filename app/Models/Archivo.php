<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Archivo extends Model
{
    protected $table = 'files';

    protected $fillable = [
        'examination_id',
        'filename',
        'original_name',
        'file_size',
        'mime_type',
        'disk',
        'path',
        'tipo_examen',
    ];

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }

    public static function traeEspacioUso(int $holdingId, Carbon $fecha = null): int
    {
        $query = DB::table('files')
            ->select(DB::raw('COALESCE(SUM(file_size), 0) as total'))
            ->join('examinations', 'examinations.id', '=', 'files.examination_id')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('orders', 'orders.id', '=', 'examination_order.order_id')
            ->join('clinics', 'clinics.id', '=', 'orders.clinic_id')
            ->where('clinics.holding_id', $holdingId);

        if ($fecha !== null) {
            $query->where(
                'files.created_at',
                '>=',
                $fecha->startOfMonth()->format('Y-m-d H:i:s')
            );
        }

        return (int) $query->value('total');
    }
}

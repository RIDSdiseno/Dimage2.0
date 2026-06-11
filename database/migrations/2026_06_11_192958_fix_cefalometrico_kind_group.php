<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // kind_id=28 "Análisis Cefalométrico" (con_trazados=1) estaba en group=5
        // que no existe en kind_groups. Lo movemos a group=3 (Examen 2D, extraorales).
        DB::table('kinds')->where('id', 28)->where('group', '5')
            ->update(['group' => '3']);

        // kind_id=25 "Archivos Clínicos" también estaba en group=5 sin group válido.
        DB::table('kinds')->where('id', 25)->where('group', '5')
            ->update(['group' => '3']);
    }

    public function down(): void
    {
        DB::table('kinds')->where('id', 28)->update(['group' => '5']);
        DB::table('kinds')->where('id', 25)->update(['group' => '5']);
    }
};

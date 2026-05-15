<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // "Resonancia Nuclear Magnética" → "Cone Beam por Zona o Diente" (legacy name)
        DB::table('kinds')->where('id', 55)->update(['descipcion' => 'Cone Beam por Zona o Diente']);

        // "Análisis Cefalométrico" was in group 5 — belongs in Extraoral 2D (group 3)
        DB::table('kinds')->where('id', 57)->update(['group' => '3', 'descipcion' => 'Análisis Cefalométrico']);

        // Fix typos in Telerradiografía names
        DB::table('kinds')->where('id', 45)->update(['descipcion' => 'Telerradiografía Perfil']);
        DB::table('kinds')->where('id', 46)->update(['descipcion' => 'Telerradiografía AP']);
        DB::table('kinds')->where('id', 47)->update(['descipcion' => 'Telerradiografía PA']);
    }

    public function down(): void
    {
        DB::table('kinds')->where('id', 55)->update(['descipcion' => 'Resonancia Nuclear Magnética']);
        DB::table('kinds')->where('id', 57)->update(['group' => '5', 'descipcion' => 'Análisis Cefalométrico']);
        DB::table('kinds')->where('id', 45)->update(['descipcion' => 'Telerradiografia Perfil']);
        DB::table('kinds')->where('id', 46)->update(['descipcion' => 'Telerradiograf AP']);
        DB::table('kinds')->where('id', 47)->update(['descipcion' => 'Telerradiograf PA']);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kind_groups', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tab'); // 'intraorales' | 'extraorales'
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        // Migrate existing hardcoded groups into the table
        DB::table('kind_groups')->insert([
            ['id' => 1, 'nombre' => 'Examen Adultos',  'tab' => 'intraorales', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nombre' => 'Examen Niños',    'tab' => 'intraorales', 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nombre' => 'Examen 2D',       'tab' => 'extraorales', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'nombre' => 'Examen 3D',       'tab' => 'extraorales', 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kind_groups');
    }
};

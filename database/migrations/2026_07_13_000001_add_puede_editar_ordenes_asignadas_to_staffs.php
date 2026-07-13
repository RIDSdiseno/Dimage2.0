<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            $table->boolean('puede_editar_ordenes_asignadas')->default(false)->after('puede_ver_menu_busqueda');
        });
    }

    public function down(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            $table->dropColumn('puede_editar_ordenes_asignadas');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE feriados ADD COLUMN pais VARCHAR(2) NOT NULL DEFAULT 'CL'");
        DB::statement("UPDATE feriados SET pais = 'CL'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE feriados DROP COLUMN pais");
    }
};

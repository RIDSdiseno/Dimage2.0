<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            for ($i = 1; $i <= 9; $i++) {
                if (! Schema::hasColumn('answers', "campo_{$i}")) {
                    $table->longText("campo_{$i}")->nullable();
                }
            }
            foreach ([
                11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,
                31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,
                51,52,53,54,55,61,62,63,64,65,
                71,72,73,74,75,81,82,83,84,85,
            ] as $d) {
                if (! Schema::hasColumn('answers', "diente_{$d}")) {
                    $table->longText("diente_{$d}")->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            for ($i = 1; $i <= 9; $i++) {
                if (Schema::hasColumn('answers', "campo_{$i}")) {
                    $table->dropColumn("campo_{$i}");
                }
            }
        });
    }
};

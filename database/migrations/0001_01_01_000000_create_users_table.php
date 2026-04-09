<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('username')->nullable();
                $table->string('telephone')->nullable();
                $table->string('photo')->nullable();
                $table->unsignedTinyInteger('type_id')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        } else {
            // Agregar columnas faltantes si la tabla ya existe
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'username')) {
                    $table->string('username')->nullable()->after('email');
                }
                if (!Schema::hasColumn('users', 'telephone')) {
                    $table->string('telephone')->nullable()->after('username');
                }
                if (!Schema::hasColumn('users', 'photo')) {
                    $table->string('photo')->nullable()->after('telephone');
                }
                if (!Schema::hasColumn('users', 'type_id')) {
                    $table->unsignedTinyInteger('type_id')->nullable()->after('photo');
                }
                // email_verified_at omitido — MySQL strict mode en tabla con timestamps existentes
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

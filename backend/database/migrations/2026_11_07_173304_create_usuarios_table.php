<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('correo', 150)->unique();
            $table->string('contrasena_hash', 255);
            $table->string('rol', 15);
            $table->foreignId('centro_id')->nullable()->constrained('centros')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestampTz('creado_en')->useCurrent();
        });

        DB::statement("ALTER TABLE usuarios ADD CONSTRAINT usuarios_rol_check CHECK (rol IN ('JOVEN','ORIENTADOR','CENTRO','ADMIN'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
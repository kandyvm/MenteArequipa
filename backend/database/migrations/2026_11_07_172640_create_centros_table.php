<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('tipo', 15);
            $table->string('distrito', 60);
            $table->string('direccion', 200);
            $table->decimal('latitud', 9, 6)->nullable();
            $table->decimal('longitud', 9, 6)->nullable();
            $table->text('servicios')->nullable();
            $table->boolean('activo')->default(true);
        });

        DB::statement("ALTER TABLE centros ADD CONSTRAINT centros_tipo_check CHECK (tipo IN ('MINSA','ESSALUD','ONG','PRIVADO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};
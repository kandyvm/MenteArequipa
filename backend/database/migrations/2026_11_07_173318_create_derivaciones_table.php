<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('derivaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->restrictOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('orientador_id')->constrained('usuarios')->restrictOnDelete();
            $table->smallInteger('prioridad');
            $table->text('observaciones')->nullable();
            $table->string('estado', 10)->default('PENDIENTE');
            $table->timestampTz('derivada_en')->useCurrent();
            $table->timestampTz('aceptada_en')->nullable();

            $table->index('estado');
            $table->index('centro_id');
        });

        DB::statement("ALTER TABLE derivaciones ADD CONSTRAINT derivaciones_prioridad_check CHECK (prioridad IN (1,3,5))");
        DB::statement("ALTER TABLE derivaciones ADD CONSTRAINT derivaciones_estado_check CHECK (estado IN ('PENDIENTE','ACEPTADA','RECHAZADA','ATENDIDA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('derivaciones');
    }
};
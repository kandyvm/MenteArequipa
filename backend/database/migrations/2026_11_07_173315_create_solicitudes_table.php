<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();
            $table->foreignId('joven_id')->constrained('jovenes')->restrictOnDelete();
            $table->string('motivo', 40);
            $table->string('descripcion', 500);
            $table->string('nivel_riesgo', 6);
            $table->string('canal', 10);
            $table->string('estado', 12)->default('REGISTRADA');
            $table->boolean('derivada_113')->default(false);
            $table->timestampTz('creada_en')->useCurrent();

            $table->index('estado');
            $table->index('nivel_riesgo');
        });

        DB::statement("ALTER TABLE solicitudes ADD CONSTRAINT solicitudes_nivel_check CHECK (nivel_riesgo IN ('BAJO','MEDIO','ALTO','CRISIS'))");
        DB::statement("ALTER TABLE solicitudes ADD CONSTRAINT solicitudes_canal_check CHECK (canal IN ('WEB','PRESENCIAL'))");
        DB::statement("ALTER TABLE solicitudes ADD CONSTRAINT solicitudes_estado_check CHECK (estado IN ('REGISTRADA','EN_REVISION','DERIVADA','ACEPTADA','ATENDIDA','CERRADA','CANCELADA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
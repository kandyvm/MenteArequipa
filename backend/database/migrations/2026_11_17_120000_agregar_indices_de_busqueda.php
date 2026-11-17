<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las búsquedas más frecuentes: solicitudes de un joven, derivaciones de una
 * solicitud y seguimientos de una derivación. PostgreSQL no los crea solos para las claves
 * foráneas. Solo agrega índices: no cambia ni borra datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', fn (Blueprint $table) => $table->index('joven_id'));
        Schema::table('derivaciones', fn (Blueprint $table) => $table->index('solicitud_id'));
        Schema::table('seguimientos', fn (Blueprint $table) => $table->index('derivacion_id'));
    }

    public function down(): void
    {
        Schema::table('solicitudes', fn (Blueprint $table) => $table->dropIndex(['joven_id']));
        Schema::table('derivaciones', fn (Blueprint $table) => $table->dropIndex(['solicitud_id']));
        Schema::table('seguimientos', fn (Blueprint $table) => $table->dropIndex(['derivacion_id']));
    }
};

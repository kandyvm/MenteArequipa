<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('accion', 30);
            $table->string('entidad', 30);
            $table->unsignedBigInteger('entidad_id');
            $table->timestampTz('creado_en')->useCurrent();
        });

        DB::statement("ALTER TABLE auditoria ADD COLUMN ip INET NOT NULL");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION auditoria_inmutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'La tabla auditoria es de solo lectura: no se permite % ', TG_OP;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER auditoria_sin_update_delete
            BEFORE UPDATE OR DELETE ON auditoria
            FOR EACH ROW EXECUTE FUNCTION auditoria_inmutable();
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS auditoria_sin_update_delete ON auditoria");
        DB::unprepared("DROP FUNCTION IF EXISTS auditoria_inmutable()");
        Schema::dropIfExists('auditoria');
    }
};
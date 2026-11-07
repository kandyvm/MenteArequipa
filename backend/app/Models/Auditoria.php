<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'accion', 'entidad', 'entidad_id', 'ip'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Registra quién hizo qué y desde qué IP. Solo inserta: la tabla es inmutable. */
    public static function registrar(string $accion, string $entidad, int $entidadId): void
    {
        static::create([
            'usuario_id' => request()->user()?->id,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'ip' => request()->ip() ?? '127.0.0.1',
        ]);
    }
}
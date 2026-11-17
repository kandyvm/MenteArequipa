<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = ['correo', 'contrasena_hash', 'rol', 'centro_id', 'activo'];

    protected $hidden = ['contrasena_hash'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** El correo se guarda siempre en minúsculas y sin espacios: Joven@Correo.pe y joven@correo.pe son la misma cuenta. */
    protected function correo(): Attribute
    {
        return Attribute::make(set: fn ($valor) => is_string($valor) ? mb_strtolower(trim($valor)) : $valor);
    }

    /** ¿Ya hay una cuenta con este correo, sin importar mayúsculas? (también encuentra cuentas antiguas guardadas con mayúsculas) */
    public static function correoEnUso(string $correo, ?int $ignorarId = null): bool
    {
        return static::whereRaw('LOWER(correo) = ?', [mb_strtolower(trim($correo))])
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }

    // Laravel busca la contraseña en este campo
    public function getAuthPassword()
    {
        return $this->contrasena_hash;
    }

    public function joven()
    {
        return $this->hasOne(Joven::class, 'usuario_id');
    }

    public function centro()
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }
}

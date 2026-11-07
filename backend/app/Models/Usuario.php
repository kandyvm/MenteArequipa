<?php

namespace App\Models;

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
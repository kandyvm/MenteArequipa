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
}
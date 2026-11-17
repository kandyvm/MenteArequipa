<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consentimiento extends Model
{
    protected $table = 'consentimientos';

    public $timestamps = false;

    /**
     * Versión del texto de consentimiento que se muestra en el registro (RegistroView.vue).
     * Si cambia el texto, se sube la versión aquí: así se sabe qué texto aceptó cada joven.
     * v1.1: se agrega el correo de contacto para ejercer los derechos ARCO.
     */
    public const VERSION_ACTUAL = 'v1.1';

    protected $fillable = ['joven_id', 'version_texto', 'ip', 'user_agent'];

    public function joven()
    {
        return $this->belongsTo(Joven::class, 'joven_id');
    }
}

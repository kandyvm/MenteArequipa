<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consentimiento extends Model
{
    protected $table = 'consentimientos';
    public $timestamps = false;

    protected $fillable = ['joven_id', 'version_texto', 'ip', 'user_agent'];

    public function joven()
    {
        return $this->belongsTo(Joven::class, 'joven_id');
    }
}
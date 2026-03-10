<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Series extends Model
{
    protected $table = "series";
    protected $primaryKey = 'id_serie';
    protected $fillable = ['nombre_serie', 'longitud_codigo', 'longitud_codigo_grafitext', 'id_seriePadre'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class histCodigosSon extends Model
{
    use HasFactory;
    protected $table = 'hist_codigos_son';
    protected $fillable = [
        'codigo',
        'observacion',
        'descripcion',
        'id_devolucion_header',
        'id_libro',
        'user_created',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncuestaProlipa extends Model
{
    use HasFactory;
    protected $table = 'encuesta_prolipa_encuestas';

    protected $fillable = [
        'codigo',
        'titulo',
        'descripcion',
        'created_by',
        'estado'
    ];

    public function creador()
    {
        return $this->belongsTo(\App\Models\Usuario::class, 'created_by', 'idusuario');
    }

    public function preguntas()
    {
        return $this->hasMany(EncuestaPreguntaProlipa::class, 'encuesta_id');
    }

    public function respuestas()
    {
        return $this->hasMany(\App\Models\EncuestaRespuestaProlipa::class, 'encuesta_id');
    }
}

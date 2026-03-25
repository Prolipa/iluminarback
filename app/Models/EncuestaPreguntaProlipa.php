<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncuestaPreguntaProlipa extends Model
{
    use HasFactory;
    protected $table = 'encuesta_prolipa_preguntas';

    protected $fillable = [
        'encuesta_id',
        'texto',
        'tipo',   // escala | multiple | casillas | abierta
        'orden'
    ];

    public function opciones()
    {
        return $this->hasMany(EncuestaOpcionProlipa::class, 'pregunta_id');
    }
}

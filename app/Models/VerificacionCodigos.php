<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificacionCodigos extends Model
{
    use HasFactory;
    protected $table = "verificaciones_codigos";
    protected $primaryKey = 'id_verificacion_codigo';

    protected $fillable = [
        'contrato',
        'id_verificacion',
        'codigo',
        'codigo_combo',
        'combo',
        'plus',
        'codigo_paquete',
        'id_libro',
        'bc_periodo',
        'bc_institucion',
        'documento_venta',
        'empresa_documento',
        'estado_liquidacion',
        'codigo_union'
    ];

    // relaciones

    public function verificacion()
    {
        return $this->belongsTo(Verificacion::class, 'id_verificacion','id');
    }

    public function libro()
    {
        return $this->belongsTo(Libro::class, 'id_libro','idlibro');
    }

    public function periodoEscolar()
    {
        return $this->belongsTo(Periodo::class, 'bc_periodo','idperiodoescolar');
    }

    public function institucion()
    {
        return $this->belongsTo(Institucion::class, 'bc_institucion','idInstitucion');
    }
}

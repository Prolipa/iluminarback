<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class f_formulario_proforma extends Model
{
    use HasFactory;
    protected $table = "f_formulario_proforma";
    protected $primaryKey = 'ffp_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = [
        'idInstitucion',
        'idperiodoescolar',
        'ffp_credito',
        'ffp_cupo',
        'ffp_descuento',
        'ffp_porcentaje_facturacion',
        'ffp_saldo_prefacturas',
        'ffp_saldo_notas',
        'if_distribuidor',
        'ffp_estado',
        'user_created',
        'user_update',
        'updated_at',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ventas extends Model
{
    use HasFactory;
    protected $table = "f_venta";
     /**
     * Laravel NO maneja claves primarias compuestas.
     * Se desactiva el manejo automático de la PK.
     */
    protected $primaryKey = null;
    public $incrementing = false;

    /**
     * No timestamps automáticos
     */
    public $timestamps = false;

    /**
     * Permitir asignación masiva
     * (mejor que guarded en este caso)
     */
    protected $guarded = [];

    /**
     * Tipos de datos recomendados
     */
    protected $casts = [
        'ven_fecha' => 'datetime',
        'ven_fech_remision' => 'datetime',
        'fecha_envio_perseo' => 'datetime',
        'fecha_anulacion' => 'datetime',
        'fecha_update_sri' => 'datetime',
        'fecha_proceso_despacho' => 'datetime',
        'fecha_notaCredito' => 'date',
        'fecha_sri' => 'date',
        'ven_valor' => 'float',
        'ven_subtotal' => 'float',
        'ven_pagado' => 'float',
        'ven_iva' => 'float',
        'ven_descuento' => 'float',
    ];

    /**
     * Relación con detalle de venta
     */
    public function detalles()
    {
        return $this->hasMany(
            DetalleVenta::class,
            'ven_codigo',
            'ven_codigo'
        )->where('id_empresa', $this->id_empresa);
    }
    // public $timestamps = false;
    // protected $primaryKey = 'ven_codigo';
    // public $incrementing = false;
    // protected $fillable = [
    //     'ven_codigo',
    //     'tip_ven_codigo',
    //     'est_ven_codigo',
    //     'ven_tipo_inst',
    //     'ven_comision',
    //     'ven_valor',
    //     'ven_pagado',
    //     'ven_com_porcentaje',
    //     'ven_iva_por',
    //     'ven_iva',
    //     'ven_desc_por',
    //     'ven_descuento',
    //     'ven_fecha',
    //     'ven_idproforma',
    //     'ven_transporte',
    //     'ven_devolucion',
    //     'ven_remision',
    //     'ven_fech_remision',
    //     'institucion_id',
    //     'periodo_id',
    //     'updated_at',
    //     'user_created',
    //     'id_empresa',
    //     'id_ins_depacho',
    //     'id_sucursal',
    //     'idtipodoc',
    //     'ven_subtotal',
    //     'impresion',
    //     'ven_observacion',
    //     'ven_p_libros_obsequios',
    //     'ven_cliente',
    //     'estadoPerseo',
    //     'idPerseo',
    //     'proformas_codigo',
    //     'fecha_envio_perseo',
    //     'clientesidPerseo',
    //     'id_factura',
    //     'ruc_cliente',
    //     'anuladoEnPerseo',
    //     'doc_intercambio',
    //     'user_intercambio',
    //     'fecha_intercambio',
    //     'observacion_intercambio',
    //     'user_anulado',
    //     'fecha_anulacion',
    //     'observacionAnulacion',
    //     'id_pedido',
    //     'fecha_notaCredito',
    //     'fecha_sri',
    //     'documento_sri',
    //     'fecha_update_sri',
    //     'usuario_update_sri',
    //     'documento_ref_nci',
    //     'observacionRegresarAPendiente',
    // ];

}

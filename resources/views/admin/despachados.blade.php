<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargar Reportes del Sistema</title>
    <!-- NOTA: Esta vista está protegida con middleware AdminAccess (solo usuarios con idgroup=0) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
        }
        .container {
            max-width: 800px;
            margin-top: 40px;
            margin-bottom: 40px;
        }
        .card {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #0d6efd;
        }
        .card-header h4 {
            font-weight: normal;
            font-size: 20px;
        }
        .form-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .form-select {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 8px 12px;
        }
        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .btn-download {
            background-color: #28a745;
            border-color: #28a745;
            padding: 10px 25px;
            font-size: 14px;
        }
        .btn-download:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-download-excel {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 10px 25px;
            font-size: 14px;
            color: white;
        }
        .btn-download-excel:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .loading {
            display: none;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            border: 1px solid #dee2e6;
        }
        .region-checkboxes {
            display: flex;
            gap: 20px;
            margin-top: 8px;
        }
        .form-check-label {
            font-weight: normal;
            margin-left: 5px;
        }
        .year-range-container {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .year-range-container .form-group {
            flex: 1;
        }
        .comparativa-options {
            display: none;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            border: 1px solid #dee2e6;
        }
        .comparativa-options h6 {
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Mensajes de Error/Éxito -->
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-bottom: 0; border-radius: 0;">
            <strong>❌ Error:</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom: 0; border-radius: 0;">
            <strong>✅ Éxito:</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header text-center">
                        <h4 class="mb-0">Descargar Reportes del Sistema</h4>
                    </div>
                    <div class="card-body">
                        <form id="downloadForm">
                            <!-- Tipo de Reporte -->
                            <div class="mb-3">
                                <label for="tipo_reporte" class="form-label">
                                    <strong>Tipo de Reporte:</strong>
                                </label>
                                <select class="form-select" id="tipo_reporte" name="tipo_reporte" required onchange="actualizarOpciones()">
                                    <option value="">Seleccione un tipo de reporte...</option>
                                    <option value="comparativa_ventas">Comparativa de Ventas por Años</option>
                                    <option value="comparativa_liquidados">Comparativa de Liquidados por Años</option>
                                    <option value="pedidos_alcances">Pedidos + Alcances</option>
                                    <option value="ventas">Ventas</option>
                                    <option value="despachados">Códigos Despachados</option>
                                    <option value="devoluciones">Devoluciones</option>
                                    <option value="facturado">Facturado</option>
                                    <option value="liquidados">Liquidados</option>
                                </select>
                            </div>

                            <!-- Opciones para Comparativa de Ventas -->
                            <div id="comparativaOptions" class="comparativa-options">
                                <h6 class="mb-3">CONFIGURACIÓN DE COMPARATIVA</h6>
                                
                                <!-- Rango de Años -->
                                <div class="year-range-container mb-3">
                                    <div class="form-group">
                                        <label for="anio_inicio" class="form-label">Año Inicio</label>
                                        <select class="form-select" id="anio_inicio" name="anio_inicio">
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
                                        </select>
                                    </div>
                                    <div style="padding-top: 30px;">
                                        <strong>-</strong>
                                    </div>
                                    <div class="form-group">
                                        <label for="anio_fin" class="form-label">Año Fin</label>
                                        <select class="form-select" id="anio_fin" name="anio_fin">
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Selección de Regiones -->
                                <div class="mb-3">
                                    <label class="form-label">Regiones a Incluir</label>
                                    <div class="region-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="region_sierra" name="regiones[]" value="1" checked>
                                            <label class="form-check-label" for="region_sierra">
                                                Sierra
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="region_costa" name="regiones[]" value="2" checked>
                                            <label class="form-check-label" for="region_costa">
                                                Costa
                                            </label>
                                        </div>
                                    </div>
                                    <small class="text-muted">Por defecto se incluyen ambas regiones</small>
                                </div>
                            </div>

                            <!-- Opciones para Comparativa de Liquidados -->
                            <div id="comparativaLiquidadosOptions" class="comparativa-options">
                                <h6 class="mb-3">CONFIGURACIÓN DE COMPARATIVA LIQUIDADOS</h6>
                                
                                <!-- Rango de Años -->
                                <div class="year-range-container mb-3">
                                    <div class="form-group">
                                        <label for="anio_inicio_liq" class="form-label">Año Inicio</label>
                                        <select class="form-select" id="anio_inicio_liq" name="anio_inicio_liq">
                                            <option value="2023">2023</option>
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
                                        </select>
                                    </div>
                                    <div style="padding-top: 30px;">
                                        <strong>-</strong>
                                    </div>
                                    <div class="form-group">
                                        <label for="anio_fin_liq" class="form-label">Año Fin</label>
                                        <select class="form-select" id="anio_fin_liq" name="anio_fin_liq">
                                            <option value="2023">2023</option>
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Selección de Regiones -->
                                <div class="mb-3">
                                    <label class="form-label">Regiones a Incluir</label>
                                    <div class="region-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="region_sierra_liq" name="regiones_liq[]" value="1" checked>
                                            <label class="form-check-label" for="region_sierra_liq">
                                                Sierra
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="region_costa_liq" name="regiones_liq[]" value="2" checked>
                                            <label class="form-check-label" for="region_costa_liq">
                                                Costa
                                            </label>
                                        </div>
                                    </div>
                                    <small class="text-muted">Por defecto se incluyen ambas regiones</small>
                                </div>
                            </div>

                            <!-- Periodo Escolar (para reportes tradicionales) -->
                            <div id="periodoContainer" class="mb-3">
                                <label for="id_periodo" class="form-label">
                                    <strong>Período Escolar:</strong>
                                </label>
                                <select class="form-select" id="id_periodo" name="id_periodo" required>
                                    <option value="">Seleccione un período...</option>
                                    @foreach($periodos as $periodo)
                                        <option value="{{ $periodo->idperiodoescolar }}">
                                            {{ $periodo->descripcion }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Si experimenta problemas, use el botón "Probar Conexión" primero</small>
                            </div>

                            <!-- Indicador de Carga -->
                            <div class="loading text-center">
                                <div class="spinner-border text-success" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2 mb-0">Generando archivo CSV, por favor espere...</p>
                            </div>

                            <!-- Botones de Acción -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-success btn-download">
                                    📥 Descargar CSV (Recomendado)
                                </button>
                                <button type="button" class="btn btn-primary ms-2 btn-download-excel" id="btnExcelFacturado" style="display: none;" onclick="descargarExcel()">
                                    📊 Reporte Contabilidad
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- <div class="card-footer text-muted text-center">
                        <small>
                            ℹ️ Los archivos se descargarán automáticamente una vez procesados.
                            <br>
                            <strong>📥 CSV (Recomendado):</strong> Más rápido, soporta 500k+ registros, menor uso de memoria.
                            <br>
                            <strong>📊 Excel:</strong> Mejor formato visual, pero más lento para grandes volúmenes.
                            <br>
                            ⚡ <strong>Para grandes volúmenes (100k+ registros):</strong> CSV toma 5-15 min, Excel 15-30 min.
                            <br>
                            🔄 No cierre la ventana hasta que termine la descarga.
                            <br>
                            <strong>📋 Pedidos + Alcances:</strong> Para períodos > 27 usa sp_pedidos_alcances_new, para ≤ 26 usa sp_pedidos_alcances_old
                        </small>
                    </div> -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Progreso de Períodos -->
    <div class="modal fade" id="progressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">📊 Procesando Comparativa de Liquidados</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <p class="mb-2"><strong>Progreso general:</strong></p>
                        <div class="progress" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                 role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                <span id="progressText">0%</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2"><strong>Períodos procesados:</strong></p>
                        <div id="periodosLista" style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; border: 1px solid #dee2e6;">
                            <p class="text-muted mb-0">Iniciando...</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p id="statusText" class="text-muted mb-0">Preparando consulta...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para actualizar opciones según el tipo de reporte
        function actualizarOpciones() {
            const tipoReporte = document.getElementById('tipo_reporte').value;
            const comparativaOptions = document.getElementById('comparativaOptions');
            const comparativaLiquidadosOptions = document.getElementById('comparativaLiquidadosOptions');
            const periodoContainer = document.getElementById('periodoContainer');
            const btnExcelFacturado = document.getElementById('btnExcelFacturado');
            const periodoSelect = document.getElementById('id_periodo');

            // Limpiar mensajes de error
            limpiarMensajeError();

            if (tipoReporte === 'comparativa_ventas') {
                // Mostrar opciones de comparativa ventas
                comparativaOptions.style.display = 'block';
                comparativaLiquidadosOptions.style.display = 'none';
                periodoContainer.style.display = 'none';
                periodoSelect.required = false;
                btnExcelFacturado.style.display = 'none';
            } else if (tipoReporte === 'comparativa_liquidados') {
                // Mostrar opciones de comparativa liquidados
                comparativaOptions.style.display = 'none';
                comparativaLiquidadosOptions.style.display = 'block';
                periodoContainer.style.display = 'none';
                periodoSelect.required = false;
                btnExcelFacturado.style.display = 'none';
            } else {
                // Mostrar selector de período tradicional
                comparativaOptions.style.display = 'none';
                comparativaLiquidadosOptions.style.display = 'none';
                periodoContainer.style.display = 'block';
                periodoSelect.required = true;

                // Mostrar botón de Excel solo para Facturado y Ventas
                if (tipoReporte === 'facturado' || tipoReporte === 'ventas') {
                    btnExcelFacturado.style.display = 'block';
                } else {
                    btnExcelFacturado.style.display = 'none';
                }
            }
        }

        // Función para limpiar mensajes de error cuando el usuario cambia selecciones
        // function limpiarMensajeError() {
        //     const loadingDiv = document.querySelector('.loading');
        //     const loadingText = loadingDiv.querySelector('p');
            
        //     // Solo limpiar si hay un mensaje de error, éxito o está visible
        //     if (loadingDiv.style.display === 'block' && (
        //         loadingText.innerHTML.includes('⚠️') || 
        //         loadingText.innerHTML.includes('❌') || 
        //         loadingText.innerHTML.includes('✅')
        //     )) {
        //         loadingDiv.style.display = 'none';
        //         loadingText.innerHTML = 'Generando archivo CSV, por favor espere...';
        //     }
        // }

         // Función para limpiar mensajes de error cuando el usuario cambia selecciones
        function limpiarMensajeError() {
            const loadingDiv = document.querySelector('.loading');
            const loadingText = loadingDiv.querySelector('p');
            const spinner = loadingDiv.querySelector('.spinner-border');
            
            // Solo limpiar si hay un mensaje de error, éxito o está visible
            if (loadingDiv.style.display === 'block' && (
                loadingText.innerHTML.includes('⚠️') || 
                loadingText.innerHTML.includes('❌') || 
                loadingText.innerHTML.includes('✅')
            )) {
                loadingDiv.style.display = 'none';
                loadingText.innerHTML = 'Generando archivo CSV, por favor espere...';
                spinner.style.display = 'block'; // Restaurar spinner para próxima descarga
            }
        }

        // Agregar event listener para cuando cambie el período
        document.getElementById('id_periodo').addEventListener('change', function() {
            limpiarMensajeError();
        });

        document.getElementById('downloadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const tipoReporte = document.getElementById('tipo_reporte').value;
            
            if (tipoReporte === 'comparativa_ventas') {
                descargarComparativa();
            } else if (tipoReporte === 'comparativa_liquidados') {
                descargarComparativaLiquidados();
            } else {
                descargarArchivo('csv');
            }
        });

        // Función para descargar comparativa de ventas
        function descargarComparativa() {
            const anioInicio = document.getElementById('anio_inicio').value;
            const anioFin = document.getElementById('anio_fin').value;
            const regionSierra = document.getElementById('region_sierra').checked;
            const regionCosta = document.getElementById('region_costa').checked;

            // Validaciones
            if (!regionSierra && !regionCosta) {
                alert('Debe seleccionar al menos una región (Sierra o Costa)');
                return;
            }

            if (parseInt(anioInicio) > parseInt(anioFin)) {
                alert('El año de inicio no puede ser mayor al año final');
                return;
            }

            // Construir regiones
            let regiones = [];
            if (regionSierra) regiones.push('1');
            if (regionCosta) regiones.push('2');

            // Preparar UI
            const btnDownload = document.querySelector('.btn-download');
            const loadingDiv = document.querySelector('.loading');
            const loadingText = loadingDiv.querySelector('p');
            const spinner = loadingDiv.querySelector('.spinner-border');

            btnDownload.disabled = true;
            btnDownload.innerHTML = '⏳ Procesando...';
            loadingDiv.style.display = 'block';
            loadingText.innerHTML = `Generando comparativa de ventas ${anioInicio}-${anioFin}...<br><small>Esto puede tomar varios minutos</small>`;
            spinner.style.display = 'block';

            // Construir URL (siempre CSV para comparativa)
            const downloadUrl = `/admin/reportes/comparativa-ventas?anio_inicio=${anioInicio}&anio_fin=${anioFin}&regiones=${regiones.join(',')}&formato=csv`;

            // Descargar
            fetch(downloadUrl)
            .then(response => {
                if (!response.ok) {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json().then(errorData => {
                            throw new Error(errorData.message || `Error HTTP: ${response.status}`);
                        });
                    }
                    throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
                }

                // Extraer nombre del archivo (comparativa siempre es CSV)
                let filename = `comparativa_ventas_${anioInicio}_${anioFin}_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.csv`;
                const contentDisposition = response.headers.get('content-disposition');
                if (contentDisposition && contentDisposition.includes('filename=')) {
                    const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(contentDisposition);
                    if (matches && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }

                return response.blob().then(blob => ({ blob, filename }));
            })
            .then(({ blob, filename }) => {
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                loadingText.innerHTML = `✅ Descarga completada exitosamente!<br><small>Revise su carpeta de descargas</small>`;
                spinner.style.display = 'none';
                
                setTimeout(() => {
                    btnDownload.disabled = false;
                    btnDownload.innerHTML = '📥 DESCARGAR CSV (Recomendado)';
                    loadingDiv.style.display = 'none';
                    loadingText.innerHTML = 'Generando archivo CSV, por favor espere...';
                    spinner.style.display = 'block';
                }, 3000);
            })
            .catch(error => {
                console.error('Error técnico:', error);
                spinner.style.display = 'none';
                loadingText.innerHTML = `<span style="color: red;">❌ ${error.message}</span><br><small>Por favor intente nuevamente</small>`;
                
                btnDownload.disabled = false;
                btnDownload.innerHTML = '📥 DESCARGAR CSV (Recomendado)';
            });
        }

        // Función para descargar Excel
        function descargarExcel() {
            const tipoReporte = document.getElementById('tipo_reporte').value;
            const idPeriodo = document.getElementById('id_periodo').value;
            
            if (!tipoReporte || !idPeriodo) {
                alert('Por favor seleccione tipo de reporte y período');
                return;
            }

            descargarArchivo('excel');
        }

        // Función unificada para descargar archivos
        function descargarArchivo(formato) {
            const tipoReporte = document.getElementById('tipo_reporte').value;
            
            if (!tipoReporte) {
                alert('Por favor seleccione un tipo de reporte');
                return;
            }

            // Si es comparativa, usar función específica
            if (tipoReporte === 'comparativa_ventas') {
                descargarComparativa();
                return;
            }
            
            const idPeriodo = document.getElementById('id_periodo').value;
            
            if (!idPeriodo) {
                alert('Por favor seleccione un período escolar');
                return;
            }

            // Validar pedidos_alcances según el período
            if (tipoReporte === 'pedidos_alcances') {
                const periodo = parseInt(idPeriodo);
                if (periodo <= 0) {
                    alert('Período inválido para el reporte de Pedidos + Alcances');
                    return;
                }
            }

            // Obtener elementos
            const btnDownload = document.querySelector('.btn-download');
            const btnExcel = document.querySelector('.btn-download-excel');
            const loadingDiv = document.querySelector('.loading');
            const loadingText = loadingDiv.querySelector('p');
            const spinner = loadingDiv.querySelector('.spinner-border');
            const selectTipoReporte = document.getElementById('tipo_reporte');
            const selectPeriodo = document.getElementById('id_periodo');

            // Limpiar cualquier mensaje anterior y preparar para nueva descarga
            loadingText.innerHTML = 'Generando archivo CSV, por favor espere...';
            spinner.style.display = 'block'; // Asegurar que el spinner esté visible
            
            // Deshabilitar botones y selects
            btnDownload.disabled = true;
            if (btnExcel) btnExcel.disabled = true;
            selectTipoReporte.disabled = true;
            selectPeriodo.disabled = true;
            btnDownload.innerHTML = '⏳ Procesando...';
            if (btnExcel) btnExcel.innerHTML = '⏳ Procesando...';
            loadingDiv.style.display = 'block';
            
            const reporteNombre = {
                'pedidos_alcances': 'Pedidos + Alcances',
                'ventas': 'Ventas',
                'despachados': 'Códigos Despachados',
                'devoluciones': 'Devoluciones',
                'facturado': 'Facturado',
                'liquidados': 'Liquidados'
            };

            const formatoTexto = formato === 'excel' ? 'Excel (.xlsx)' : 'CSV';
            loadingText.innerHTML = `Iniciando descarga de ${reporteNombre[tipoReporte]} en formato ${formatoTexto}...<br><small>Para 190k+ registros puede tomar ${formato === 'excel' ? '15-30' : '5-15'} minutos</small>`;

            // MÉTODO MEJORADO CON FETCH PARA MEJOR MANEJO DE ERRORES
            const downloadUrl = `/admin/reportes/${tipoReporte}/${idPeriodo}?formato=${formato}`;
            const extension = formato === 'excel' ? 'xlsx' : 'csv';
            
            loadingText.innerHTML = `Ejecutando consulta y generando ${formatoTexto} de ${reporteNombre[tipoReporte]}...<br><small>No cierre esta ventana</small>`;

            // Usar fetch para mejor manejo de errores
            fetch(downloadUrl)
            .then(response => {
                // Si la respuesta no es exitosa, verificar si es JSON (error del servidor)
                if (!response.ok) {
                    // Verificar si es JSON de error
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json().then(errorData => {
                            throw new Error(errorData.message || `Error HTTP: ${response.status}`);
                        });
                    }
                    throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
                }
                
                // Verificar si es una respuesta JSON
                const contentType = response.headers.get('content-type');
                
                // CASO ESPECIAL: Facturado en Excel retorna JSON para procesarlo en el frontend
                if (tipoReporte === 'facturado' && formato === 'excel' && contentType && contentType.includes('application/json')) {
                    return response.json().then(data => {
                        if (data.status === 1 && data.datos) {
                            // Generar Excel con XLSX.js
                            generarExcelFacturado(data.datos, idPeriodo);
                            return { esExcelGenerado: true };
                        } else {
                            throw new Error(data.message || 'Error al obtener datos para Excel');
                        }
                    });
                }
                
                // CASO ESPECIAL: Ventas en Excel retorna JSON para procesarlo en el frontend
                if (tipoReporte === 'ventas' && formato === 'excel' && contentType && contentType.includes('application/json')) {
                    return response.json().then(data => {
                        if (data.status === 1 && data.datos) {
                            // Generar Excel con XLSX.js
                            generarExcelVentas(data.datos, idPeriodo);
                            return { esExcelGenerado: true };
                        } else {
                            throw new Error(data.message || 'Error al obtener datos para Excel');
                        }
                    });
                }
                
                // Verificar si es JSON sin datos
                if (contentType && contentType.includes('application/json')) {
                    return response.json().then(data => {
                        if (data.empty_result) {
                            throw new Error(`No hay datos disponibles para ${reporteNombre[tipoReporte]} en el período seleccionado`);
                        } else {
                            throw new Error(data.message || 'Error desconocido del servidor');
                        }
                    });
                }
                
                // Verificar que sea el formato correcto
                const esValido = formato === 'excel' 
                    ? contentType && (contentType.includes('spreadsheet') || contentType.includes('application/vnd.openxmlformats'))
                    : contentType && (contentType.includes('text/csv') || contentType.includes('application/octet-stream'));
                
                if (!esValido) {
                    throw new Error(`El servidor no retornó un archivo ${formatoTexto} válido`);
                }
                
                // Extraer nombre del archivo de las cabeceras del servidor
                let filename = `${tipoReporte}_${idPeriodo}_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.${extension}`;
                
                // Intentar obtener el nombre del archivo de las cabeceras Content-Disposition
                const contentDisposition = response.headers.get('content-disposition');
                if (contentDisposition && contentDisposition.includes('filename=')) {
                    const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(contentDisposition);
                    if (matches && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }
                
                // Crear objeto que incluye el blob y el filename
                return response.blob().then(blob => ({ blob, filename }));
            })
            .then((result) => {
                // Si es Excel generado en el frontend, ya se descargó
                if (result.esExcelGenerado) {
                    loadingText.innerHTML = `✅ Descarga completada exitosamente en formato ${formatoTexto}!<br><small>Revise su carpeta de descargas</small>`;
                    
                    // Rehabilitar controles
                    btnDownload.disabled = false;
                    if (btnExcel) btnExcel.disabled = false;
                    selectTipoReporte.disabled = false;
                    selectPeriodo.disabled = false;
                    btnDownload.innerHTML = '📥 Descargar CSV (Recomendado)';
                    if (btnExcel) btnExcel.innerHTML = '📊 Descargar Excel';
                    loadingDiv.style.display = 'none';
                    loadingText.innerHTML = 'Generando archivo CSV, por favor espere...';
                    return;
                }
                
                const { blob, filename } = result;
                
                // Crear URL del blob
                const url = window.URL.createObjectURL(blob);
                
                // Crear enlace y descargar
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Limpiar URL del blob
                window.URL.revokeObjectURL(url);
                
                loadingText.innerHTML = `✅ Descarga completada exitosamente en formato ${formatoTexto}!<br><small>Revise su carpeta de descargas</small>`;
                
                // Rehabilitar controles inmediatamente después de la descarga exitosa
                btnDownload.disabled = false;
                if (btnExcel) btnExcel.disabled = false;
                selectTipoReporte.disabled = false;
                selectPeriodo.disabled = false;
                btnDownload.innerHTML = '📥 Descargar CSV (Recomendado)';
                if (btnExcel) btnExcel.innerHTML = '📊 Descargar Excel';
                loadingDiv.style.display = 'none';
                loadingText.innerHTML = 'Generando archivo CSV, por favor espere...';
            })
            .catch(error => {
                // Registrar error en consola para debugging (solo para desarrolladores)
                console.error('Error técnico para debugging:', error);
                
                // Ocultar el spinner pero mantener el área de mensaje visible
                const spinner = loadingDiv.querySelector('.spinner-border');
                spinner.style.display = 'none';
                
                // Mensajes amigables para el usuario final
                if (error.message.includes('No hay datos disponibles')) {
                    loadingText.innerHTML = `<span style="color: orange;">⚠️ ${error.message}</span><br><small>Intente con otro período o tipo de reporte</small>`;
                } else if (error.message.includes('Error HTTP: 500')) {
                    loadingText.innerHTML = `<span style="color: red;">❌ No se pudo procesar su solicitud</span><br><small>Por favor intente nuevamente en unos minutos</small>`;
                } else if (error.message.includes('Error HTTP: 404')) {
                    loadingText.innerHTML = `<span style="color: orange;">⚠️ No hay información disponible para este reporte</span><br><small>Verifique que haya seleccionado el período correcto</small>`;
                } else if (error.message.includes('Error HTTP:')) {
                    loadingText.innerHTML = `<span style="color: red;">❌ Problema de conexión</span><br><small>Verifique su conexión a internet e intente nuevamente</small>`;
                } else {
                    loadingText.innerHTML = `<span style="color: red;">❌ No se pudo descargar el reporte</span><br><small>Por favor intente nuevamente o contacte al administrador</small>`;
                }
                
                // Rehabilitar controles inmediatamente después del error
                btnDownload.disabled = false;
                if (btnExcel) btnExcel.disabled = false;
                selectTipoReporte.disabled = false;
                selectPeriodo.disabled = false;
                btnDownload.innerHTML = '📥 Descargar CSV (Recomendado)';
                if (btnExcel) btnExcel.innerHTML = '📊 Descargar Excel';
                
                // El mensaje de error se queda visible hasta que el usuario cambie alguna selección
            });
        }

        // Función para probar el procedimiento almacenado
        function probarProcedimiento() {
            const tipoReporte = document.getElementById('tipo_reporte').value;
            const idPeriodo = document.getElementById('id_periodo').value;
            
            if (!tipoReporte) {
                alert('Por favor seleccione un tipo de reporte');
                return;
            }
            
            if (!idPeriodo) {
                alert('Por favor seleccione un período escolar');
                return;
            }

            const testUrl = `/admin/reportes/test/${tipoReporte}/${idPeriodo}`;

            fetch(testUrl)
            .then(response => response.json())
            .then(data => {
                if (data.status === 1) {
                    let mensaje = `✅ PRUEBA EXITOSA!\n\n`;
                    mensaje += `📊 Reporte: ${data.tipo_reporte}\n`;
                    mensaje += `📅 Período: ${data.periodo}\n`;
                    mensaje += `📈 Total registros: ${data.total_registros_aproximado}\n`;
                    mensaje += `🏗️ Columnas disponibles: ${data.estructura_columnas.length}\n\n`;
                    mensaje += `Primeras columnas:\n${data.estructura_columnas.slice(0, 5).join(', ')}\n\n`;
                    
                    if (data.procedimiento_usado) {
                        mensaje += `🔧 Procedimiento usado: ${data.procedimiento_usado}\n\n`;
                    }
                    
                    mensaje += `El procedimiento almacenado está funcionando correctamente.\n`;
                    mensaje += `Ahora puede proceder con la descarga completa.`;

                    alert(mensaje);
                } else {
                    alert(`❌ ERROR EN LA PRUEBA:\n\n${data.message}\n\nRevise los logs del servidor para más información.`);
                }
            })
            .catch(error => {
                console.error('Error en prueba:', error);
                alert(`❌ ERROR DE CONEXIÓN:\n\n${error.message}\n\nProblemas posibles:\n1. Servidor no responde\n2. Procedimiento almacenado no existe\n3. Error de base de datos`);
            });
        }

        // Función de respaldo usando el método original
        function descargarLegacy() {
            const tipoReporte = document.getElementById('tipo_reporte').value;
            const idPeriodo = document.getElementById('id_periodo').value;
            
            if (!tipoReporte) {
                alert('Por favor seleccione un tipo de reporte');
                return;
            }
            
            if (!idPeriodo) {
                alert('Por favor seleccione un período escolar');
                return;
            }

            // Solo funciona para despachados por ahora
            if (tipoReporte !== 'despachados') {
                alert('El método de respaldo solo está disponible para "Códigos Despachados".\nPara otros reportes, configure primero las rutas del backend.');
                return;
            }

            // Usar la ruta original
            const legacyUrl = `/admin/despachados/simple/${idPeriodo}`;
            
            // Método de descarga directa
            const link = document.createElement('a');
            link.href = legacyUrl;
            link.download = `codigos_despachados_legacy_${idPeriodo}_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.csv`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            alert('🔄 Descarga iniciada usando método de respaldo.\nSi no funciona, revise la configuración del backend.');
        }

        // Función para generar Excel de Facturado con XLSX.js (con rowspan)
        function generarExcelFacturado(datos, idPeriodo) {
            const wb = XLSX.utils.book_new();
            const ws = {};

            // ENCABEZADOS - Todas las columnas del stored procedure
            XLSX.utils.sheet_add_aoa(ws, [
                ['Contrato', 'Periodo', 'Ciudad', 'ID Inst.', 'Institución', 'Asesor', 'Documento', 
                 '% Venta', 'Código', 'Nombre Libro', 'Precio', 'Cantidad', 'Tipo', 'Distribuidor', 
                 'Tipo Inst.', 'Tipo Venta', 'Tipo Producto', 'Fecha Doc.', 'Fecha Envío', 
                 'Envío Perseo', 'Cliente Perseo', 'RUC Cliente', 'Empresa', 'Facturación Cruzada', 'Desglose Combo']
            ], { origin: "A1" });

            // DATOS CON ROWSPAN
            let filas = [];
            let merges = [];
            let filaActual = 2; // Fila 2 en Excel (1 es encabezado)

            datos.forEach(row => {
                // Separar desgloses del combo
                const codigos = row.Desglose_combo && row.Desglose_combo.trim() 
                    ? row.Desglose_combo.split(',').map(c => c.trim()).filter(c => c)
                    : [''];

                const numCodigos = codigos.length;
                const inicio = filaActual;
                const fin = inicio + numCodigos - 1;

                // Añadir filas
                codigos.forEach((codigo, i) => {
                    filas.push([
                        i === 0 ? (row.contrato_generado || '') : '',
                        i === 0 ? (row.periodo || '') : '',
                        i === 0 ? (row.ciudad_nombre || '') : '',
                        i === 0 ? (row.id_institucion || '') : '',
                        i === 0 ? (row.nombreInstitucion || '') : '',
                        i === 0 ? (row.asesor || '') : '',
                        i === 0 ? (row.documentoVenta || '') : '',
                        i === 0 ? (row.porcentaje_venta || 0) : '',
                        i === 0 ? (row.pro_codigo || '') : '',
                        i === 0 ? (row.nombre_libro || '') : '',
                        i === 0 ? (row.precio || 0) : '',
                        i === 0 ? (row.cantidad || 0) : '',
                        i === 0 ? (row.tipo || '') : '',
                        i === 0 ? (row.distribuidor || '') : '',
                        i === 0 ? (row.tipo_institucion || '') : '',
                        i === 0 ? (row.tipo_venta || '') : '',
                        i === 0 ? (row.tipo_producto || '') : '',
                        i === 0 ? (row.fecha_documento || '') : '',
                        i === 0 ? (row.fecha_envio || '') : '',
                        i === 0 ? (row.EnvioPerseo || '') : '',
                        i === 0 ? (row.ClientePerseo || '') : '',
                        i === 0 ? (row.RucClientePerseo || '') : '',
                        i === 0 ? (row.Empresa || '') : '',
                        i === 0 ? (row.Facturacion_Cruzada || '') : '',
                        codigo  // SIEMPRE: código del desglose
                    ]);
                });

                // MERGES CORRECTOS (Excel usa filas 0-based internamente)
                if (numCodigos > 1) {
                    // Fusionar todas las columnas EXCEPTO Desglose_combo (columna 24)
                    for (let col = 0; col < 24; col++) {
                        merges.push({ s: { r: inicio - 1, c: col }, e: { r: fin - 1, c: col } });
                    }
                }

                filaActual += numCodigos;
            });

            // AGREGAR FILAS DE DATOS EN A2
            XLSX.utils.sheet_add_aoa(ws, filas, { origin: "A2" });

            // APLICAR MERGES
            ws["!merges"] = merges;

            // ANCHOS DE COLUMNA
            ws["!cols"] = [
                {wch:12},  // Contrato
                {wch:12},  // Periodo
                {wch:15},  // Ciudad
                {wch:10},  // ID Inst.
                {wch:35},  // Institución
                {wch:25},  // Asesor
                {wch:20},  // Documento
                {wch:10},  // % Venta
                {wch:12},  // Código
                {wch:30},  // Nombre Libro
                {wch:10},  // Precio
                {wch:10},  // Cantidad
                {wch:12},  // Tipo
                {wch:12},  // Distribuidor
                {wch:12},  // Tipo Inst.
                {wch:12},  // Tipo Venta
                {wch:15},  // Tipo Producto
                {wch:12},  // Fecha Doc.
                {wch:12},  // Fecha Envío
                {wch:15},  // Envío Perseo
                {wch:30},  // Cliente Perseo
                {wch:15},  // RUC Cliente
                {wch:12},  // Empresa
                {wch:18},  // Facturación Cruzada
                {wch:15}   // Desglose Combo
            ];

            XLSX.utils.book_append_sheet(wb, ws, "Facturado");

            // DESCARGAR
            const fecha = new Date().toISOString().slice(0,19).replace(/:/g, '-');
            const filename = `facturado_${idPeriodo}_${fecha}.xlsx`;
            
            const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
            const blob = new Blob([wbout], { type: 'application/octet-stream' });
            const url = URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            URL.revokeObjectURL(url);
        }

        // Función para descargar comparativa de liquidados con modal de progreso
        async function descargarComparativaLiquidados() {
            const anioInicio = document.getElementById('anio_inicio_liq').value;
            const anioFin = document.getElementById('anio_fin_liq').value;
            const regionSierra = document.getElementById('region_sierra_liq').checked;
            const regionCosta = document.getElementById('region_costa_liq').checked;

            // Validaciones
            if (!regionSierra && !regionCosta) {
                alert('Debe seleccionar al menos una región (Sierra o Costa)');
                return;
            }

            if (parseInt(anioInicio) > parseInt(anioFin)) {
                alert('El año de inicio no puede ser mayor al año final');
                return;
            }

            // Construir regiones
            let regiones = [];
            if (regionSierra) regiones.push('1');
            if (regionCosta) regiones.push('2');

            // Preparar UI
            const btnDownload = document.querySelector('.btn-download');
            btnDownload.disabled = true;
            btnDownload.innerHTML = '⏳ Procesando...';

            // Mostrar modal de progreso
            const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
            progressModal.show();

            // Elementos del modal
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const periodosLista = document.getElementById('periodosLista');
            const statusText = document.getElementById('statusText');

            periodosLista.innerHTML = '';
            statusText.innerHTML = 'Obteniendo períodos disponibles...';

            try {
                // Construir URL para la comparativa
                const downloadUrl = `/admin/reportes/comparativa-liquidados?anio_inicio=${anioInicio}&anio_fin=${anioFin}&regiones=${regiones.join(',')}&formato=csv`;

                // Realizar la petición con fetch para recibir el stream
                const response = await fetch(downloadUrl);

                if (!response.ok) {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || `Error HTTP: ${response.status}`);
                    }
                    throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
                }

                // Leer el stream para actualizar el progreso
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let fullBuffer = ''; 
                const csvChunks = [];

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    // Decodificar el nuevo chunk y añadirlo al buffer acumulado
                    fullBuffer += decoder.decode(value, { stream: true });

                    // Procesar todos los mensajes de progreso completos que haya en el buffer
                    let progressStartIndex;
                    while ((progressStartIndex = fullBuffer.indexOf("<!-- PROGRESS: ")) !== -1) {
                        const progressEndIndex = fullBuffer.indexOf(" -->", progressStartIndex);
                        
                        // Si no encontramos el final del mensaje, rompemos el ciclo para esperar más datos
                        if (progressEndIndex === -1) break;

                        // Todo lo que está ANTES del mensaje de progreso es datos del CSV
                        if (progressStartIndex > 0) {
                            const csvPart = fullBuffer.substring(0, progressStartIndex);
                            if (csvPart.length > 0) {
                                csvChunks.push(new TextEncoder().encode(csvPart));
                            }
                        }

                        // Extraer y procesar el JSON del progreso
                        const progressJSON = fullBuffer.substring(progressStartIndex + 15, progressEndIndex);
                        try {
                            const progress = JSON.parse(progressJSON);
                            
                            // Actualizar barra de progreso
                            const percent = Math.round((progress.current / progress.total) * 100);
                            progressBar.style.width = percent + '%';
                            progressBar.setAttribute('aria-valuenow', percent);
                            progressText.textContent = percent + '%';

                            // Agregar período procesado a la lista
                            const periodoItem = document.createElement('div');
                            periodoItem.className = 'mb-1';
                            periodoItem.innerHTML = `<small>✅ <strong>${progress.periodo}</strong> - ${progress.registros} registros</small>`;
                            periodosLista.appendChild(periodoItem);
                            periodosLista.scrollTop = periodosLista.scrollHeight;

                            // Actualizar status
                            statusText.innerHTML = `Procesando período ${progress.current} de ${progress.total}...`;
                            if (progress.error) {
                                console.error(`Error en periodo ${progress.periodo}:`, progress.error);
                            }
                        } catch (e) {
                            console.warn('Error parseando JSON de progreso:', e, progressJSON);
                        }

                        // Cortar el buffer: descartar lo que ya procesamos (el mensaje de progreso)
                        fullBuffer = fullBuffer.substring(progressEndIndex + 4);
                    }

                    // IMPORTANTE: No podemos limpiar el buffer completamente aquí porque 
                    // podría contener el inicio de un mensaje de progreso.
                    // Solo podemos limpiar lo que estamos SEGUROS que es CSV y no tiene inicios de tags.
                    const lastTagStart = fullBuffer.lastIndexOf("<!--");
                    if (lastTagStart > 0) {
                        // Guardamos como CSV todo lo que está antes del último tag potencial
                        const csvPart = fullBuffer.substring(0, lastTagStart);
                        csvChunks.push(new TextEncoder().encode(csvPart));
                        fullBuffer = fullBuffer.substring(lastTagStart);
                    } else if (lastTagStart === -1 && fullBuffer.length > 500) {
                        // Si no hay tags en un buffer grande, podemos enviarlo casi todo
                        // dejamos un poco por si el tag se partió justo al final
                        const safeLength = fullBuffer.length - 20; 
                        const csvPart = fullBuffer.substring(0, safeLength);
                        csvChunks.push(new TextEncoder().encode(csvPart));
                        fullBuffer = fullBuffer.substring(safeLength);
                    }
                }

                // Al terminar todo el stream, lo que quede en el buffer es el resto del CSV
                if (fullBuffer.length > 0) {
                    csvChunks.push(new TextEncoder().encode(fullBuffer));
                }

                // Crear blob solo con chunks limpios del CSV
                const blob = new Blob(csvChunks, { type: 'text/csv' });
                
                // Extraer nombre del archivo
                let filename = `comparativa_liquidados_${anioInicio}_${anioFin}_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.csv`;
                const contentDisposition = response.headers.get('content-disposition');
                if (contentDisposition && contentDisposition.includes('filename=')) {
                    const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(contentDisposition);
                    if (matches && matches[1]) {
                        filename = matches[1].replace(/["']/g, '');
                    }
                }

                // Descargar el archivo
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                // Actualizar UI de éxito
                progressBar.style.width = '100%';
                progressBar.setAttribute('aria-valuenow', 100);
                progressText.textContent = '100%';
                statusText.innerHTML = '✅ <strong>Descarga completada exitosamente!</strong>';

                // Cerrar modal después de 3 segundos
                setTimeout(() => {
                    progressModal.hide();
                    btnDownload.disabled = false;
                    btnDownload.innerHTML = '📥 Descargar CSV (Recomendado)';
                }, 3000);

            } catch (error) {
                console.error('Error técnico:', error);
                statusText.innerHTML = `<span style="color: red;">❌ ${error.message}</span>`;
                
                setTimeout(() => {
                    progressModal.hide();
                    btnDownload.disabled = false;
                    btnDownload.innerHTML = '📥 Descargar CSV (Recomendado)';
                }, 5000);
            }
        }

        // Función para generar Excel de Ventas con XLSX.js (con rowspan)
        function generarExcelVentas(datos, idPeriodo) {
            const wb = XLSX.utils.book_new();
            const ws = {};

            // ENCABEZADOS - Todas las columnas del stored procedure sp_ventas (20 columnas)
            XLSX.utils.sheet_add_aoa(ws, [
                ['Contrato', 'Periodo', 'Ciudad', 'ID Inst.', 'Institución', 'Asesor', 'Documento', 
                 '% Venta', 'Código', 'Nombre Libro', 'Precio', 'Cantidad', 'Tipo Venta', 'Distribuidor', 
                 'Tipo Inst.', 'Tipo Venta Modalidad', 'Tipo Producto', 'Fecha Doc.', 'Empresa', 'Desglose Combo']
            ], { origin: "A1" });

            // DATOS CON ROWSPAN
            let filas = [];
            let merges = [];
            let filaActual = 2; // Fila 2 en Excel (1 es encabezado)

            datos.forEach(row => {
                // Separar desgloses del combo
                const codigos = row.Desglose_combo && row.Desglose_combo.trim() 
                    ? row.Desglose_combo.split(',').map(c => c.trim()).filter(c => c)
                    : [''];

                const numCodigos = codigos.length;
                const inicio = filaActual;
                const fin = inicio + numCodigos - 1;

                // Añadir filas
                codigos.forEach((codigo, i) => {
                    filas.push([
                        i === 0 ? (row.contrato_generado || '') : '',
                        i === 0 ? (row.periodo || '') : '',
                        i === 0 ? (row.ciudad_nombre || '') : '',
                        i === 0 ? (row.id_institucion || '') : '',
                        i === 0 ? (row.nombreInstitucion || '') : '',
                        i === 0 ? (row.asesor || '') : '',
                        i === 0 ? (row.documentoVenta || '') : '',
                        i === 0 ? (row.porcentaje_venta || 0) : '',
                        i === 0 ? (row.pro_codigo || '') : '',
                        i === 0 ? (row.nombre_libro || '') : '',
                        i === 0 ? (row.precio || 0) : '',
                        i === 0 ? (row.cantidad || 0) : '',
                        i === 0 ? (row.tipo_venta || '') : '',
                        i === 0 ? (row.distribuidor || '') : '',
                        i === 0 ? (row.tipo_institucion || '') : '',
                        i === 0 ? (row.tipo_venta_modalidad || '') : '',
                        i === 0 ? (row.tipo_producto || '') : '',
                        i === 0 ? (row.fecha_documento || '') : '',
                        i === 0 ? (row.Empresa || '') : '',
                        codigo  // SIEMPRE: código del desglose
                    ]);
                });

                // MERGES CORRECTOS (Excel usa filas 0-based internamente)
                if (numCodigos > 1) {
                    // Fusionar todas las columnas EXCEPTO Desglose_combo (columna 19)
                    for (let col = 0; col < 19; col++) {
                        merges.push({ s: { r: inicio - 1, c: col }, e: { r: fin - 1, c: col } });
                    }
                }

                filaActual += numCodigos;
            });

            // AGREGAR FILAS DE DATOS EN A2
            XLSX.utils.sheet_add_aoa(ws, filas, { origin: "A2" });

            // APLICAR MERGES
            ws["!merges"] = merges;

            // ANCHOS DE COLUMNA
            ws["!cols"] = [
                {wch:12},  // Contrato
                {wch:12},  // Periodo
                {wch:15},  // Ciudad
                {wch:10},  // ID Inst.
                {wch:35},  // Institución
                {wch:25},  // Asesor
                {wch:20},  // Documento
                {wch:10},  // % Venta
                {wch:12},  // Código
                {wch:30},  // Nombre Libro
                {wch:10},  // Precio
                {wch:10},  // Cantidad
                {wch:12},  // Tipo Venta
                {wch:12},  // Distribuidor
                {wch:12},  // Tipo Inst.
                {wch:18},  // Tipo Venta Modalidad
                {wch:15},  // Tipo Producto
                {wch:12},  // Fecha Doc.
                {wch:12},  // Empresa
                {wch:15}   // Desglose Combo
            ];

            XLSX.utils.book_append_sheet(wb, ws, "Ventas");

            // DESCARGAR
            const fecha = new Date().toISOString().slice(0,19).replace(/:/g, '-');
            const filename = `ventas_${idPeriodo}_${fecha}.xlsx`;
            
            const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
            const blob = new Blob([wbout], { type: 'application/octet-stream' });
            const url = URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            URL.revokeObjectURL(url);
        }
    </script>
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$download = $_GET['download'] ?? null;

// Export CSV / TXT files for Declaraguate SAT
if ($download === 'reten_isr') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=RetenISR_SAT_' . $anio . '_' . $mes . '.csv');
    
    $output = fopen('php://output', 'w');
    // SAT Header
    fputcsv($output, ['NIT_AGENTE', 'NIT_PROVEEDOR', 'NOMBRE_PROVEEDOR', 'SERIE_FACTURA', 'NUMERO_FACTURA', 'FECHA_FACTURA', 'MONTO_NETO', 'ISR_RETENIDO', 'CONSTANCIA_NUM']);
    
    $stmt = $pdo->prepare("SELECT * FROM contabilidad_libro_compras WHERE id_empresa = ? AND estado_retencion_isr = 'Constancia Emitida' AND MONTH(fecha_factura) = ? AND YEAR(fecha_factura) = ?");
    $stmt->execute([$empresa_id, $mes, $anio]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $ret = calcular_retencion_isr_proveedor($r['monto_neto']);
        fputcsv($output, [
            $empresa_actual['nit'],
            $r['nit_proveedor'],
            $r['nombre_proveedor'],
            $r['serie'],
            $r['numero'],
            $r['fecha_factura'],
            number_format($r['monto_neto'], 2, '.', ''),
            number_format($ret, 2, '.', ''),
            $r['constancia_retencion_num']
        ]);
    }
    fclose($output);
    exit;
}

if ($download === 'libro_compras') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Libro_Compras_SAT_' . $anio . '_' . $mes . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['TIPO_DOC', 'SERIE', 'NUMERO', 'FECHA', 'NIT_PROVEEDOR', 'PROVEEDOR', 'EXENTO', 'NETO', 'IVA_CREDITO', 'TOTAL']);
    
    $stmt = $pdo->prepare("SELECT * FROM contabilidad_libro_compras WHERE id_empresa = ? AND MONTH(fecha_factura) = ? AND YEAR(fecha_factura) = ?");
    $stmt->execute([$empresa_id, $mes, $anio]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['tipo_documento'],
            $r['serie'],
            $r['numero'],
            $r['fecha_factura'],
            $r['nit_proveedor'],
            $r['nombre_proveedor'],
            number_format($r['monto_exento'], 2, '.', ''),
            number_format($r['monto_neto'], 2, '.', ''),
            number_format($r['monto_iva'], 2, '.', ''),
            number_format($r['total'], 2, '.', '')
        ]);
    }
    fclose($output);
    exit;
}

if ($download === 'libro_ventas') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Libro_Ventas_SAT_' . $anio . '_' . $mes . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['TIPO_DOC', 'SERIE', 'NUMERO', 'FECHA', 'NIT_CLIENTE', 'CLIENTE', 'EXENTO', 'NETO', 'IVA_DEBITO', 'TOTAL', 'UUID_FEL']);
    
    $stmt = $pdo->prepare("SELECT * FROM contabilidad_libro_ventas WHERE id_empresa = ? AND MONTH(fecha_factura) = ? AND YEAR(fecha_factura) = ?");
    $stmt->execute([$empresa_id, $mes, $anio]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['tipo_documento'],
            $r['serie'],
            $r['numero'],
            $r['fecha_factura'],
            $r['nit_cliente'],
            $r['nombre_cliente'],
            number_format($r['monto_exento'], 2, '.', ''),
            number_format($r['monto_neto'], 2, '.', ''),
            number_format($r['monto_iva'], 2, '.', ''),
            number_format($r['total'], 2, '.', ''),
            $r['uuid_fel']
        ]);
    }
    fclose($output);
    exit;
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Generación de Archivos para Declaraguate (SAT)</h1>
        <p class="page-subtitle">Exportación masiva de datos en formatos .CSV y .TXT para carga oficial en SAT-Declaraguate</p>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <form method="GET" action="" style="display:flex; align-items:center; gap:15px;">
        <label style="font-size:13px; font-weight:700;">Seleccionar Mes y Año:</label>
        <select name="mes" class="form-control" style="width:120px;">
            <?php for ($m=1; $m<=12; $m++): ?>
                <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= ($m == $mes) ? 'selected' : '' ?>>Mes <?= $m ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" name="anio" class="form-control" value="<?= $anio ?>" style="width:100px;">
        <button type="submit" class="btn btn-primary btn-sm">Actualizar Período</button>
    </form>
</div>

<div class="grid grid-cols-3">
    <!-- Export 1: Retenciones ISR -->
    <div class="card" style="border-top:4px solid var(--primary);">
        <h3 style="font-size:16px; font-weight:700; color:var(--secondary); margin-bottom:10px;">
            1. Carga Masiva Retenciones ISR
        </h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">
            Genera el archivo para la plataforma SAT-RetenISR con todas las constancias emitidas a proveedores durante el mes.
        </p>
        <a href="?download=reten_isr&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-primary" style="width:100%; justify-content:center;">
            ⬇️ Descargar Archivo RetenISR (.CSV)
        </a>
    </div>

    <!-- Export 2: Libro Compras -->
    <div class="card" style="border-top:4px solid var(--warning);">
        <h3 style="font-size:16px; font-weight:700; color:var(--secondary); margin-bottom:10px;">
            2. Libro de Compras SAT
        </h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">
            Archivo delimitado con detalle de facturas de compras, proveedores, montos exentos, netos y 12% IVA crédito.
        </p>
        <a href="?download=libro_compras&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-secondary" style="width:100%; justify-content:center;">
            ⬇️ Descargar Libro Compras (.CSV)
        </a>
    </div>

    <!-- Export 3: Libro Ventas -->
    <div class="card" style="border-top:4px solid var(--success);">
        <h3 style="font-size:16px; font-weight:700; color:var(--secondary); margin-bottom:10px;">
            3. Libro de Ventas SAT (FEL)
        </h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">
            Archivo con registro de facturas emitidas, serie, número, NIT cliente, 12% IVA débito fiscal y UUID FEL de certificación.
        </p>
        <a href="?download=libro_ventas&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-success" style="width:100%; justify-content:center;">
            ⬇️ Descargar Libro Ventas (.CSV)
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

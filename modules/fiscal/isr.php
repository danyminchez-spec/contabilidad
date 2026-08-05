<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

// 1. Calculate Monthly Income (Régimen Opcional Simplificado)
$stmtVentasNeto = $pdo->prepare("SELECT COALESCE(SUM(monto_neto), 0) FROM contabilidad_libro_ventas WHERE id_empresa = ? AND MONTH(fecha_factura) = ? AND YEAR(fecha_factura) = ?");
$stmtVentasNeto->execute([$empresa_id, $mes, $anio]);
$ingresoRentaBruta = (float)$stmtVentasNeto->fetchColumn();

$isrOpcionalCalculado = calcular_isr_opcional_mensual($ingresoRentaBruta);

// Fetch Constancias de Retención Issued to us or by us
$stmtConstancias = $pdo->prepare("SELECT * FROM contabilidad_libro_compras WHERE id_empresa = ? AND estado_retencion_isr = 'Constancia Emitida' ORDER BY fecha_factura DESC");
$stmtConstancias->execute([$empresa_id]);
$constanciasEmitidas = $stmtConstancias->fetchAll();

// 2. Calculate Régimen Sobre Utilidades (25%)
$stmtIngTot = $pdo->prepare("SELECT COALESCE(SUM(d.haber - d.debe), 0) FROM contabilidad_partida_detalles d JOIN contabilidad_partidas p ON d.id_partida = p.id JOIN contabilidad_cuentas c ON d.id_cuenta = c.id WHERE p.id_empresa = ? AND c.tipo_cuenta = 'Ingresos' AND YEAR(p.fecha) = ?");
$stmtIngTot->execute([$empresa_id, $anio]);
$ingresosAnuales = (float)$stmtIngTot->fetchColumn();

$stmtGasTot = $pdo->prepare("SELECT COALESCE(SUM(d.debe - d.haber), 0) FROM contabilidad_partida_detalles d JOIN contabilidad_partidas p ON d.id_partida = p.id JOIN contabilidad_cuentas c ON d.id_cuenta = c.id WHERE p.id_empresa = ? AND c.tipo_cuenta = 'Gastos' AND YEAR(p.fecha) = ?");
$stmtGasTot->execute([$empresa_id, $anio]);
$gastosAnuales = (float)$stmtGasTot->fetchColumn();

// Non-deductible expenses (Cuenta 6.1.02.03)
$stmtNoDed = $pdo->prepare("SELECT COALESCE(SUM(d.debe - d.haber), 0) FROM contabilidad_partida_detalles d JOIN contabilidad_partidas p ON d.id_partida = p.id JOIN contabilidad_cuentas c ON d.id_cuenta = c.id WHERE p.id_empresa = ? AND c.codigo_cuenta LIKE '6.1.02.03%' AND YEAR(p.fecha) = ?");
$stmtNoDed->execute([$empresa_id, $anio]);
$gastosNoDeducibles = (float)$stmtNoDed->fetchColumn();

$gastosDeducibles = $gastosAnuales - $gastosNoDeducibles;
$rentaComputableUtilidades = max(0, $ingresosAnuales - $gastosDeducibles);
$isrUtilidadesCalculado = round($rentaComputableUtilidades * 0.25, 2);
$pagoTrimestralIsr = round($isrUtilidadesCalculado / 4, 2);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Regímenes de ISR (Ley de Actualización Tributaria)</h1>
        <p class="page-subtitle">Liquidación de Impuesto Sobre la Renta y Control de Retenciones SAT</p>
    </div>
</div>

<div class="card" style="margin-bottom:20px; background:linear-gradient(135deg, #0f172a, #1e293b); color:#fff;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Régimen Fiscal Asignado en SAT</span>
            <h2 style="font-size:1.5rem; font-weight:800; color:#60a5fa; margin-top:2px;">
                RÉGIMEN <?= strtoupper($empresa_actual['regimen_isr']) ?>
            </h2>
            <p style="font-size:13px; color:#cbd5e1; margin-top:4px;">
                NIT: <?= htmlspecialchars($empresa_actual['nit']) ?> | Agente Retención: <?= $empresa_actual['es_agente_retencion'] ? 'SÍ' : 'NO' ?>
            </p>
        </div>
        <div>
            <span class="badge badge-success" style="font-size:14px; padding:8px 16px;">Vigente Ejercicio <?= $anio ?></span>
        </div>
    </div>
</div>

<div class="grid grid-cols-2" style="margin-bottom:24px;">
    <!-- REGIMEN OPCIONAL SIMPLIFICADO -->
    <div class="card" style="border-top: 4px solid var(--primary);">
        <h3 style="font-size:16px; font-weight:700; color:var(--secondary); margin-bottom:15px;">
            1. Régimen Opcional Simplificado sobre Ingresos
        </h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">
            Cálculo mensual acumulado: <strong>5%</strong> sobre los primeros Q30,000.00 y <strong>7%</strong> sobre el excedente (Q1,500.00 fijos).
        </p>

        <div style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid var(--border); margin-bottom:15px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:13px;">Ingresos Brutos del Mes (Mes <?= $mes ?>/<?= $anio ?>):</span>
                <strong><?= format_gtq($ingresoRentaBruta) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:13px;">Tramo 5% (hasta Q30,000.00):</span>
                <span><?= format_gtq(min(30000.00, $ingresoRentaBruta) * 0.05) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:13px;">Tramo 7% (Excedente de Q30,000.00):</span>
                <span><?= format_gtq(max(0, $ingresoRentaBruta - 30000.00) * 0.07) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:800; border-top:1px solid var(--border); padding-top:10px; color:var(--primary);">
                <span>ISR MENSUAL DETERMINADO:</span>
                <span><?= format_gtq($isrOpcionalCalculado) ?></span>
            </div>
        </div>
    </div>

    <!-- REGIMEN SOBRE UTILIDADES -->
    <div class="card" style="border-top: 4px solid var(--warning);">
        <h3 style="font-size:16px; font-weight:700; color:var(--secondary); margin-bottom:15px;">
            2. Régimen Sobre Utilidades de Actividades Lucrativas
        </h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">
            Cálculo del <strong>25%</strong> sobre la Renta Computable (Utilidad Fiscal) con control de gastos deducibles vs. no deducibles.
        </p>

        <div style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:12px;">
                <span>Total Ingresos Anuales:</span>
                <strong><?= format_gtq($ingresosAnuales) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:12px;">
                <span>(-) Gastos Deducibles de Operación:</span>
                <span><?= format_gtq($gastosDeducibles) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:12px; color:var(--danger);">
                <span>(+) Gastos NO Deducibles (SAT):</span>
                <span><?= format_gtq($gastosNoDeducibles) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; border-top:1px solid var(--border); padding-top:8px; margin-top:4px;">
                <span>Renta Computable (Utilidad Fiscal):</span>
                <span><?= format_gtq($rentaComputableUtilidades) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:800; color:var(--warning); margin-top:6px;">
                <span>ISR ANUAL ESTIMADO (25%):</span>
                <span><?= format_gtq($isrUtilidadesCalculado) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text-muted); border-top:1px dashed var(--border); padding-top:6px; margin-top:6px;">
                <span>Pago Parcial Cierre Trimestral:</span>
                <strong><?= format_gtq($pagoTrimestralIsr) ?> / Trimestre</strong>
            </div>
        </div>
    </div>
</div>

<!-- Constancias de Retencion emitidas -->
<div class="card">
    <h3 style="font-size:15px; font-weight:700; color:var(--secondary); margin-bottom:12px;">
        Control de Constancias de Retención de ISR Emitidas a Proveedores
    </h3>
    <div class="table-container" style="margin-top:0;">
        <table class="table">
            <thead>
                <tr>
                    <th># Constancia Retención</th>
                    <th>Fecha Factura</th>
                    <th>NIT Proveedor</th>
                    <th>Nombre Proveedor</th>
                    <th class="text-right">Monto Neto Factura</th>
                    <th class="text-right">Monto Retención ISR (5%/7%)</th>
                    <th class="text-center">Estado SAT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($constanciasEmitidas)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:20px; color:var(--text-muted);">No hay constancias de retención registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($constanciasEmitidas as $c): ?>
                        <?php $ret = calcular_retencion_isr_proveedor($c['monto_neto']); ?>
                        <tr>
                            <td><strong style="font-family:monospace; color:var(--primary);"><?= htmlspecialchars((string)($c['constancia_retencion_num'] ?? 'RET-ISR-PEND')) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($c['fecha_factura'])) ?></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars((string)($c['nit_proveedor'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($c['nombre_proveedor'] ?? '')) ?></td>
                            <td class="text-right"><?= format_gtq($c['monto_neto']) ?></td>
                            <td class="text-right" style="color:var(--warning); font-weight:700;"><?= format_gtq($ret) ?></td>
                            <td class="text-center"><span class="badge badge-success">Constancia Válida SAT</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

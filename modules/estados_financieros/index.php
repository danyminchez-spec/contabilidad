<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();
$reporte = $_GET['reporte'] ?? 'resultados';
$anio = $_GET['anio'] ?? date('Y');

// Fetch sums for Accounts
$getSumByTipo = function($tipo) use ($pdo, $empresa_id) {
    $stmt = $pdo->prepare("SELECT c.codigo_cuenta, c.nombre_cuenta, c.saldo_actual FROM contabilidad_cuentas c WHERE c.id_empresa = ? AND c.tipo_cuenta = ? AND c.nivel >= 4 AND c.saldo_actual != 0 ORDER BY c.codigo_cuenta ASC");
    $stmt->execute([$empresa_id, $tipo]);
    return $stmt->fetchAll();
};

$ingresos = $getSumByTipo('Ingresos');
$costos = $getSumByTipo('Costos');
$gastos = $getSumByTipo('Gastos');

$totalIngresos = array_sum(array_column($ingresos, 'saldo_actual'));
$totalCostos = array_sum(array_column($costos, 'saldo_actual'));
$totalGastos = array_sum(array_column($gastos, 'saldo_actual'));

$utilidadBruta = $totalIngresos - $totalCostos;
$utilidadOperacion = $utilidadBruta - $totalGastos;

// Tax calculation based on company regime
if ($empresa_actual['regimen_isr'] === 'Opcional Simplificado') {
    $provisionIsr = calcular_isr_opcional_mensual($totalIngresos);
} else {
    $provisionIsr = max(0, $utilidadOperacion * 0.25);
}

$utilidadNeta = $utilidadOperacion - $provisionIsr;

// Balance Sheet Data
$activos = $getSumByTipo('Activo');
$pasivos = $getSumByTipo('Pasivo');
$patrimonio = $getSumByTipo('Patrimonio');

$totalActivo = array_sum(array_column($activos, 'saldo_actual'));
$totalPasivo = array_sum(array_column($pasivos, 'saldo_actual'));
$totalPatrimonioSinUtilidad = array_sum(array_column($patrimonio, 'saldo_actual'));
$totalPatrimonioFinal = $totalPatrimonioSinUtilidad + $utilidadNeta;
$totalPasivoMasPatrimonio = $totalPasivo + $totalPatrimonioFinal;
?>

<div class="print-header">
    <h1><?= htmlspecialchars($empresa_actual['razon_social']) ?></h1>
    <p>NIT: <?= htmlspecialchars($empresa_actual['nit']) ?> | Guatemala</p>
    <h2>
        <?php
        if ($reporte === 'resultados') echo 'ESTADO DE RESULTADOS';
        elseif ($reporte === 'balance') echo 'BALANCE GENERAL';
        elseif ($reporte === 'flujo') echo 'ESTADO DE FLUJO DE EFECTIVO';
        else echo 'ESTADO DE CAMBIOS EN EL PATRIMONIO';
        ?>
    </h2>
    <p>Al 31 de Diciembre de <?= $anio ?> (Cifras expresadas en Quetzales GTQ)</p>
</div>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Estados Financieros Oficiales</h1>
        <p class="page-subtitle">Formulación y emisión de reportes financieros bajo NIIF / Leyes GT</p>
    </div>
    <button class="btn btn-secondary" onclick="window.print()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir Estado Financiero
    </button>
</div>

<!-- Tabs Navigation -->
<div class="card no-print" style="margin-bottom:20px;">
    <div class="tabs" style="margin-bottom:0; border-bottom:none;">
        <a href="?reporte=resultados" class="tab-btn <?= ($reporte === 'resultados') ? 'active' : '' ?>">1. Estado de Resultados</a>
        <a href="?reporte=balance" class="tab-btn <?= ($reporte === 'balance') ? 'active' : '' ?>">2. Balance General</a>
        <a href="?reporte=flujo" class="tab-btn <?= ($reporte === 'flujo') ? 'active' : '' ?>">3. Estado de Flujo de Efectivo</a>
        <a href="?reporte=patrimonio" class="tab-btn <?= ($reporte === 'patrimonio') ? 'active' : '' ?>">4. Cambios en el Patrimonio</a>
    </div>
</div>

<?php if ($reporte === 'resultados'): ?>
    <!-- ESTADO DE RESULTADOS -->
    <div class="card" style="max-width:850px; margin:0 auto;">
        <h3 style="font-size:16px; font-weight:700; border-bottom:2px solid var(--primary); padding-bottom:8px; margin-bottom:15px; color:var(--secondary);">
            INGRESOS OPERATIVOS
        </h3>
        <table class="table" style="margin-bottom:20px;">
            <tbody>
                <?php foreach ($ingresos as $ing): ?>
                    <tr>
                        <td style="font-family:monospace; color:var(--primary); width:20%;"><?= htmlspecialchars($ing['codigo_cuenta']) ?></td>
                        <td><?= htmlspecialchars($ing['nombre_cuenta']) ?></td>
                        <td class="text-right" style="width:30%;"><?= format_gtq($ing['saldo_actual']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#f8fafc; font-weight:700;">
                    <td colspan="2">TOTAL INGRESOS BRUTOS:</td>
                    <td class="text-right" style="color:var(--success); font-size:15px;"><?= format_gtq($totalIngresos) ?></td>
                </tr>
            </tbody>
        </table>

        <h3 style="font-size:16px; font-weight:700; border-bottom:2px solid var(--warning); padding-bottom:8px; margin-bottom:15px; color:var(--secondary);">
            COSTO DE VENTAS
        </h3>
        <table class="table" style="margin-bottom:20px;">
            <tbody>
                <?php foreach ($costos as $cst): ?>
                    <tr>
                        <td style="font-family:monospace; color:var(--warning); width:20%;"><?= htmlspecialchars($cst['codigo_cuenta']) ?></td>
                        <td><?= htmlspecialchars($cst['nombre_cuenta']) ?></td>
                        <td class="text-right" style="width:30%;"><?= format_gtq($cst['saldo_actual']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#f8fafc; font-weight:700;">
                    <td colspan="2">TOTAL COSTOS:</td>
                    <td class="text-right"><?= format_gtq($totalCostos) ?></td>
                </tr>
                <tr style="background:#eff6ff; font-weight:800;">
                    <td colspan="2">UTILIDAD BRUTA EN VENTAS:</td>
                    <td class="text-right" style="color:var(--primary); font-size:15px;"><?= format_gtq($utilidadBruta) ?></td>
                </tr>
            </tbody>
        </table>

        <h3 style="font-size:16px; font-weight:700; border-bottom:2px solid var(--danger); padding-bottom:8px; margin-bottom:15px; color:var(--secondary);">
            GASTOS DE OPERACIÓN Y ADMINISTRACIÓN
        </h3>
        <table class="table" style="margin-bottom:20px;">
            <tbody>
                <?php foreach ($gastos as $gst): ?>
                    <tr>
                        <td style="font-family:monospace; color:var(--danger); width:20%;"><?= htmlspecialchars($gst['codigo_cuenta']) ?></td>
                        <td><?= htmlspecialchars($gst['nombre_cuenta']) ?></td>
                        <td class="text-right" style="width:30%;"><?= format_gtq($gst['saldo_actual']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#f8fafc; font-weight:700;">
                    <td colspan="2">TOTAL GASTOS DE OPERACIÓN:</td>
                    <td class="text-right" style="color:var(--danger);"><?= format_gtq($totalGastos) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Totals Summary -->
        <div style="background:#0f172a; color:#fff; padding:20px; border-radius:10px; margin-top:20px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span>Utilidad de Operación Antes de Impuesto:</span>
                <strong><?= format_gtq($utilidadOperacion) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:#f59e0b;">
                <span>(-) Provisión Impuesto Sobre la Renta (ISR <?= htmlspecialchars($empresa_actual['regimen_isr']) ?>):</span>
                <strong><?= format_gtq($provisionIsr) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:800; border-top:1px solid #334155; padding-top:10px; color:#10b981;">
                <span>UTILIDAD NETA DEL EJERCICIO:</span>
                <span><?= format_gtq($utilidadNeta) ?></span>
            </div>
        </div>
    </div>

<?php elseif ($reporte === 'balance'): ?>
    <!-- BALANCE GENERAL -->
    <div style="display:flex; justify-content:center; margin-bottom:15px;">
        <span class="badge <?= (abs($totalActivo - $totalPasivoMasPatrimonio) < 0.01) ? 'badge-success' : 'badge-danger' ?>" style="font-size:14px; padding:8px 16px;">
            <?= (abs($totalActivo - $totalPasivoMasPatrimonio) < 0.01) ? '✓ Balance General Cuadrado (Activo = Pasivo + Patrimonio)' : '⚠️ Balance Descuadrado' ?>
        </span>
    </div>

    <div class="grid grid-cols-2" style="max-width:1000px; margin:0 auto;">
        <!-- ACTIVO -->
        <div class="card">
            <h3 style="font-size:16px; font-weight:700; border-bottom:2px solid var(--primary); padding-bottom:8px; margin-bottom:15px; color:var(--secondary);">
                ACTIVO
            </h3>
            <table class="table">
                <tbody>
                    <?php foreach ($activos as $act): ?>
                        <tr>
                            <td style="font-family:monospace; color:var(--primary);"><?= htmlspecialchars($act['codigo_cuenta']) ?></td>
                            <td><?= htmlspecialchars($act['nombre_cuenta']) ?></td>
                            <td class="text-right"><?= format_gtq($act['saldo_actual']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#eff6ff; font-weight:800;">
                        <td colspan="2">TOTAL ACTIVO:</td>
                        <td class="text-right" style="color:var(--primary); font-size:15px;"><?= format_gtq($totalActivo) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- PASIVO Y PATRIMONIO -->
        <div class="card">
            <h3 style="font-size:16px; font-weight:700; border-bottom:2px solid var(--warning); padding-bottom:8px; margin-bottom:15px; color:var(--secondary);">
                PASIVO
            </h3>
            <table class="table" style="margin-bottom:20px;">
                <tbody>
                    <?php foreach ($pasivos as $pas): ?>
                        <tr>
                            <td style="font-family:monospace; color:var(--warning);"><?= htmlspecialchars($pas['codigo_cuenta']) ?></td>
                            <td><?= htmlspecialchars($pas['nombre_cuenta']) ?></td>
                            <td class="text-right"><?= format_gtq($pas['saldo_actual']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#fffbeb; font-weight:800;">
                        <td colspan="2">TOTAL PASIVO:</td>
                        <td class="text-right"><?= format_gtq($totalPasivo) ?></td>
                    </tr>
                </tfoot>
            </table>

            <h3 style="font-size:16px; font-weight:700; border-bottom:2px solid var(--success); padding-bottom:8px; margin-bottom:15px; color:var(--secondary);">
                PATRIMONIO NETO
            </h3>
            <table class="table">
                <tbody>
                    <?php foreach ($patrimonio as $pat): ?>
                        <tr>
                            <td style="font-family:monospace; color:var(--success);"><?= htmlspecialchars($pat['codigo_cuenta']) ?></td>
                            <td><?= htmlspecialchars($pat['nombre_cuenta']) ?></td>
                            <td class="text-right"><?= format_gtq($pat['saldo_actual']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td style="font-family:monospace; color:var(--success);">3.1.02.02</td>
                        <td>Utilidad Neta del Ejercicio Actual</td>
                        <td class="text-right" style="font-weight:700; color:var(--success);"><?= format_gtq($utilidadNeta) ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style="background:#ecfdf5; font-weight:800;">
                        <td colspan="2">TOTAL PATRIMONIO:</td>
                        <td class="text-right" style="color:var(--success);"><?= format_gtq($totalPatrimonioFinal) ?></td>
                    </tr>
                    <tr style="background:#0f172a; color:#fff; font-weight:800;">
                        <td colspan="2">TOTAL PASIVO + PATRIMONIO:</td>
                        <td class="text-right" style="font-size:15px;"><?= format_gtq($totalPasivoMasPatrimonio) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php elseif ($reporte === 'flujo'): ?>
    <!-- ESTADO DE FLUJO DE EFECTIVO -->
    <div class="card" style="max-width:800px; margin:0 auto;">
        <h3 style="font-size:16px; font-weight:700; margin-bottom:15px; color:var(--secondary);">Flujo de Efectivo por Actividades</h3>
        <table class="table">
            <tbody>
                <tr style="background:#f8fafc; font-weight:700;"><td colspan="2">ACTIVIDADES DE OPERACIÓN</td></tr>
                <tr><td>Recaudación por Ventas de Bienes y Servicios</td><td class="text-right" style="color:var(--success);"><?= format_gtq($totalIngresos) ?></td></tr>
                <tr><td>Pagos a Proveedores por Compras de Mercaderías</td><td class="text-right" style="color:var(--danger);"><?= format_gtq(-$totalCostos) ?></td></tr>
                <tr><td>Pagos de Sueldos y Gastos Administrativos</td><td class="text-right" style="color:var(--danger);"><?= format_gtq(-$totalGastos) ?></td></tr>
                <tr style="font-weight:800; background:#f1f5f9;"><td>Efectivo Neto Provisto por Actividades de Operación</td><td class="text-right"><?= format_gtq($utilidadOperacion) ?></td></tr>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <!-- CAMBIOS EN EL PATRIMONIO -->
    <div class="card" style="max-width:800px; margin:0 auto;">
        <h3 style="font-size:16px; font-weight:700; margin-bottom:15px; color:var(--secondary);">Estado de Cambios en el Patrimonio Neto</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Concepto Patrimonio</th>
                    <th class="text-right">Saldo Inicial</th>
                    <th class="text-right">Incrementos / Utilidad</th>
                    <th class="text-right">Saldo Final</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Capital Social Pagado</td>
                    <td class="text-right"><?= format_gtq($totalPatrimonioSinUtilidad) ?></td>
                    <td class="text-right">Q 0.00</td>
                    <td class="text-right"><strong><?= format_gtq($totalPatrimonioSinUtilidad) ?></strong></td>
                </tr>
                <tr>
                    <td>Utilidad Neta del Ejercicio</td>
                    <td class="text-right">Q 0.00</td>
                    <td class="text-right" style="color:var(--success);"><?= format_gtq($utilidadNeta) ?></td>
                    <td class="text-right"><strong><?= format_gtq($utilidadNeta) ?></strong></td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background:#0f172a; color:#fff; font-weight:800;">
                    <td>TOTAL PATRIMONIO NETO:</td>
                    <td class="text-right"><?= format_gtq($totalPatrimonioSinUtilidad) ?></td>
                    <td class="text-right"><?= format_gtq($utilidadNeta) ?></td>
                    <td class="text-right"><?= format_gtq($totalPatrimonioFinal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

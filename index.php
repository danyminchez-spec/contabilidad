<?php
require_once __DIR__ . '/includes/header.php';

$empresa_id = get_active_empresa_id();

// Query KPIs for active company
// 1. Total Ventas del Mes
$stmtVentas = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total, COALESCE(SUM(monto_iva), 0) as iva FROM contabilidad_libro_ventas WHERE id_empresa = ? AND MONTH(fecha_factura) = MONTH(CURRENT_DATE()) AND YEAR(fecha_factura) = YEAR(CURRENT_DATE())");
$stmtVentas->execute([$empresa_id]);
$resVentas = $stmtVentas->fetch();
$ventasMes = $resVentas['total'];
$ivaDebitoMes = $resVentas['iva'];

// 2. Total Compras del Mes
$stmtCompras = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total, COALESCE(SUM(monto_iva), 0) as iva FROM contabilidad_libro_compras WHERE id_empresa = ? AND MONTH(fecha_factura) = MONTH(CURRENT_DATE()) AND YEAR(fecha_factura) = YEAR(CURRENT_DATE())");
$stmtCompras->execute([$empresa_id]);
$resCompras = $stmtCompras->fetch();
$comprasMes = $resCompras['total'];
$ivaCreditoMes = $resCompras['iva'];

// 3. Balance IVA (Débito - Crédito)
$balanceIva = $ivaDebitoMes - $ivaCreditoMes;

// 4. Total Empleados & Nómina Activa
$stmtEmpCount = $pdo->prepare("SELECT COUNT(*) FROM contabilidad_empleados WHERE id_empresa = ? AND estado = 1");
$stmtEmpCount->execute([$empresa_id]);
$totalEmpleados = $stmtEmpCount->fetchColumn();

// 5. Ultimas Partidas
$stmtPartidas = $pdo->prepare("SELECT p.*, cc.nombre as centro_costo FROM contabilidad_partidas p LEFT JOIN contabilidad_centros_costo cc ON p.id_centro_costo = cc.id WHERE p.id_empresa = ? ORDER BY p.fecha DESC, p.correlativo DESC LIMIT 5");
$stmtPartidas->execute([$empresa_id]);
$ultimasPartidas = $stmtPartidas->fetchAll();

// Chart Data (Ingresos vs Gastos últimos 6 meses)
$monthsLabel = [];
$ingresosData = [];
$gastosData = [];

for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("-$i months");
    $m = date('m', $time);
    $y = date('Y', $time);
    $monthsLabel[] = date('M Y', $time);

    // Sum Ingresos (Cuenta 4%)
    $stmtIng = $pdo->prepare("SELECT COALESCE(SUM(d.haber - d.debe), 0) FROM contabilidad_partida_detalles d JOIN contabilidad_partidas p ON d.id_partida = p.id JOIN contabilidad_cuentas c ON d.id_cuenta = c.id WHERE p.id_empresa = ? AND c.tipo_cuenta = 'Ingresos' AND MONTH(p.fecha) = ? AND YEAR(p.fecha) = ?");
    $stmtIng->execute([$empresa_id, $m, $y]);
    $ingresosData[] = (float)$stmtIng->fetchColumn();

    // Sum Gastos (Cuenta 6%)
    $stmtGas = $pdo->prepare("SELECT COALESCE(SUM(d.debe - d.haber), 0) FROM contabilidad_partida_detalles d JOIN contabilidad_partidas p ON d.id_partida = p.id JOIN contabilidad_cuentas c ON d.id_cuenta = c.id WHERE p.id_empresa = ? AND c.tipo_cuenta = 'Gastos' AND MONTH(p.fecha) = ? AND YEAR(p.fecha) = ?");
    $stmtGas->execute([$empresa_id, $m, $y]);
    $gastosData[] = (float)$stmtGas->fetchColumn();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Contable</h1>
        <p class="page-subtitle">Visión general financiera y fiscal para <?= htmlspecialchars($empresa_actual['razon_social']) ?></p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="/contabilidad/modules/operaciones/partidas.php?action=new" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva Partida
        </a>
        <a href="/contabilidad/modules/fel/dte.php" class="btn btn-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Emitir Factura FEL
        </a>
    </div>
</div>

<!-- KPI Cards Grid -->
<div class="grid grid-cols-4" style="margin-bottom: 24px;">
    <div class="card kpi-card">
        <div>
            <div class="kpi-label">Ventas del Mes (Bruto)</div>
            <div class="kpi-value"><?= format_gtq($ventasMes) ?></div>
            <span style="font-size:11px; color:var(--text-muted);">IVA Débito: <?= format_gtq($ivaDebitoMes) ?></span>
        </div>
        <div class="kpi-icon kpi-icon-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
    </div>

    <div class="card kpi-card">
        <div>
            <div class="kpi-label">Compras del Mes (Bruto)</div>
            <div class="kpi-value"><?= format_gtq($comprasMes) ?></div>
            <span style="font-size:11px; color:var(--text-muted);">IVA Crédito: <?= format_gtq($ivaCreditoMes) ?></span>
        </div>
        <div class="kpi-icon kpi-icon-amber">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
        </div>
    </div>

    <div class="card kpi-card">
        <div>
            <div class="kpi-label">Balance IVA (SAT)</div>
            <div class="kpi-value" style="color: <?= $balanceIva >= 0 ? 'var(--danger)' : 'var(--success)' ?>">
                <?= format_gtq(abs($balanceIva)) ?>
            </div>
            <span class="badge <?= $balanceIva >= 0 ? 'badge-danger' : 'badge-success' ?>">
                <?= $balanceIva >= 0 ? 'IVA por Pagar (Débito > Crédito)' : 'Remanente Crédito Fiscal' ?>
            </span>
        </div>
        <div class="kpi-icon kpi-icon-green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v2m0 8v2"/></svg>
        </div>
    </div>

    <div class="card kpi-card">
        <div>
            <div class="kpi-label">Planilla Activa</div>
            <div class="kpi-value"><?= $totalEmpleados ?> Empleados</div>
            <span style="font-size:11px; color:var(--text-muted);">IGSS 4.83% Lab / 10.67% Pat</span>
        </div>
        <div class="kpi-icon kpi-icon-purple">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
    </div>
</div>

<!-- Charts & Tables Grid -->
<div class="grid grid-cols-3" style="grid-template-columns: 2fr 1fr; margin-bottom: 24px;">
    <div class="card">
        <h3 style="font-size:15px; font-weight:700; margin-bottom:15px; color:var(--secondary);">Evolución Financiera (Ingresos vs Gastos)</h3>
        <div style="height: 280px; position: relative;">
            <canvas id="chartFinanzas"></canvas>
        </div>
    </div>

    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <h3 style="font-size:15px; font-weight:700; margin-bottom:12px; color:var(--secondary);">Resumen Fiscal SAT</h3>
            <div style="margin-bottom: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid var(--border);">
                <span style="font-size:11px; font-weight:700; color:var(--text-muted);">RÉGIMEN ISR ACTUAL</span>
                <div style="font-size:14px; font-weight:700; color:var(--primary); margin-top:2px;">
                    <?= htmlspecialchars($empresa_actual['regimen_isr']) ?>
                </div>
                <small style="font-size:11px; color:var(--text-muted);">
                    <?= $empresa_actual['regimen_isr'] === 'Opcional Simplificado' ? '5% hasta Q30,000 / 7% sobre excedente' : '25% sobre utilidad fiscal computable' ?>
                </small>
            </div>

            <div style="margin-bottom: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid var(--border);">
                <span style="font-size:11px; font-weight:700; color:var(--text-muted);">CODIGO DE COMERCIO (ART 368-381)</span>
                <div style="font-size:13px; font-weight:600; margin-top:2px;">Libros Obligatorios al día</div>
                <small style="font-size:11px; color:var(--success); font-weight:600;">✓ Diario, Mayor, Inventarios y Balances</small>
            </div>

            <div style="margin-bottom: 12px; padding: 12px; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
                <span style="font-size:11px; font-weight:700; color:#166534;">EXPIRACIÓN DE LICENCIA DE SISTEMA</span>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                    <strong style="font-size:13px; color:#14532d;">Suscripción <?= htmlspecialchars($licencia_actual['tipo_licencia']) ?></strong>
                    <span class="badge badge-success" style="font-size:11px;">
                        Expira: <?= date('d/m/Y', strtotime($licencia_actual['fecha_expiracion'])) ?>
                    </span>
                </div>
                <small style="font-size:11px; color:#15803d; display:block; margin-top:4px; font-family:monospace;">
                    Serial: <?= htmlspecialchars($licencia_actual['clave_licencia']) ?>
                </small>
            </div>
        </div>

        <a href="/contabilidad/modules/fiscal/declaraguate.php" class="btn btn-secondary" style="width: 100%; justify-content: center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exportar a Declaraguate (.csv/.txt)
        </a>
    </div>
</div>

<!-- Ultimas Partidas Registradas -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h3 style="font-size:15px; font-weight:700; color:var(--secondary);">Últimos Asientos Contables (Libro Diario)</h3>
        <a href="/contabilidad/modules/operaciones/partidas.php" style="font-size:12px; font-weight:600; color:var(--primary); text-decoration:none;">Ver Todas las Partidas &rarr;</a>
    </div>

    <div class="table-container" style="margin-top:0;">
        <table class="table">
            <thead>
                <tr>
                    <th># Partida</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Centro de Costo</th>
                    <th>Concepto / Glosa</th>
                    <th class="text-right">Total Debe</th>
                    <th class="text-right">Total Haber</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimasPartidas)): ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 20px; color: var(--text-muted);">No hay partidas registradas aún.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ultimasPartidas as $p): ?>
                        <tr>
                            <td><strong>Partida #<?= $p['correlativo'] ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($p['tipo_partida']) ?></span></td>
                            <td><?= htmlspecialchars($p['centro_costo'] ?: 'General') ?></td>
                            <td><?= htmlspecialchars($p['concepto']) ?></td>
                            <td class="text-right"><strong><?= format_gtq($p['total_debe']) ?></strong></td>
                            <td class="text-right"><strong><?= format_gtq($p['total_haber']) ?></strong></td>
                            <td class="text-center"><span class="badge badge-success">Cuadrada</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('chartFinanzas').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthsLabel) ?>,
            datasets: [
                {
                    label: 'Ingresos (GTQ)',
                    data: <?= json_encode($ingresosData) ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 6
                },
                {
                    label: 'Gastos (GTQ)',
                    data: <?= json_encode($gastosData) ?>,
                    backgroundColor: '#f59e0b',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'Q ' + value.toLocaleString(); }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

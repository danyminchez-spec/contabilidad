<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();

$tipoLibro = $_GET['libro'] ?? 'diario';
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-t');

// Query data based on selected book
if ($tipoLibro === 'diario') {
    $stmt = $pdo->prepare("SELECT p.* FROM contabilidad_partidas p WHERE p.id_empresa = ? AND p.fecha BETWEEN ? AND ? ORDER BY p.fecha ASC, p.correlativo ASC");
    $stmt->execute([$empresa_id, $fechaInicio, $fechaFin]);
    $partidasDiario = $stmt->fetchAll();

    // Fetch details for each partida
    foreach ($partidasDiario as &$pt) {
        $stmtDet = $pdo->prepare("SELECT d.*, c.codigo_cuenta, c.nombre_cuenta FROM contabilidad_partida_detalles d JOIN contabilidad_cuentas c ON d.id_cuenta = c.id WHERE d.id_partida = ? ORDER BY d.orden ASC");
        $stmtDet->execute([$pt['id']]);
        $pt['detalles'] = $stmtDet->fetchAll();
    }
} elseif ($tipoLibro === 'mayor') {
    // Mayor query: Group by account
    $stmt = $pdo->prepare("SELECT c.id, c.codigo_cuenta, c.nombre_cuenta, c.tipo_cuenta, COALESCE(SUM(d.debe), 0) as total_debe, COALESCE(SUM(d.haber), 0) as total_haber FROM contabilidad_cuentas c LEFT JOIN contabilidad_partida_detalles d ON c.id = d.id_cuenta LEFT JOIN contabilidad_partidas p ON d.id_partida = p.id AND p.fecha BETWEEN ? AND ? WHERE c.id_empresa = ? GROUP BY c.id ORDER BY c.codigo_cuenta ASC");
    $stmt->execute([$fechaInicio, $fechaFin, $empresa_id]);
    $cuentasMayor = $stmt->fetchAll();
} elseif ($tipoLibro === 'inventarios') {
    // Inventarios query: Inventories & Assets accounts
    $stmt = $pdo->prepare("SELECT c.codigo_cuenta, c.nombre_cuenta, c.tipo_cuenta, c.saldo_actual FROM contabilidad_cuentas c WHERE c.id_empresa = ? AND (c.tipo_cuenta = 'Activo' OR c.tipo_cuenta = 'Pasivo') AND c.nivel >= 4 ORDER BY c.codigo_cuenta ASC");
    $stmt->execute([$empresa_id]);
    $inventariosData = $stmt->fetchAll();
}
?>

<!-- Header for Printing -->
<div class="print-header">
    <h1><?= htmlspecialchars($empresa_actual['razon_social']) ?></h1>
    <p>NIT: <?= htmlspecialchars($empresa_actual['nit']) ?> | Guatemala</p>
    <h2>
        <?= ($tipoLibro === 'diario') ? 'LIBRO DIARIO' : (($tipoLibro === 'mayor') ? 'LIBRO MAYOR' : 'LIBRO DE INVENTARIOS') ?>
    </h2>
    <p>Correspondiente del <?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?> (En Quetzales GTQ)</p>
    <p><small>Cumplimiento Artículos 368 al 381 del Código de Comercio de Guatemala</small></p>
</div>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Libros Obligatorios de Comercio</h1>
        <p class="page-subtitle">Exigidos legalmente por el Código de Comercio de Guatemala (Art. 368 - 381)</p>
    </div>
    <button class="btn btn-secondary" onclick="window.print()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir Libro (PDF)
    </button>
</div>

<!-- Tabs & Filters -->
<div class="card no-print" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
        <div class="tabs" style="margin-bottom:0; border-bottom:none;">
            <a href="?libro=diario&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>" class="tab-btn <?= ($tipoLibro === 'diario') ? 'active' : '' ?>">1. Libro Diario</a>
            <a href="?libro=mayor&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>" class="tab-btn <?= ($tipoLibro === 'mayor') ? 'active' : '' ?>">2. Libro Mayor</a>
            <a href="?libro=inventarios&fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>" class="tab-btn <?= ($tipoLibro === 'inventarios') ? 'active' : '' ?>">3. Libro de Inventarios</a>
        </div>

        <form method="GET" action="" style="display:flex; align-items:center; gap:10px;">
            <input type="hidden" name="libro" value="<?= $tipoLibro ?>">
            <label style="font-size:12px; font-weight:700;">Rango:</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?= $fechaInicio ?>" style="padding:5px 10px;">
            <input type="date" name="fecha_fin" class="form-control" value="<?= $fechaFin ?>" style="padding:5px 10px;">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </div>
</div>

<?php if ($tipoLibro === 'diario'): ?>
    <!-- LIBRO DIARIO -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 12%;">Fecha / # Partida</th>
                    <th style="width: 15%;">Código Cuenta</th>
                    <th style="width: 45%;">Cuenta / Concepto</th>
                    <th class="text-right" style="width: 14%;">Debe (GTQ)</th>
                    <th class="text-right" style="width: 14%;">Haber (GTQ)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($partidasDiario)): ?>
                    <tr><td colspan="5" class="text-center" style="padding:25px; color:var(--text-muted);">No hay partidas registradas en el período seleccionado.</td></tr>
                <?php else: ?>
                    <?php foreach ($partidasDiario as $pt): ?>
                        <tr style="background-color: #f1f5f9; font-weight:700;">
                            <td><?= date('d/m/Y', strtotime($pt['fecha'])) ?></td>
                            <td colspan="2">P-<?= $pt['correlativo'] ?>: <?= htmlspecialchars($pt['concepto']) ?></td>
                            <td class="text-right"><?= format_gtq($pt['total_debe']) ?></td>
                            <td class="text-right"><?= format_gtq($pt['total_haber']) ?></td>
                        </tr>
                        <?php foreach ($pt['detalles'] as $d): ?>
                            <tr>
                                <td></td>
                                <td style="font-family:monospace; font-weight:600; color:var(--primary);"><?= htmlspecialchars($d['codigo_cuenta']) ?></td>
                                <td style="padding-left: <?= ($d['haber'] > 0) ? '30px' : '15px' ?>;">
                                    <?= htmlspecialchars($d['nombre_cuenta']) ?>
                                    <?php if ($d['concepto_linea']): ?>
                                        <small style="color:var(--text-muted); display:block;"><?= htmlspecialchars($d['concepto_linea']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right"><?= ($d['debe'] > 0) ? format_gtq($d['debe']) : '-' ?></td>
                                <td class="text-right"><?= ($d['haber'] > 0) ? format_gtq($d['haber']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($tipoLibro === 'mayor'): ?>
    <!-- LIBRO MAYOR -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código Cuenta</th>
                    <th>Nombre de la Cuenta</th>
                    <th>Tipo</th>
                    <th class="text-right">Suma Debe</th>
                    <th class="text-right">Suma Haber</th>
                    <th class="text-right">Saldo Deudor / Acreedor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cuentasMayor as $cm): ?>
                    <?php 
                    $saldo = $cm['total_debe'] - $cm['total_haber'];
                    if ($cm['total_debe'] == 0 && $cm['total_haber'] == 0) continue;
                    ?>
                    <tr>
                        <td style="font-family:monospace; font-weight:700; color:var(--primary);"><?= htmlspecialchars($cm['codigo_cuenta']) ?></td>
                        <td><strong><?= htmlspecialchars($cm['nombre_cuenta']) ?></strong></td>
                        <td><span class="badge badge-secondary"><?= $cm['tipo_cuenta'] ?></span></td>
                        <td class="text-right"><?= format_gtq($cm['total_debe']) ?></td>
                        <td class="text-right"><?= format_gtq($cm['total_haber']) ?></td>
                        <td class="text-right" style="font-weight:700; color: <?= $saldo >= 0 ? 'var(--primary)' : 'var(--danger)' ?>;">
                            <?= format_gtq($saldo) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <!-- LIBRO DE INVENTARIOS -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código Cuenta</th>
                    <th>Descripción Rubro / Existencia</th>
                    <th>Tipo de Elemento</th>
                    <th class="text-right">Valor Valuación Contable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventariosData as $inv): ?>
                    <tr>
                        <td style="font-family:monospace; font-weight:700; color:var(--primary);"><?= htmlspecialchars($inv['codigo_cuenta']) ?></td>
                        <td><strong><?= htmlspecialchars($inv['nombre_cuenta']) ?></strong></td>
                        <td><span class="badge badge-info"><?= $inv['tipo_cuenta'] ?></span></td>
                        <td class="text-right"><strong><?= format_gtq($inv['saldo_actual']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

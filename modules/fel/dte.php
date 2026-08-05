<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();

// Handle DTE View
$viewDteId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$selectedDte = null;
if ($viewDteId) {
    $stmt = $pdo->prepare("SELECT * FROM contabilidad_fel_dte WHERE id = ? AND id_empresa = ?");
    $stmt->execute([$viewDteId, $empresa_id]);
    $selectedDte = $stmt->fetch();
}

// Fetch all DTEs
$stmtDtes = $pdo->prepare("SELECT * FROM contabilidad_fel_dte WHERE id_empresa = ? ORDER BY fecha_emision DESC");
$stmtDtes->execute([$empresa_id]);
$dtes = $stmtDtes->fetchAll();
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">FEL (Factura Electrónica en Línea - SAT)</h1>
        <p class="page-subtitle">Emisión y consulta de Documentos Tributarios Electrónicos con Certificador Autorizado</p>
    </div>
    <a href="/contabilidad/modules/fiscal/compras_ventas.php?tab=ventas" class="btn btn-success">
        + Emitir Nuevo DTE (Factura FEL)
    </a>
</div>

<?php if ($selectedDte): ?>
    <!-- DTE VISUALIZER (FORMATO OFICIAL SAT FEL) -->
    <div class="card" style="max-width: 800px; margin: 0 auto 30px; border: 2px solid var(--primary); padding: 30px;">
        <div style="display:flex; justify-content:space-between; border-bottom:2px solid var(--border); padding-bottom:15px; margin-bottom:20px;">
            <div>
                <h2 style="font-size:18px; font-weight:800; color:var(--primary);"><?= htmlspecialchars($empresa_actual['razon_social']) ?></h2>
                <p style="font-size:13px; color:var(--text-main);">
                    <strong>Nombre Comercial:</strong> <?= htmlspecialchars($empresa_actual['nombre_comercial']) ?><br>
                    <strong>NIT Emisor:</strong> <?= htmlspecialchars($selectedDte['nit_emisor']) ?><br>
                    <strong>Dirección:</strong> <?= htmlspecialchars($empresa_actual['direccion'] ?: 'Ciudad de Guatemala') ?>
                </p>
            </div>
            <div style="text-align:right;">
                <span class="badge badge-info" style="font-size:14px; padding:6px 12px; margin-bottom:8px; display:inline-block;">
                    DOCUMENTO TRIBUTARIO ELECTRÓNICO
                </span>
                <h3 style="font-size:16px; font-weight:800; color:var(--secondary);"><?= htmlspecialchars($selectedDte['tipo_dte']) ?></h3>
                <p style="font-size:13px; font-weight:700; color:var(--primary);">
                    Serie: <?= htmlspecialchars($selectedDte['serie']) ?> | Número: <?= htmlspecialchars($selectedDte['numero']) ?>
                </p>
            </div>
        </div>

        <!-- UUID & SAT Certificate Header -->
        <div style="background:#f8fafc; border:1px solid var(--border); padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:12px; font-family:monospace;">
            <div><strong>NÚMERO DE AUTORIZACIÓN (UUID):</strong> <?= htmlspecialchars($selectedDte['uuid']) ?></div>
            <div><strong>FECHA DE CERTIFICACIÓN SAT:</strong> <?= date('d/m/Y H:i:s', strtotime($selectedDte['fecha_emision'])) ?></div>
            <div><strong>CERTIFICADOR AUTORIZADO SAT:</strong> INFILE, S.A. (Certificador DTE Guatemala)</div>
        </div>

        <!-- Receptor Details -->
        <div style="margin-bottom:20px; font-size:13px;">
            <div style="background:#f1f5f9; padding:8px 12px; font-weight:700; border-radius:6px 6px 0 0;">DATOS DEL RECEPTOR</div>
            <div style="border:1px solid var(--border); border-top:none; padding:12px; border-radius:0 0 6px 6px;">
                <strong>NIT Receptor:</strong> <?= htmlspecialchars($selectedDte['nit_receptor']) ?><br>
                <strong>Fecha Emisión:</strong> <?= date('d/m/Y', strtotime($selectedDte['fecha_emision'])) ?>
            </div>
        </div>

        <!-- Line Details Table -->
        <table class="table" style="margin-bottom:20px;">
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Descripción de Servicios / Productos</th>
                    <th class="text-right">Precio Unitario</th>
                    <th class="text-right">Total (GTQ)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Servicios Profesionales de Consultoría y Asesoría Contable</td>
                    <td class="text-right"><?= format_gtq($selectedDte['monto_total']) ?></td>
                    <td class="text-right"><strong><?= format_gtq($selectedDte['monto_total']) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Totals & QR -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-top:2px solid var(--border); padding-top:15px;">
            <div style="display:flex; align-items:center; gap:15px;">
                <div style="width:90px; height:90px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px; text-align:center; color:var(--text-muted); font-weight:700;">
                    QR DTE SAT<br>VALIDADO
                </div>
                <div style="font-size:11px; color:var(--text-muted);">
                    Sujeto a pagos trimestrales ISR / IVA 12% incluido<br>
                    Este documento es una representación gráfica de un DTE.
                </div>
            </div>
            <div style="width:250px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:13px;">
                    <span>Monto Neto (Afecto):</span>
                    <span><?= format_gtq($selectedDte['monto_neto']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:13px; color:var(--danger);">
                    <span>IVA (12%):</span>
                    <span><?= format_gtq($selectedDte['monto_iva']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:16px; font-weight:800; border-top:1px solid var(--border); padding-top:6px; color:var(--primary);">
                    <span>TOTAL:</span>
                    <span><?= format_gtq($selectedDte['monto_total']) ?></span>
                </div>
            </div>
        </div>

        <div class="no-print" style="margin-top:20px; text-align:right;">
            <button class="btn btn-secondary" onclick="window.print()">Imprimir DTE</button>
            <a href="dte.php" class="btn btn-primary">Volver al Listado</a>
        </div>
    </div>

<?php else: ?>
    <!-- LIST OF DTES -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha Emisión</th>
                    <th>Tipo DTE</th>
                    <th>Serie / Número</th>
                    <th>UUID (GUID SAT)</th>
                    <th>NIT Receptor</th>
                    <th class="text-right">Monto Neto</th>
                    <th class="text-right">IVA (12%)</th>
                    <th class="text-right">Total (GTQ)</th>
                    <th class="text-center">Estado FEL</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dtes)): ?>
                    <tr><td colspan="10" class="text-center" style="padding:25px; color:var(--text-muted);">No hay DTEs emitidos aún.</td></tr>
                <?php else: ?>
                    <?php foreach ($dtes as $d): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($d['fecha_emision'])) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($d['tipo_dte']) ?></span></td>
                            <td><strong><?= htmlspecialchars($d['serie'] . '-' . $d['numero']) ?></strong></td>
                            <td><small style="font-family:monospace;"><?= htmlspecialchars(substr($d['uuid'], 0, 18)) ?>...</small></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($d['nit_receptor']) ?></td>
                            <td class="text-right"><?= format_gtq($d['monto_neto']) ?></td>
                            <td class="text-right" style="color:var(--danger);"><?= format_gtq($d['monto_iva']) ?></td>
                            <td class="text-right"><strong><?= format_gtq($d['monto_total']) ?></strong></td>
                            <td class="text-center"><span class="badge badge-success">✓ Certificado</span></td>
                            <td class="text-center">
                                <a href="?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm">Ver DTE</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();

$tab = $_GET['tab'] ?? 'compras';
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

// Handle NEW PURCHASE INVOICE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_compra'])) {
    $fecha_factura = $_POST['fecha_factura'];
    $tipo_documento = $_POST['tipo_documento'];
    $serie = trim($_POST['serie']);
    $numero = trim($_POST['numero']);
    $id_proveedor = (int)$_POST['id_proveedor'];
    $monto_exento = (float)($_POST['monto_exento'] ?? 0);
    $total = (float)$_POST['total'];

    // Get Vendor Details
    $stmtProv = $pdo->prepare("SELECT * FROM contabilidad_clientes_proveedores WHERE id = ?");
    $stmtProv->execute([$id_proveedor]);
    $prov = $stmtProv->fetch();

    $calc = calcular_iva($total - $monto_exento);
    $neto = $calc['neto'];
    $iva = $calc['iva'];

    // Calculate ISR retention if vendor is Opcional Simplificado & Net >= 2500
    $retencionIsr = calcular_retencion_isr_proveedor($neto, $prov['regimen_isr']);
    $estadoRetencion = ($retencionIsr > 0) ? 'Retenido' : 'No Aplica';
    $constanciaNum = ($retencionIsr > 0) ? 'RET-ISR-' . rand(10000, 99999) : null;

    // Create Partida Contable for Purchase
    // Debit: Gastos/Inventario (Neto) + IVA Crédito Fiscal (IVA)
    // Credit: Banco / Proveedores por Pagar (Total - Retención) + ISR Retenido por Pagar (Retención)
    $stmtAcc = $pdo->prepare("SELECT id FROM contabilidad_cuentas WHERE id_empresa = ? AND codigo_cuenta = ?");
    $stmtAcc->execute([$empresa_id, '6.1.02.02.001']);
    $idCuentaGasto = $stmtAcc->fetchColumn() ?: 1;

    $stmtAcc->execute([$empresa_id, '1.1.02.02.001']);
    $idCuentaIvaCredito = $stmtAcc->fetchColumn() ?: 1;

    $stmtAcc->execute([$empresa_id, '1.1.01.02.001']);
    $idCuentaBanco = $stmtAcc->fetchColumn() ?: 1;

    $stmtAcc->execute([$empresa_id, '2.1.01.03.001']);
    $idCuentaIsrRet = $stmtAcc->fetchColumn() ?: 1;

    $correlativo = get_siguiente_correlativo_partida($empresa_id);
    $concepto = "Compra según Factura {$serie}-{$numero} de {$prov['nombre_razon_social']}";

    $pdo->beginTransaction();
    $stmtPart = $pdo->prepare("INSERT INTO contabilidad_partidas (id_empresa, correlativo, fecha, tipo_partida, concepto, total_debe, total_haber) VALUES (?, ?, ?, 'Compras', ?, ?, ?)");
    $stmtPart->execute([$empresa_id, $correlativo, $fecha_factura, $concepto, $total, $total]);
    $partidaId = $pdo->lastInsertId();

    $stmtDet = $pdo->prepare("INSERT INTO contabilidad_partida_detalles (id_partida, id_cuenta, concepto_linea, debe, haber, orden) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtDet->execute([$partidaId, $idCuentaGasto, 'Gasto por Compra de Servicios/Bienes', $neto, 0.00, 1]);
    $stmtDet->execute([$partidaId, $idCuentaIvaCredito, 'IVA Crédito Fiscal 12%', $iva, 0.00, 2]);

    if ($retencionIsr > 0) {
        $pagoNeto = $total - $retencionIsr;
        $stmtDet->execute([$partidaId, $idCuentaBanco, 'Pago Neto a Proveedor', 0.00, $pagoNeto, 3]);
        $stmtDet->execute([$partidaId, $idCuentaIsrRet, "Retención ISR 5%/7% (Constancia {$constanciaNum})", 0.00, $retencionIsr, 4]);
    } else {
        $stmtDet->execute([$partidaId, $idCuentaBanco, 'Pago con Banco Industrial', 0.00, $total, 3]);
    }

    // Insert Libro Compras
    $stmtCmp = $pdo->prepare("INSERT INTO contabilidad_libro_compras (id_empresa, id_partida, id_proveedor, fecha_factura, tipo_documento, serie, numero, nit_proveedor, nombre_proveedor, monto_exento, monto_neto, monto_iva, total, estado_retencion_isr, constancia_retencion_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtCmp->execute([$empresa_id, $partidaId, $id_proveedor, $fecha_factura, $tipo_documento, $serie, $numero, $prov['nit'], $prov['nombre_razon_social'], $monto_exento, $neto, $iva, $total, $estadoRetencion, $constanciaNum]);

    $pdo->commit();
    redirect_to("compras_ventas.php?tab=compras&created=1");
}

// Handle NEW SALES INVOICE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_venta'])) {
    $fecha_factura = $_POST['fecha_factura'];
    $tipo_documento = $_POST['tipo_documento'];
    $serie = trim($_POST['serie']);
    $numero = trim($_POST['numero']);
    $id_cliente = (int)$_POST['id_cliente'];
    $monto_exento = (float)($_POST['monto_exento'] ?? 0);
    $total = (float)$_POST['total'];

    // Get Customer Details
    $stmtCli = $pdo->prepare("SELECT * FROM contabilidad_clientes_proveedores WHERE id = ?");
    $stmtCli->execute([$id_cliente]);
    $cli = $stmtCli->fetch();

    $calc = calcular_iva($total - $monto_exento);
    $neto = $calc['neto'];
    $iva = $calc['iva'];

    // Generate Simulated FEL UUID
    $uuidFel = sprintf('%08X-%04X-%04X-%04X-%012X', mt_rand(), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand());

    // Create Partida Contable for Sale
    // Debit: Banco Industrial GTQ (Total)
    // Credit: Ventas Afectas 12% (Neto) + IVA Débito Fiscal (IVA)
    $stmtAcc = $pdo->prepare("SELECT id FROM contabilidad_cuentas WHERE id_empresa = ? AND codigo_cuenta = ?");
    $stmtAcc->execute([$empresa_id, '1.1.01.02.001']);
    $idCuentaBanco = $stmtAcc->fetchColumn() ?: 1;

    $stmtAcc->execute([$empresa_id, '4.1.01.02.001']);
    $idCuentaVenta = $stmtAcc->fetchColumn() ?: 1;

    $stmtAcc->execute([$empresa_id, '2.1.01.02.001']);
    $idCuentaIvaDebito = $stmtAcc->fetchColumn() ?: 1;

    $correlativo = get_siguiente_correlativo_partida($empresa_id);
    $concepto = "Venta Factura FEL {$serie}-{$numero} a {$cli['nombre_razon_social']}";

    $pdo->beginTransaction();
    $stmtPart = $pdo->prepare("INSERT INTO contabilidad_partidas (id_empresa, correlativo, fecha, tipo_partida, concepto, total_debe, total_haber) VALUES (?, ?, ?, 'Ventas', ?, ?, ?)");
    $stmtPart->execute([$empresa_id, $correlativo, $fecha_factura, $concepto, $total, $total]);
    $partidaId = $pdo->lastInsertId();

    $stmtDet = $pdo->prepare("INSERT INTO contabilidad_partida_detalles (id_partida, id_cuenta, concepto_linea, debe, haber, orden) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtDet->execute([$partidaId, $idCuentaBanco, 'Ingreso a Banco por Venta', $total, 0.00, 1]);
    $stmtDet->execute([$partidaId, $idCuentaVenta, 'Venta de Servicios Afecta IVA 12%', 0.00, $neto, 2]);
    $stmtDet->execute([$partidaId, $idCuentaIvaDebito, 'IVA Débito Fiscal 12%', 0.00, $iva, 3]);

    // Insert Libro Ventas
    $stmtVta = $pdo->prepare("INSERT INTO contabilidad_libro_ventas (id_empresa, id_partida, id_cliente, fecha_factura, tipo_documento, serie, numero, nit_cliente, nombre_cliente, monto_exento, monto_neto, monto_iva, total, uuid_fel, fecha_certificacion_fel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmtVta->execute([$empresa_id, $partidaId, $id_cliente, $fecha_factura, $tipo_documento, $serie, $numero, $cli['nit'], $cli['nombre_razon_social'], $monto_exento, $neto, $iva, $total, $uuidFel]);

    // Insert FEL DTE
    $stmtFel = $pdo->prepare("INSERT INTO contabilidad_fel_dte (id_empresa, tipo_dte, serie, numero, uuid, fecha_emision, nit_emisor, nit_receptor, monto_neto, monto_iva, monto_total, estado_fel) VALUES (?, 'FACT', ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'Certificado')");
    $stmtFel->execute([$empresa_id, $serie, $numero, $uuidFel, $empresa_actual['nit'], $cli['nit'], $neto, $iva, $total]);

    $pdo->commit();
    redirect_to("compras_ventas.php?tab=ventas&created=1");
}

// Fetch Compras
$stmtCompras = $pdo->prepare("SELECT * FROM contabilidad_libro_compras WHERE id_empresa = ? AND MONTH(fecha_factura) = ? AND YEAR(fecha_factura) = ? ORDER BY fecha_factura DESC");
$stmtCompras->execute([$empresa_id, $mes, $anio]);
$compras = $stmtCompras->fetchAll();

// Fetch Ventas
$stmtVentas = $pdo->prepare("SELECT * FROM contabilidad_libro_ventas WHERE id_empresa = ? AND MONTH(fecha_factura) = ? AND YEAR(fecha_factura) = ? ORDER BY fecha_factura DESC");
$stmtVentas->execute([$empresa_id, $mes, $anio]);
$ventas = $stmtVentas->fetchAll();

// Fetch Clients and Suppliers for dropdowns
$stmtProvList = $pdo->prepare("SELECT id, nit, nombre_razon_social, regimen_isr FROM contabilidad_clientes_proveedores WHERE id_empresa = ? AND (tipo = 'Proveedor' OR tipo = 'Ambos') ORDER BY nombre_razon_social ASC");
$stmtProvList->execute([$empresa_id]);
$proveedores = $stmtProvList->fetchAll();

$stmtCliList = $pdo->prepare("SELECT id, nit, nombre_razon_social FROM contabilidad_clientes_proveedores WHERE id_empresa = ? AND (tipo = 'Cliente' OR tipo = 'Ambos') ORDER BY nombre_razon_social ASC");
$stmtCliList->execute([$empresa_id]);
$clientes = $stmtCliList->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Libros de IVA (Compras y Ventas SAT)</h1>
        <p class="page-subtitle">Cálculo parametrizado del 12% de IVA e ISR Retenciones para Declaraguate SAT</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" onclick="openModal('modalNuevaCompra')">
            + Registrar Factura Compra
        </button>
        <button class="btn btn-success" onclick="openModal('modalNuevaVenta')">
            + Registrar Factura Venta (FEL)
        </button>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tabs">
    <a href="?tab=compras&mes=<?= $mes ?>&anio=<?= $anio ?>" class="tab-btn <?= ($tab === 'compras') ? 'active' : '' ?>">
        Libro de Compras (Crédito Fiscal)
    </a>
    <a href="?tab=ventas&mes=<?= $mes ?>&anio=<?= $anio ?>" class="tab-btn <?= ($tab === 'ventas') ? 'active' : '' ?>">
        Libro de Ventas (Débito Fiscal)
    </a>
</div>

<?php if ($tab === 'compras'): ?>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Documento</th>
                    <th>NIT Proveedor</th>
                    <th>Nombre / Razón Social</th>
                    <th class="text-right">Monto Exento</th>
                    <th class="text-right">Monto Neto</th>
                    <th class="text-right">IVA Crédito (12%)</th>
                    <th class="text-right">Total (GTQ)</th>
                    <th class="text-center">Estado Retención ISR</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($compras)): ?>
                    <tr><td colspan="9" class="text-center" style="padding:25px; color:var(--text-muted);">No hay compras registradas en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($compras as $c): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($c['fecha_factura'])) ?></td>
                            <td><strong><?= htmlspecialchars($c['tipo_documento'] . ' ' . $c['serie'] . '-' . $c['numero']) ?></strong></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($c['nit_proveedor']) ?></td>
                            <td><?= htmlspecialchars($c['nombre_proveedor']) ?></td>
                            <td class="text-right"><?= format_gtq($c['monto_exento']) ?></td>
                            <td class="text-right"><?= format_gtq($c['monto_neto']) ?></td>
                            <td class="text-right" style="color:var(--success); font-weight:700;"><?= format_gtq($c['monto_iva']) ?></td>
                            <td class="text-right"><strong><?= format_gtq($c['total']) ?></strong></td>
                            <td class="text-center">
                                <?php if ($c['estado_retencion_isr'] === 'Retenido' || $c['estado_retencion_isr'] === 'Constancia Emitida'): ?>
                                    <span class="badge badge-warning" title="<?= htmlspecialchars((string)($c['constancia_retencion_num'] ?? '')) ?>">Constancia Emitida</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No Aplica</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Documento</th>
                    <th>NIT Cliente</th>
                    <th>Nombre / Razón Social</th>
                    <th class="text-right">Monto Exento</th>
                    <th class="text-right">Monto Neto</th>
                    <th class="text-right">IVA Débito (12%)</th>
                    <th class="text-right">Total (GTQ)</th>
                    <th class="text-center">Certificación FEL</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ventas)): ?>
                    <tr><td colspan="9" class="text-center" style="padding:25px; color:var(--text-muted);">No hay ventas registradas en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($v['fecha_factura'])) ?></td>
                            <td><strong><?= htmlspecialchars($v['tipo_documento'] . ' ' . $v['serie'] . '-' . $v['numero']) ?></strong></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($v['nit_cliente']) ?></td>
                            <td><?= htmlspecialchars($v['nombre_cliente']) ?></td>
                            <td class="text-right"><?= format_gtq($v['monto_exento']) ?></td>
                            <td class="text-right"><?= format_gtq($v['monto_neto']) ?></td>
                            <td class="text-right" style="color:var(--danger); font-weight:700;"><?= format_gtq($v['monto_iva']) ?></td>
                            <td class="text-right"><strong><?= format_gtq($v['total']) ?></strong></td>
                            <td class="text-center">
                                <span class="badge badge-success" title="UUID FEL: <?= htmlspecialchars((string)($v['uuid_fel'] ?? '')) ?>">✓ FEL Certificado</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Nueva Compra -->
<div class="modal-backdrop" id="modalNuevaCompra">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Factura de Compra</h3>
            <button class="close-modal" onclick="closeModal('modalNuevaCompra')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_add_compra" value="1">
            
            <div class="form-group">
                <label class="form-label">Proveedor:</label>
                <select name="id_proveedor" class="form-control" required>
                    <option value="">-- Seleccionar Proveedor --</option>
                    <?php foreach ($proveedores as $pv): ?>
                        <option value="<?= $pv['id'] ?>"><?= htmlspecialchars($pv['nombre_razon_social'] . ' (NIT: ' . $pv['nit'] . ' - Régimen: ' . $pv['regimen_isr'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha Factura:</label>
                    <input type="date" name="fecha_factura" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Serie:</label>
                    <input type="text" name="serie" class="form-control" placeholder="ej. F99" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Número:</label>
                    <input type="text" name="numero" class="form-control" placeholder="ej. 12054" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Tipo Documento:</label>
                    <select name="tipo_documento" class="form-control" required>
                        <option value="Factura">Factura</option>
                        <option value="DTE">DTE FEL</option>
                        <option value="Nota de Crédito">Nota de Crédito</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Monto Exento (GTQ):</label>
                    <input type="number" step="0.01" name="monto_exento" class="form-control" value="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Monto Total Factura (GTQ):</label>
                    <input type="number" step="0.01" name="total" class="form-control" placeholder="ej. 5600.00" required>
                </div>
            </div>

            <p style="font-size:12px; color:var(--text-muted); background:#f8fafc; padding:10px; border-radius:6px; margin-top:10px;">
                💡 <strong>Autocalculación Fiscal:</strong> El sistema desglosará automáticamente el <strong>12% de IVA Crédito Fiscal</strong> y calculará la <strong>Retención de ISR (5%/7%)</strong> según el régimen tributario del proveedor.
            </p>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevaCompra')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Compra & Generar Partida</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nueva Venta -->
<div class="modal-backdrop" id="modalNuevaVenta">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Emitir Factura de Venta FEL</h3>
            <button class="close-modal" onclick="closeModal('modalNuevaVenta')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_add_venta" value="1">
            
            <div class="form-group">
                <label class="form-label">Cliente:</label>
                <select name="id_cliente" class="form-control" required>
                    <option value="">-- Seleccionar Cliente --</option>
                    <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nombre_razon_social'] . ' (NIT: ' . $cl['nit'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha Emisión:</label>
                    <input type="date" name="fecha_factura" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Serie FEL:</label>
                    <input type="text" name="serie" class="form-control" value="A1B2" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Número FEL:</label>
                    <input type="text" name="numero" class="form-control" value="<?= rand(100000, 999999) ?>" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Tipo DTE:</label>
                    <select name="tipo_documento" class="form-control" required>
                        <option value="Factura">Factura Cambiaria / Servicios</option>
                        <option value="Factura Pequeño Contribuyente">Factura Pequeño Contribuyente</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Monto Exento (GTQ):</label>
                    <input type="number" step="0.01" name="monto_exento" class="form-control" value="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Monto Total Venta (GTQ):</label>
                    <input type="number" step="0.01" name="total" class="form-control" placeholder="ej. 11200.00" required>
                </div>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevaVenta')">Cancelar</button>
                <button type="submit" class="btn btn-success">Certificar DTE & Registrar Venta</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

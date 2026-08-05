<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();

// Handle CREATE BANK ACCOUNT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_banco'])) {
    $nombre_banco = trim($_POST['nombre_banco']);
    $numero_cuenta = trim($_POST['numero_cuenta']);
    $moneda = $_POST['moneda'];
    $saldo_banco = (float)$_POST['saldo_banco'];

    $stmt = $pdo->prepare("INSERT INTO contabilidad_bancos_cuentas (id_empresa, nombre_banco, numero_cuenta, moneda, saldo_banco) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$empresa_id, $nombre_banco, $numero_cuenta, $moneda, $saldo_banco]);
    $newId = $pdo->lastInsertId();
    redirect_to("conciliacion.php?id_banco={$newId}&msg=banco_created");
}

// Handle UPDATE BANK ACCOUNT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_banco'])) {
    $id_cuenta_banco = (int)$_POST['id_cuenta_banco'];
    $nombre_banco = trim($_POST['nombre_banco']);
    $numero_cuenta = trim($_POST['numero_cuenta']);
    $moneda = $_POST['moneda'];
    $saldo_banco = (float)$_POST['saldo_banco'];

    $stmt = $pdo->prepare("UPDATE contabilidad_bancos_cuentas SET nombre_banco = ?, numero_cuenta = ?, moneda = ?, saldo_banco = ? WHERE id = ? AND id_empresa = ?");
    $stmt->execute([$nombre_banco, $numero_cuenta, $moneda, $saldo_banco, $id_cuenta_banco, $empresa_id]);

    redirect_to("conciliacion.php?id_banco={$id_cuenta_banco}&msg=banco_updated");
}

// Handle DELETE BANK ACCOUNT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_banco'])) {
    $id_cuenta_banco = (int)$_POST['id_cuenta_banco'];
    
    // Delete transactions first
    $stmtDelMov = $pdo->prepare("DELETE FROM contabilidad_bancos_movimientos WHERE id_cuenta_banco = ?");
    $stmtDelMov->execute([$id_cuenta_banco]);

    // Delete account
    $stmtDelAcc = $pdo->prepare("DELETE FROM contabilidad_bancos_cuentas WHERE id = ? AND id_empresa = ?");
    $stmtDelAcc->execute([$id_cuenta_banco, $empresa_id]);

    redirect_to("conciliacion.php?msg=banco_deleted");
}

// Handle CREATE BANK TRANSACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_movimiento'])) {
    $id_cuenta_banco = (int)$_POST['id_cuenta_banco'];
    $fecha = $_POST['fecha'];
    $tipo_movimiento = $_POST['tipo_movimiento'];
    $referencia = trim($_POST['referencia']);
    $concepto = trim($_POST['concepto']);
    $monto = (float)$_POST['monto'];

    $stmt = $pdo->prepare("INSERT INTO contabilidad_bancos_movimientos (id_cuenta_banco, fecha, tipo_movimiento, referencia, concepto, monto) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id_cuenta_banco, $fecha, $tipo_movimiento, $referencia, $concepto, $monto]);

    // Update bank balance
    $sign = ($tipo_movimiento === 'Deposito' || $tipo_movimiento === 'Nota_Credito') ? 1 : -1;
    $stmtUpd = $pdo->prepare("UPDATE contabilidad_bancos_cuentas SET saldo_banco = saldo_banco + ? WHERE id = ?");
    $stmtUpd->execute([$monto * $sign, $id_cuenta_banco]);

    redirect_to("conciliacion.php?id_banco={$id_cuenta_banco}&msg=mov_added");
}

// Handle TOGGLE RECONCILED
if (isset($_GET['action']) && $_GET['action'] === 'toggle_conciliado') {
    $movId = (int)$_GET['mov_id'];
    $bankId = (int)$_GET['banco_id'];
    $stmt = $pdo->prepare("UPDATE contabilidad_bancos_movimientos SET conciliado = NOT conciliado WHERE id = ?");
    $stmt->execute([$movId]);
    redirect_to("conciliacion.php?id_banco={$bankId}");
}

// Fetch Bank Accounts
$stmtBancos = $pdo->prepare("SELECT * FROM contabilidad_bancos_cuentas WHERE id_empresa = ? ORDER BY nombre_banco ASC");
$stmtBancos->execute([$empresa_id]);
$bancos = $stmtBancos->fetchAll();

$selectedBancoId = isset($_GET['id_banco']) ? (int)$_GET['id_banco'] : ($bancos[0]['id'] ?? 0);
$selectedBanco = null;
$movimientos = [];

if ($selectedBancoId > 0) {
    foreach ($bancos as $b) {
        if ($b['id'] == $selectedBancoId) {
            $selectedBanco = $b;
            break;
        }
    }
    $stmtMov = $pdo->prepare("SELECT * FROM contabilidad_bancos_movimientos WHERE id_cuenta_banco = ? ORDER BY fecha DESC, id DESC");
    $stmtMov->execute([$selectedBancoId]);
    $movimientos = $stmtMov->fetchAll();
}

// Calculate Reconciliation Math
$saldoEstadoCuenta = $selectedBanco ? (float)$selectedBanco['saldo_banco'] : 0.00;
$depositosEnTransito = 0.00;
$chequesPendientes = 0.00;

foreach ($movimientos as $m) {
    if (!$m['conciliado']) {
        if ($m['tipo_movimiento'] === 'Deposito' || $m['tipo_movimiento'] === 'Nota_Credito') {
            $depositosEnTransito += (float)$m['monto'];
        } else {
            $chequesPendientes += (float)$m['monto'];
        }
    }
}

$saldoConciliadoSegunLibros = $saldoEstadoCuenta + $depositosEnTransito - $chequesPendientes;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Conciliación Bancaria (Quetzales GTQ y Dólares USD)</h1>
        <p class="page-subtitle">Cruce de extractos bancarios con asientos contables de mayor</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-secondary" onclick="openModal('modalNuevaCuentaBanco')">
            + Agregar Cuenta Bancaria
        </button>
        <?php if ($selectedBanco): ?>
            <button class="btn btn-primary" onclick="openModal('modalNuevoMovimiento')">
                + Registrar Movimiento Bancario
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Bank Selector -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
        <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
            <label style="font-size:13px; font-weight:700;">Seleccionar Cuenta Bancaria:</label>
            <select class="form-control" style="width:320px;" onchange="location.href='conciliacion.php?id_banco=' + this.value;">
                <?php foreach ($bancos as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($b['id'] == $selectedBancoId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['nombre_banco'] . ' - ' . $b['numero_cuenta'] . ' (' . $b['moneda'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($selectedBanco): ?>
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="badge badge-info" style="font-size:13px; padding:6px 12px;">Moneda: <?= $selectedBanco['moneda'] ?></span>
                
                <button class="btn btn-secondary btn-sm" onclick='editarBanco(<?= json_encode($selectedBanco, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                    ✏️ Editar Cuenta
                </button>

                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar la cuenta bancaria <?= htmlspecialchars($selectedBanco['nombre_banco']) ?> (<?= htmlspecialchars($selectedBanco['numero_cuenta']) ?>) y todos sus movimientos bancarios asociados?');">
                    <input type="hidden" name="action_delete_banco" value="1">
                    <input type="hidden" name="id_cuenta_banco" value="<?= $selectedBanco['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--danger); border-color:#fecaca; background:#fef2f2;" title="Eliminar Cuenta Bancaria">
                        🗑️ Eliminar Cuenta
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($selectedBanco): ?>
    <!-- Summary Reconciliation KPI Cards -->
    <div class="grid grid-cols-4" style="margin-bottom:24px;">
        <div class="card">
            <div class="kpi-label">Saldo según Estado de Cuenta</div>
            <div class="kpi-value"><?= format_gtq($saldoEstadoCuenta, $selectedBanco['moneda']) ?></div>
            <span style="font-size:11px; color:var(--text-muted);">Saldo registrado en banco</span>
        </div>

        <div class="card">
            <div class="kpi-label">(+) Depósitos en Tránsito</div>
            <div class="kpi-value" style="color:var(--success);"><?= format_gtq($depositosEnTransito, $selectedBanco['moneda']) ?></div>
            <span style="font-size:11px; color:var(--text-muted);">No acreditados en banco</span>
        </div>

        <div class="card">
            <div class="kpi-label">(-) Cheques / ND Pendientes</div>
            <div class="kpi-value" style="color:var(--danger);"><?= format_gtq($chequesPendientes, $selectedBanco['moneda']) ?></div>
            <span style="font-size:11px; color:var(--text-muted);">No cobrados en banco</span>
        </div>

        <div class="card" style="background:#f0fdf4; border-color:#bbf7d0;">
            <div class="kpi-label" style="color:#166534;">SALDO CONCILIADO SEGÚN LIBROS</div>
            <div class="kpi-value" style="color:#15803d;"><?= format_gtq($saldoConciliadoSegunLibros, $selectedBanco['moneda']) ?></div>
            <span class="badge badge-success">✓ Balance de Conciliación Ok</span>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="card">
        <h3 style="font-size:15px; font-weight:700; color:var(--secondary); margin-bottom:12px;">Extracto de Movimientos Bancarios</h3>
        <div class="table-container" style="margin-top:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha Movimiento</th>
                        <th>Tipo</th>
                        <th>Referencia / # Cheque</th>
                        <th>Concepto</th>
                        <th class="text-right">Monto (<?= $selectedBanco['moneda'] ?>)</th>
                        <th class="text-center">Estado Conciliado</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movimientos)): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 30px; color: var(--text-muted);">
                                No hay movimientos bancarios registrados para esta cuenta.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars(str_replace('_', ' ', $m['tipo_movimiento'])) ?></span></td>
                                <td><strong style="font-family:monospace;"><?= htmlspecialchars($m['referencia']) ?></strong></td>
                                <td><?= htmlspecialchars($m['concepto']) ?></td>
                                <td class="text-right font-mono" style="font-weight:700; color: <?= ($m['tipo_movimiento'] === 'Deposito' || $m['tipo_movimiento'] === 'Nota_Credito') ? 'var(--success)' : 'var(--danger)' ?>">
                                    <?= ($m['tipo_movimiento'] === 'Deposito' || $m['tipo_movimiento'] === 'Nota_Credito') ? '+' : '-' ?> <?= format_gtq($m['monto'], $selectedBanco['moneda']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['conciliado']): ?>
                                        <span class="badge badge-success">✓ Conciliado</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">⏳ Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="conciliacion.php?action=toggle_conciliado&mov_id=<?= $m['id'] ?>&banco_id=<?= $selectedBanco['id'] ?>" class="btn <?= $m['conciliado'] ? 'btn-secondary' : 'btn-success' ?> btn-sm">
                                        <?= $m['conciliado'] ? 'Desmarcar' : '✓ Conciliar' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="text-align:center; padding:50px;">
        <h3 style="color:var(--text-muted);">No tiene cuentas bancarias registradas para esta empresa.</h3>
        <button class="btn btn-primary" onclick="openModal('modalNuevaCuentaBanco')" style="margin-top:15px;">
            + Registrar Primera Cuenta Bancaria
        </button>
    </div>
<?php endif; ?>

<!-- Modal Nueva Cuenta Bancaria -->
<div class="modal-backdrop" id="modalNuevaCuentaBanco">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Agregar Cuenta Bancaria</h3>
            <button class="close-modal" onclick="closeModal('modalNuevaCuentaBanco')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_banco" value="1">
            
            <div class="form-group">
                <label class="form-label">Institución Bancaria:</label>
                <input type="text" name="nombre_banco" class="form-control" placeholder="ej. Banco Industrial, S.A." required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Número de Cuenta:</label>
                    <input type="text" name="numero_cuenta" class="form-control" placeholder="ej. 001-987654-0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Moneda de Cuenta:</label>
                    <select name="moneda" class="form-control" required>
                        <option value="GTQ">GTQ - Quetzales (Q)</option>
                        <option value="USD">USD - Dólares ($)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Saldo Inicial según Estado de Cuenta:</label>
                <input type="number" step="0.01" name="saldo_banco" class="form-control" placeholder="0.00" required>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevaCuentaBanco')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cuenta Bancaria</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Cuenta Bancaria -->
<div class="modal-backdrop" id="modalEditarCuentaBanco">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Editar Cuenta Bancaria</h3>
            <button class="close-modal" onclick="closeModal('modalEditarCuentaBanco')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_update_banco" value="1">
            <input type="hidden" name="id_cuenta_banco" id="edit_id_cuenta_banco">
            
            <div class="form-group">
                <label class="form-label">Institución Bancaria:</label>
                <input type="text" name="nombre_banco" id="edit_nombre_banco" class="form-control" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Número de Cuenta:</label>
                    <input type="text" name="numero_cuenta" id="edit_numero_cuenta" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Moneda de Cuenta:</label>
                    <select name="moneda" id="edit_moneda" class="form-control" required>
                        <option value="GTQ">GTQ - Quetzales (Q)</option>
                        <option value="USD">USD - Dólares ($)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Saldo según Estado de Cuenta:</label>
                <input type="number" step="0.01" name="saldo_banco" id="edit_saldo_banco" class="form-control" required>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditarCuentaBanco')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Cuenta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nuevo Movimiento -->
<?php if ($selectedBanco): ?>
<div class="modal-backdrop" id="modalNuevoMovimiento">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Movimiento Bancario</h3>
            <button class="close-modal" onclick="closeModal('modalNuevoMovimiento')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_add_movimiento" value="1">
            <input type="hidden" name="id_cuenta_banco" value="<?= $selectedBanco['id'] ?>">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha Movimiento:</label>
                    <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo de Movimiento:</label>
                    <select name="tipo_movimiento" class="form-control" required>
                        <option value="Deposito">Depósito</option>
                        <option value="Cheque">Cheque Emitido</option>
                        <option value="Transferencia">Transferencia Electrónica</option>
                        <option value="Nota_Debito">Nota de Débito (Comisión)</option>
                        <option value="Nota_Credito">Nota de Crédito (Intereses)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Referencia / Número de Cheque:</label>
                <input type="text" name="referencia" class="form-control" placeholder="ej. CHQ-99182 o DEP-55102" required>
            </div>

            <div class="form-group">
                <label class="form-label">Concepto Explicativo:</label>
                <input type="text" name="concepto" class="form-control" placeholder="ej. Depósito por pago de cliente Distribuidora" required>
            </div>

            <div class="form-group">
                <label class="form-label">Monto (<?= $selectedBanco['moneda'] ?>):</label>
                <input type="number" step="0.01" name="monto" class="form-control" placeholder="ej. 1500.00" required>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevoMovimiento')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar Movimiento</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function editarBanco(data) {
    document.getElementById('edit_id_cuenta_banco').value = data.id;
    document.getElementById('edit_nombre_banco').value = data.nombre_banco || '';
    document.getElementById('edit_numero_cuenta').value = data.numero_cuenta || '';
    document.getElementById('edit_moneda').value = data.moneda || 'GTQ';
    document.getElementById('edit_saldo_banco').value = data.saldo_banco || '0.00';

    openModal('modalEditarCuentaBanco');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

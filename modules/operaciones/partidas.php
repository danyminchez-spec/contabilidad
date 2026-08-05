<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();

// Handle creation of new Journal Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_partida'])) {
    $fecha = $_POST['fecha'];
    $tipo_partida = $_POST['tipo_partida'];
    $id_centro_costo = !empty($_POST['id_centro_costo']) ? (int)$_POST['id_centro_costo'] : null;
    $concepto = trim($_POST['concepto']);
    
    $cuentas_ids = $_POST['cuenta_id'] ?? [];
    $conceptos_linea = $_POST['concepto_linea'] ?? [];
    $ccs_linea = $_POST['centro_costo_linea'] ?? [];
    $debes = $_POST['debe'] ?? [];
    $haberes = $_POST['haber'] ?? [];

    $total_debe = 0;
    $total_haber = 0;
    $lineas = [];

    for ($i = 0; $i < count($cuentas_ids); $i++) {
        $id_cta = (int)$cuentas_ids[$i];
        if ($id_cta <= 0) continue;

        $d = (float)($debes[$i] ?? 0);
        $h = (float)($haberes[$i] ?? 0);
        $conc = trim($conceptos_linea[$i] ?? '');
        $cc = !empty($ccs_linea[$i]) ? (int)$ccs_linea[$i] : $id_centro_costo;

        $total_debe += $d;
        $total_haber += $h;

        $lineas[] = [
            'id_cuenta' => $id_cta,
            'concepto_linea' => $conc,
            'id_centro_costo' => $cc,
            'debe' => $d,
            'haber' => $h,
            'orden' => $i + 1
        ];
    }

    $total_debe = round($total_debe, 2);
    $total_haber = round($total_haber, 2);

    // Validation: Check balance
    if (count($lineas) < 2) {
        $error = "La partida debe contener al menos 2 líneas de movimiento.";
    } elseif (abs($total_debe - $total_haber) > 0.009) {
        $error = "La partida está descuadrada. Total Debe (Q " . number_format($total_debe, 2) . ") != Total Haber (Q " . number_format($total_haber, 2) . ")";
    } else {
        $pdo->beginTransaction();
        try {
            $correlativo = get_siguiente_correlativo_partida($empresa_id);
            
            // Insert header
            $stmtHead = $pdo->prepare("INSERT INTO contabilidad_partidas (id_empresa, correlativo, fecha, tipo_partida, id_centro_costo, concepto, total_debe, total_haber) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtHead->execute([$empresa_id, $correlativo, $fecha, $tipo_partida, $id_centro_costo, $concepto, $total_debe, $total_haber]);
            $partida_id = $pdo->lastInsertId();

            // Insert details and update balances
            $stmtDet = $pdo->prepare("INSERT INTO contabilidad_partida_detalles (id_partida, id_cuenta, id_centro_costo, concepto_linea, debe, haber, orden) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtUpdBal = $pdo->prepare("UPDATE contabilidad_cuentas SET saldo_actual = saldo_actual + ? WHERE id = ?");

            foreach ($lineas as $l) {
                $stmtDet->execute([$partida_id, $l['id_cuenta'], $l['id_centro_costo'], $l['concepto_linea'], $l['debe'], $l['haber'], $l['orden']]);
                
                // Balance impact
                $netoMovimiento = $l['debe'] - $l['haber'];
                $stmtUpdBal->execute([$netoMovimiento, $l['id_cuenta']]);
            }

            $pdo->commit();
            redirect_to("partidas.php?msg=saved");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al guardar la partida: " . $e->getMessage();
        }
    }
}

// Fetch Accounts & Cost Centers
$stmtCuentas = $pdo->prepare("SELECT id, codigo_cuenta, nombre_cuenta, tipo_cuenta FROM contabilidad_cuentas WHERE id_empresa = ? AND estado = 1 ORDER BY codigo_cuenta ASC");
$stmtCuentas->execute([$empresa_id]);
$cuentasList = $stmtCuentas->fetchAll();

$stmtCc = $pdo->prepare("SELECT id, codigo, nombre FROM contabilidad_centros_costo WHERE id_empresa = ? AND estado = 1 ORDER BY codigo ASC");
$stmtCc->execute([$empresa_id]);
$ccList = $stmtCc->fetchAll();

// Fetch Partidas
$stmtPartidas = $pdo->prepare("SELECT p.*, cc.nombre as nombre_cc FROM contabilidad_partidas p LEFT JOIN contabilidad_centros_costo cc ON p.id_centro_costo = cc.id WHERE p.id_empresa = ? ORDER BY p.fecha DESC, p.correlativo DESC");
$stmtPartidas->execute([$empresa_id]);
$partidas = $stmtPartidas->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Partidas y Asientos Contables</h1>
        <p class="page-subtitle">Registro cronológico con validación de balance (Debe = Haber)</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalNuevaPartida')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva Partida de Diario
    </button>
</div>

<?php if (isset($error)): ?>
    <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:14px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Centro Costo</th>
                <th>Concepto / Glosa</th>
                <th class="text-right">Debe</th>
                <th class="text-right">Haber</th>
                <th class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($partidas)): ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding:25px; color:var(--text-muted);">No se han registrado partidas contables.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($partidas as $pt): ?>
                    <tr>
                        <td><strong>Partida #<?= $pt['correlativo'] ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($pt['fecha'])) ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($pt['tipo_partida']) ?></span></td>
                        <td><?= htmlspecialchars($pt['nombre_cc'] ?: 'General') ?></td>
                        <td><?= htmlspecialchars($pt['concepto']) ?></td>
                        <td class="text-right"><strong><?= format_gtq($pt['total_debe']) ?></strong></td>
                        <td class="text-right"><strong><?= format_gtq($pt['total_haber']) ?></strong></td>
                        <td class="text-center">
                            <button class="btn btn-secondary btn-sm" onclick="verDetallePartida(<?= $pt['id'] ?>)">
                                Ver Asiento
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Nueva Partida -->
<div class="modal-backdrop" id="modalNuevaPartida">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Nueva Partida Contable</h3>
            <button class="close-modal" onclick="closeModal('modalNuevaPartida')">&times;</button>
        </div>
        <form method="POST" action="" id="formPartida">
            <input type="hidden" name="action_create_partida" value="1">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha de Asiento:</label>
                    <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo de Partida:</label>
                    <select name="tipo_partida" class="form-control" required>
                        <option value="Diario">Diario</option>
                        <option value="Apertura">Apertura</option>
                        <option value="Ajuste">Ajuste</option>
                        <option value="Cierre">Cierre</option>
                        <option value="Ventas">Ventas</option>
                        <option value="Compras">Compras</option>
                        <option value="Nómina">Nómina</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Centro de Costo Principal:</label>
                    <select name="id_centro_costo" class="form-control">
                        <option value="">-- General --</option>
                        <?php foreach ($ccList as $cc): ?>
                            <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['codigo'] . ' - ' . $cc['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Concepto / Glosa Explicativa:</label>
                <input type="text" name="concepto" class="form-control" placeholder="ej. Pago de servicios de internet y telefonía del mes" required>
            </div>

            <!-- Dynamic Lines Table -->
            <h4 style="font-size:14px; font-weight:700; margin:15px 0 10px; color:var(--secondary);">Detalle de Movimientos (Debe / Haber)</h4>
            <table class="table" id="tablaDetallesPartida">
                <thead>
                    <tr>
                        <th style="width: 35%;">Cuenta Contable</th>
                        <th style="width: 25%;">Concepto Línea</th>
                        <th style="width: 18%;">Debe (GTQ)</th>
                        <th style="width: 18%;">Haber (GTQ)</th>
                        <th style="width: 4%;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyLineas">
                    <!-- Template rows loaded via JS -->
                </tbody>
                <tfoot>
                    <tr style="background:#f1f5f9; font-weight:700;">
                        <td colspan="2" class="text-right">TOTALES:</td>
                        <td class="text-right"><span id="lblTotalDebe" style="color:var(--primary); font-size:14px;">Q 0.00</span></td>
                        <td class="text-right"><span id="lblTotalHaber" style="color:var(--primary); font-size:14px;">Q 0.00</span></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="5">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="agregarLineaPartida()">
                                + Agregar Línea
                            </button>
                            <span id="lblDiferencia" style="float:right; font-weight:700; font-size:12px; margin-top:4px;"></span>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevaPartida')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardarPartida">Guardar Asiento Contable</button>
            </div>
        </form>
    </div>
</div>

<script>
const cuentasOptions = `<?php foreach ($cuentasList as $c): ?>
    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['codigo_cuenta'] . ' - ' . $c['nombre_cuenta']) ?></option>
<?php endforeach; ?>`;

function agregarLineaPartida(cuentaId = '', concepto = '', debe = 0, haber = 0) {
    const tbody = document.getElementById('tbodyLineas');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="cuenta_id[]" class="form-control select-cuenta" required>
                <option value="">-- Seleccionar Cuenta --</option>
                ${cuentasOptions}
            </select>
        </td>
        <td>
            <input type="text" name="concepto_linea[]" class="form-control" value="${concepto}" placeholder="Glosa opcional">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="debe[]" class="form-control inp-debe text-right" value="${debe}" oninput="calcularTotalesPartida()">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="haber[]" class="form-control inp-haber text-right" value="${haber}" oninput="calcularTotalesPartida()">
        </td>
        <td class="text-center">
            <button type="button" style="background:none; border:none; color:var(--danger); cursor:pointer; font-weight:bold; font-size:16px;" onclick="eliminarLineaPartida(this)">&times;</button>
        </td>
    `;
    tbody.appendChild(tr);

    if (cuentaId) {
        tr.querySelector('.select-cuenta').value = cuentaId;
    }
    calcularTotalesPartida();
}

function eliminarLineaPartida(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('#tbodyLineas tr').length > 2) {
        row.remove();
        calcularTotalesPartida();
    } else {
        alert("La partida debe tener al menos 2 líneas.");
    }
}

function calcularTotalesPartida() {
    let totDebe = 0;
    let totHaber = 0;

    document.querySelectorAll('.inp-debe').forEach(inp => totDebe += parseFloat(inp.value || 0));
    document.querySelectorAll('.inp-haber').forEach(inp => totHaber += parseFloat(inp.value || 0));

    document.getElementById('lblTotalDebe').innerText = 'Q ' + totDebe.toFixed(2);
    document.getElementById('lblTotalHaber').innerText = 'Q ' + totHaber.toFixed(2);

    const diff = Math.abs(totDebe - totHaber);
    const lblDiff = document.getElementById('lblDiferencia');
    const btnGuardar = document.getElementById('btnGuardarPartida');

    if (diff < 0.01 && totDebe > 0) {
        lblDiff.innerHTML = '<span style="color:var(--success);">✓ Partida Cuadrada (Debe = Haber)</span>';
        btnGuardar.disabled = false;
    } else {
        lblDiff.innerHTML = `<span style="color:var(--danger);">⚠️ Descuadre: Q ${diff.toFixed(2)}</span>`;
        btnGuardar.disabled = true;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Add 2 initial lines by default
    agregarLineaPartida();
    agregarLineaPartida();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_create_account'])) {
        $codigo_cuenta = trim($_POST['codigo_cuenta']);
        $nombre_cuenta = trim($_POST['nombre_cuenta']);
        $nivel = (int)$_POST['nivel'];
        $id_padre = !empty($_POST['id_padre']) ? (int)$_POST['id_padre'] : null;
        $tipo_cuenta = $_POST['tipo_cuenta'];
        $requiere_centro_costo = isset($_POST['requiere_centro_costo']) ? 1 : 0;
        $rubro_sat = trim($_POST['rubro_sat']);

        $stmt = $pdo->prepare("INSERT INTO contabilidad_cuentas (id_empresa, codigo_cuenta, nombre_cuenta, nivel, id_padre, tipo_cuenta, requiere_centro_costo, rubro_sat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$empresa_id, $codigo_cuenta, $nombre_cuenta, $nivel, $id_padre, $tipo_cuenta, $requiere_centro_costo, $rubro_sat]);
        redirect_to("index.php?msg=account_created");
    }

    if (isset($_POST['action_create_cost_center'])) {
        $codigo = trim($_POST['codigo']);
        $nombre = trim($_POST['nombre']);
        $tipo = $_POST['tipo'];

        $stmt = $pdo->prepare("INSERT INTO contabilidad_centros_costo (id_empresa, codigo, nombre, tipo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$empresa_id, $codigo, $nombre, $tipo]);
        redirect_to("index.php?tab=cost_centers&msg=cc_created");
    }
}

// Fetch Accounts
$stmtAccounts = $pdo->prepare("SELECT * FROM contabilidad_cuentas WHERE id_empresa = ? ORDER BY codigo_cuenta ASC");
$stmtAccounts->execute([$empresa_id]);
$cuentas = $stmtAccounts->fetchAll();

// Fetch Cost Centers
$stmtCc = $pdo->prepare("SELECT * FROM contabilidad_centros_costo WHERE id_empresa = ? ORDER BY codigo ASC");
$stmtCc->execute([$empresa_id]);
$centrosCosto = $stmtCc->fetchAll();

$activeTab = $_GET['tab'] ?? 'catalog';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Catálogo de Cuentas y Centros de Costo</h1>
        <p class="page-subtitle">Estructura jerárquica flexible (Nivel 1 al 6) con Mapeo SAT para <?= htmlspecialchars($empresa_actual['nombre_comercial']) ?></p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" onclick="openModal('modalNuevaCuenta')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva Cuenta
        </button>
        <button class="btn btn-secondary" onclick="openModal('modalNuevoCentroCosto')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/></svg>
            Nuevo Centro de Costo
        </button>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tabs">
    <a href="?tab=catalog" class="tab-btn <?= ($activeTab === 'catalog') ? 'active' : '' ?>">Catálogo Nomenclatura SAT</a>
    <a href="?tab=cost_centers" class="tab-btn <?= ($activeTab === 'cost_centers') ? 'active' : '' ?>">Centros de Costo (Áreas/Proyectos)</a>
</div>

<?php if ($activeTab === 'catalog'): ?>
    <!-- Table Catalogo -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código Cuenta</th>
                    <th>Nombre de la Cuenta</th>
                    <th>Nivel</th>
                    <th>Tipo</th>
                    <th>Rubro Fiscal SAT</th>
                    <th class="text-center">Req. CC</th>
                    <th class="text-right">Saldo Actual</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cuentas as $c): ?>
                    <?php 
                    $indent = ($c['nivel'] - 1) * 20; 
                    $isHeader = ($c['nivel'] <= 3);
                    ?>
                    <tr style="<?= $isHeader ? 'background-color: #f8fafc; font-weight:700;' : '' ?>">
                        <td>
                            <span style="font-family: monospace; font-size: 13px; font-weight: 700; color: var(--primary);">
                                <?= htmlspecialchars($c['codigo_cuenta']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="padding-left: <?= $indent ?>px;">
                                <?= $c['nivel'] > 1 ? '↳ ' : '' ?>
                                <?= htmlspecialchars($c['nombre_cuenta']) ?>
                            </div>
                        </td>
                        <td><span class="badge badge-secondary">Nivel <?= $c['nivel'] ?></span></td>
                        <td>
                            <?php
                            $badgeClass = 'badge-secondary';
                            if ($c['tipo_cuenta'] === 'Activo') $badgeClass = 'badge-info';
                            if ($c['tipo_cuenta'] === 'Pasivo') $badgeClass = 'badge-warning';
                            if ($c['tipo_cuenta'] === 'Patrimonio') $badgeClass = 'badge-success';
                            if ($c['tipo_cuenta'] === 'Ingresos') $badgeClass = 'badge-success';
                            if ($c['tipo_cuenta'] === 'Gastos' || $c['tipo_cuenta'] === 'Costos') $badgeClass = 'badge-danger';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= $c['tipo_cuenta'] ?></span>
                        </td>
                        <td>
                            <small style="font-family:monospace; color:var(--text-muted);">
                                <?= htmlspecialchars($c['rubro_sat'] ?: 'N/A') ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <?= $c['requiere_centro_costo'] ? '<span class="badge badge-warning">Sí</span>' : '<span style="color:#cbd5e1;">-</span>' ?>
                        </td>
                        <td class="text-right">
                            <strong><?= format_gtq($c['saldo_actual']) ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <!-- Table Cost Centers -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código CC</th>
                    <th>Nombre Centro de Costo</th>
                    <th>Tipo</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($centrosCosto as $cc): ?>
                    <tr>
                        <td><strong style="font-family:monospace;"><?= htmlspecialchars($cc['codigo']) ?></strong></td>
                        <td><?= htmlspecialchars($cc['nombre']) ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($cc['tipo']) ?></span></td>
                        <td class="text-center"><span class="badge badge-success">Activo</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Nueva Cuenta -->
<div class="modal-backdrop" id="modalNuevaCuenta">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Agregar Cuenta al Catálogo</h3>
            <button class="close-modal" onclick="closeModal('modalNuevaCuenta')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_account" value="1">
            
            <div class="form-group">
                <label class="form-label">Código de Cuenta (Jerárquico):</label>
                <input type="text" name="codigo_cuenta" class="form-control" placeholder="ej. 1.1.01.01.002" required>
            </div>

            <div class="form-group">
                <label class="form-label">Nombre de la Cuenta:</label>
                <input type="text" name="nombre_cuenta" class="form-control" placeholder="ej. Banco Agromercantil GTQ" required>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Nivel Jerárquico (1 a 6):</label>
                    <select name="nivel" class="form-control" required>
                        <option value="1">Nivel 1 (Clase: Activo, Pasivo...)</option>
                        <option value="2">Nivel 2 (Grupo)</option>
                        <option value="3">Nivel 3 (Rubro)</option>
                        <option value="4">Nivel 4 (Cuenta Mayor)</option>
                        <option value="5" selected>Nivel 5 (Subcuenta / Detalle)</option>
                        <option value="6">Nivel 6 (Auxiliar)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo de Cuenta:</label>
                    <select name="tipo_cuenta" class="form-control" required>
                        <option value="Activo">Activo</option>
                        <option value="Pasivo">Pasivo</option>
                        <option value="Patrimonio">Patrimonio</option>
                        <option value="Ingresos">Ingresos</option>
                        <option value="Costos">Costos</option>
                        <option value="Gastos">Gastos</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Cuenta Padre (Opcional):</label>
                <select name="id_padre" class="form-control">
                    <option value="">-- Sin Padre / Es Nivel 1 --</option>
                    <?php foreach ($cuentas as $ac): ?>
                        <option value="<?= $ac['id'] ?>"><?= htmlspecialchars($ac['codigo_cuenta'] . ' - ' . $ac['nombre_cuenta']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Mapeo Rubro Fiscal SAT (Declaración Anual):</label>
                <input type="text" name="rubro_sat" class="form-control" placeholder="ej. 10102 - Caja y Bancos">
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="requiere_centro_costo" id="chkCc" value="1">
                <label for="chkCc" style="font-size:13px; font-weight:600;">Esta cuenta exige segmentar por Centro de Costo (Gastos/Ingresos)</label>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevaCuenta')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cuenta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nuevo Centro de Costo -->
<div class="modal-backdrop" id="modalNuevoCentroCosto">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Agregar Centro de Costo</h3>
            <button class="close-modal" onclick="closeModal('modalNuevoCentroCosto')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_cost_center" value="1">
            
            <div class="form-group">
                <label class="form-label">Código Centro Costo:</label>
                <input type="text" name="codigo" class="form-control" placeholder="ej. CC-SUC-XELA" required>
            </div>

            <div class="form-group">
                <label class="form-label">Nombre Centro Costo:</label>
                <input type="text" name="nombre" class="form-control" placeholder="ej. Sucursal Quetzaltenango" required>
            </div>

            <div class="form-group">
                <label class="form-label">Tipo de Segmentación:</label>
                <select name="tipo" class="form-control" required>
                    <option value="Área">Área / Departamento</option>
                    <option value="Proyecto">Proyecto Específico</option>
                    <option value="Sucursal">Sucursal / Unidad de Negocio</option>
                </select>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevoCentroCosto')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Centro de Costo</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

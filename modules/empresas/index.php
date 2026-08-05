<?php
require_once __DIR__ . '/../../includes/header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_create_empresa'])) {
        if (!is_admin()) {
            redirect_to("index.php?error=no_permission");
        }

        $nit = trim($_POST['nit']);
        $razon_social = trim($_POST['razon_social']);
        $nombre_comercial = trim($_POST['nombre_comercial']);
        $direccion = trim($_POST['direccion']);
        $regimen_isr = $_POST['regimen_isr'];
        $es_agente_retencion = isset($_POST['es_agente_retencion']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO contabilidad_empresas (nit, razon_social, nombre_comercial, direccion, regimen_isr, es_agente_retencion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nit, $razon_social, $nombre_comercial, $direccion, $regimen_isr, $es_agente_retencion]);
        $new_id = $pdo->lastInsertId();

        // Seed chart of accounts for the new company from standard catalog
        $seedCatalog = function($empId) use ($pdo) {
            $accounts = [
                ['1', 'ACTIVO', 1, null, 'Activo', 0, '10000'],
                ['1.1', 'ACTIVO CORRIENTE', 2, '1', 'Activo', 0, '11000'],
                ['1.1.01', 'Caja y Bancos', 3, '1.1', 'Activo', 0, '11100'],
                ['1.1.01.01', 'Caja General', 4, '1.1.01', 'Activo', 0, '11101'],
                ['1.1.01.01.001', 'Caja Chica Quetzales', 5, '1.1.01.01', 'Activo', 0, '11101'],
                ['1.1.01.02', 'Banco Industrial S.A. GTQ', 4, '1.1.01', 'Activo', 0, '11102'],
                ['1.1.01.02.001', 'Cuenta Monetaria 001-987654-0 GTQ', 5, '1.1.01.02', 'Activo', 0, '11102'],
                ['1.1.02', 'Cuentas por Cobrar', 3, '1.1', 'Activo', 0, '11200'],
                ['1.1.02.01', 'Clientes Nacionales', 4, '1.1.02', 'Activo', 0, '11201'],
                ['1.1.02.01.001', 'Clientes Comerciales GT', 5, '1.1.02.01', 'Activo', 0, '11201'],
                ['1.1.02.02', 'IVA Crédito Fiscal', 4, '1.1.02', 'Activo', 0, '11202'],
                ['1.1.02.02.001', 'IVA Crédito Fiscal Compras 12%', 5, '1.1.02.02', 'Activo', 0, '11202'],
                ['2', 'PASIVO', 1, null, 'Pasivo', 0, '20000'],
                ['2.1', 'PASIVO CORRIENTE', 2, '2', 'Pasivo', 0, '21000'],
                ['2.1.01', 'Cuentas por Pagar', 3, '2.1', 'Pasivo', 0, '21100'],
                ['2.1.01.01', 'Proveedores Locales', 4, '2.1.01', 'Pasivo', 0, '21101'],
                ['2.1.01.01.001', 'Proveedores de Bienes y Servicios GT', 5, '2.1.01.01', 'Pasivo', 0, '21101'],
                ['2.1.01.02', 'IVA Débito Fiscal', 4, '2.1.01', 'Pasivo', 0, '21102'],
                ['2.1.01.02.001', 'IVA Débito Fiscal Ventas 12%', 5, '2.1.01.02', 'Pasivo', 0, '21102'],
                ['3', 'PATRIMONIO NETO', 1, null, 'Patrimonio', 0, '30000'],
                ['3.1', 'CAPITAL CONTABLE', 2, '3', 'Patrimonio', 0, '31000'],
                ['3.1.01.01.001', 'Acciones Comunes', 5, null, 'Patrimonio', 0, '31101'],
                ['4', 'INGRESOS', 1, null, 'Ingresos', 0, '40000'],
                ['4.1.01.01.001', 'Ventas Afectas IVA 12%', 5, null, 'Ingresos', 0, '41101'],
                ['5', 'COSTOS DE VENTA', 1, null, 'Costos', 0, '50000'],
                ['5.1.01.01.001', 'Costo de Adquisición de Inventario', 5, null, 'Costos', 0, '51101'],
                ['6', 'GASTOS DE OPERACIÓN', 1, null, 'Gastos', 0, '60000'],
                ['6.1.01.01.001', 'Sueldos Base Ordinarios', 5, null, 'Gastos', 1, '61101']
            ];
            $codeToId = [];
            $stmtIns = $pdo->prepare("INSERT INTO contabilidad_cuentas (id_empresa, codigo_cuenta, nombre_cuenta, nivel, id_padre, tipo_cuenta, requiere_centro_costo, rubro_sat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($accounts as $acc) {
                $parentId = $acc[3] ? ($codeToId[$acc[3]] ?? null) : null;
                $stmtIns->execute([$empId, $acc[0], $acc[1], $acc[2], $parentId, $acc[4], $acc[5], $acc[6]]);
                $codeToId[$acc[0]] = $pdo->lastInsertId();
            }
            $stmtCc = $pdo->prepare("INSERT INTO contabilidad_centros_costo (id_empresa, codigo, nombre, tipo) VALUES (?, 'CC-GENERAL', 'Administración General', 'Área')");
            $stmtCc->execute([$empId]);
        };
        $seedCatalog($new_id);

        set_active_empresa_id($new_id);
        redirect_to("index.php?created=1");
    }

    if (isset($_POST['action_update_empresa'])) {
        if (!is_admin()) {
            redirect_to("index.php?error=no_permission");
        }

        $id = (int)$_POST['id_empresa'];
        $nit = trim($_POST['nit']);
        $razon_social = trim($_POST['razon_social']);
        $nombre_comercial = trim($_POST['nombre_comercial']);
        $direccion = trim($_POST['direccion']);
        $regimen_isr = $_POST['regimen_isr'];
        $es_agente_retencion = isset($_POST['es_agente_retencion']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE contabilidad_empresas SET nit = ?, razon_social = ?, nombre_comercial = ?, direccion = ?, regimen_isr = ?, es_agente_retencion = ? WHERE id = ?");
        $stmt->execute([$nit, $razon_social, $nombre_comercial, $direccion, $regimen_isr, $es_agente_retencion, $id]);

        redirect_to("index.php?updated=1");
    }
}

$empresas = get_todas_empresas();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Empresas</h1>
        <p class="page-subtitle">Consulte y opere las empresas asignadas a su perfil</p>
    </div>
    <?php if (is_admin()): ?>
        <button class="btn btn-primary" onclick="openModal('modalNuevaEmpresa')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Registrar Nueva Empresa
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['error']) && $_GET['error'] === 'no_permission'): ?>
    <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ⚠️ Únicamente el usuario Administrador está autorizado para crear o modificar empresas.
    </div>
<?php endif; ?>

<div class="grid grid-cols-3">
    <?php foreach ($empresas as $emp): ?>
        <div class="card" style="border-top: 4px solid <?= ($emp['id'] == $empresa_actual['id']) ? 'var(--primary)' : 'var(--border)' ?>;">
            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:12px;">
                <div>
                    <h3 style="font-size:16px; font-weight:700; color:var(--secondary);"><?= htmlspecialchars((string)($emp['nombre_comercial'] ?? '')) ?></h3>
                    <span style="font-size:12px; color:var(--text-muted); font-weight:600;">NIT: <?= htmlspecialchars((string)($emp['nit'] ?? '')) ?></span>
                </div>
                <?php if ($emp['id'] == $empresa_actual['id']): ?>
                    <span class="badge badge-success">Empresa Activa</span>
                <?php endif; ?>
            </div>

            <div style="font-size:13px; color:var(--text-main); margin-bottom:15px; line-height:1.6;">
                <strong>Razón Social:</strong> <?= htmlspecialchars((string)($emp['razon_social'] ?? '')) ?><br>
                <strong>Régimen ISR:</strong> <span class="badge badge-info"><?= htmlspecialchars((string)($emp['regimen_isr'] ?? '')) ?></span><br>
                <strong>Agente de Retención:</strong> <?= $emp['es_agente_retencion'] ? 'Sí (SAT)' : 'No' ?><br>
                <strong>Dirección:</strong> <?= htmlspecialchars((string)($emp['direccion'] ?: 'Ciudad de Guatemala')) ?>
            </div>

            <div style="display:flex; gap:8px; margin-top:15px; border-top:1px solid var(--border); padding-top:12px;">
                <?php if (is_admin()): ?>
                    <button class="btn btn-secondary btn-sm" onclick='editarEmpresa(<?= json_encode($emp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="flex:1; justify-content:center;">
                        ✏️ Editar
                    </button>
                <?php endif; ?>
                <?php if ($emp['id'] != $empresa_actual['id']): ?>
                    <form method="POST" action="" style="flex:1;">
                        <input type="hidden" name="action_switch_empresa" value="1">
                        <input type="hidden" name="switch_empresa_id" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">
                            Cambiar
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-primary btn-sm" style="flex:1; justify-content:center;" disabled>
                        En Uso
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (is_admin()): ?>
<!-- Modal Nueva Empresa (Exclusivo Administrador) -->
<div class="modal-backdrop" id="modalNuevaEmpresa">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Nueva Empresa en Guatemala</h3>
            <button class="close-modal" onclick="closeModal('modalNuevaEmpresa')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_empresa" value="1">
            
            <div class="form-group">
                <label class="form-label">NIT (sin guión o con guión):</label>
                <input type="text" name="nit" class="form-control" placeholder="ej. 1234567-8" required>
            </div>

            <div class="form-group">
                <label class="form-label">Razón Social Legal:</label>
                <input type="text" name="razon_social" class="form-control" placeholder="ej. Inversiones El Quetzal, Sociedad Anónima" required>
            </div>

            <div class="form-group">
                <label class="form-label">Nombre Comercial:</label>
                <input type="text" name="nombre_comercial" class="form-control" placeholder="ej. El Quetzal GT" required>
            </div>

            <div class="form-group">
                <label class="form-label">Dirección Fiscal:</label>
                <input type="text" name="direccion" class="form-control" placeholder="ej. 7a Avenida 12-23 Zona 9, Guatemala">
            </div>

            <div class="form-group">
                <label class="form-label">Régimen ISR (Impuesto Sobre la Renta):</label>
                <select name="regimen_isr" class="form-control" required>
                    <option value="Opcional Simplificado">Régimen Opcional Simplificado sobre Ingresos (5% / 7%)</option>
                    <option value="Sobre Utilidades">Régimen Sobre Utilidades de Actividades Lucrativas (25%)</option>
                </select>
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="es_agente_retencion" id="chkAgente" value="1">
                <label for="chkAgente" style="font-size:13px; font-weight:600;">Es Agente de Retención de ISR / IVA (SAT)</label>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevaEmpresa')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Empresa & Cargar Catálogo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Empresa (Exclusivo Administrador) -->
<div class="modal-backdrop" id="modalEditarEmpresa">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Editar Datos de la Empresa</h3>
            <button class="close-modal" onclick="closeModal('modalEditarEmpresa')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_update_empresa" value="1">
            <input type="hidden" name="id_empresa" id="edit_id_empresa">
            
            <div class="form-group">
                <label class="form-label">NIT:</label>
                <input type="text" name="nit" id="edit_emp_nit" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Razón Social Legal:</label>
                <input type="text" name="razon_social" id="edit_emp_razon_social" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Nombre Comercial:</label>
                <input type="text" name="nombre_comercial" id="edit_emp_nombre_comercial" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Dirección Fiscal:</label>
                <input type="text" name="direccion" id="edit_emp_direccion" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Régimen ISR:</label>
                <select name="regimen_isr" id="edit_emp_regimen_isr" class="form-control" required>
                    <option value="Opcional Simplificado">Régimen Opcional Simplificado sobre Ingresos (5% / 7%)</option>
                    <option value="Sobre Utilidades">Régimen Sobre Utilidades de Actividades Lucrativas (25%)</option>
                </select>
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="es_agente_retencion" id="edit_emp_es_agente_retencion" value="1">
                <label for="edit_emp_es_agente_retencion" style="font-size:13px; font-weight:600;">Es Agente de Retención de ISR / IVA (SAT)</label>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditarEmpresa')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Empresa</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarEmpresa(data) {
    document.getElementById('edit_id_empresa').value = data.id;
    document.getElementById('edit_emp_nit').value = data.nit || '';
    document.getElementById('edit_emp_razon_social').value = data.razon_social || '';
    document.getElementById('edit_emp_nombre_comercial').value = data.nombre_comercial || '';
    document.getElementById('edit_emp_direccion').value = data.direccion || '';
    document.getElementById('edit_emp_regimen_isr').value = data.regimen_isr || 'Opcional Simplificado';
    document.getElementById('edit_emp_es_agente_retencion').checked = (parseInt(data.es_agente_retencion) === 1);

    openModal('modalEditarEmpresa');
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

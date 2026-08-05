<?php
require_once __DIR__ . '/../../includes/header.php';
require_admin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_create_cliente_empresa'])) {
        $codigo_cliente = trim($_POST['codigo_cliente']);
        $nombre_cliente = trim($_POST['nombre_cliente']);
        $nit = trim($_POST['nit']);
        $representante = trim($_POST['representante']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);

        $stmt = $pdo->prepare("INSERT INTO contabilidad_cliente_empresas (codigo_cliente, nombre_cliente, nit, representante, telefono, email) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$codigo_cliente, $nombre_cliente, $nit, $representante, $telefono, $email]);
        $newClientId = $pdo->lastInsertId();

        // Auto-create initial License for this Client-Company
        $licenseKey = 'CONTABILIDAD-GT-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $stmtLic = $pdo->prepare("INSERT INTO contabilidad_licencias (id_cliente_empresa, clave_licencia, tipo_licencia, max_empresas, fecha_inicio, fecha_expiracion, estado) VALUES (?, ?, 'Anual', 10, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 1 YEAR), 'Activa')");
        $stmtLic->execute([$newClientId, $licenseKey]);

        redirect_to("cliente_empresas.php?created=1");
    }

    if (isset($_POST['action_update_cliente_empresa'])) {
        $id = (int)$_POST['id_cliente_empresa'];
        $codigo_cliente = trim($_POST['codigo_cliente']);
        $nombre_cliente = trim($_POST['nombre_cliente']);
        $nit = trim($_POST['nit']);
        $representante = trim($_POST['representante']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $estado = (int)$_POST['estado'];

        $stmt = $pdo->prepare("UPDATE contabilidad_cliente_empresas SET codigo_cliente = ?, nombre_cliente = ?, nit = ?, representante = ?, telefono = ?, email = ?, estado = ? WHERE id = ?");
        $stmt->execute([$codigo_cliente, $nombre_cliente, $nit, $representante, $telefono, $email, $estado, $id]);

        redirect_to("cliente_empresas.php?updated=1");
    }
}

// Fetch Client Companies
$clientes = $pdo->query("SELECT * FROM contabilidad_cliente_empresas ORDER BY id ASC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Cliente-Empresa Corporativos</h1>
        <p class="page-subtitle">Registro de clientes corporativos propietarios de empresas contables y licencias</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalNuevoClienteEmpresa')">
        + Registrar Nuevo Cliente-Empresa
    </button>
</div>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Código Cliente</th>
                <th>Nombre del Cliente Corporativo</th>
                <th>NIT</th>
                <th>Representante Legal</th>
                <th>Teléfono</th>
                <th>Correo Electrónico</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cl): ?>
                <tr>
                    <td><strong style="font-family:monospace; color:var(--primary);"><?= htmlspecialchars((string)($cl['codigo_cliente'] ?? '')) ?></strong></td>
                    <td><strong><?= htmlspecialchars((string)($cl['nombre_cliente'] ?? '')) ?></strong></td>
                    <td style="font-family:monospace;"><?= htmlspecialchars((string)($cl['nit'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($cl['representante'] ?: 'N/A')) ?></td>
                    <td><?= htmlspecialchars((string)($cl['telefono'] ?: 'N/A')) ?></td>
                    <td><?= htmlspecialchars((string)($cl['email'] ?: 'N/A')) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $cl['estado'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $cl['estado'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-secondary btn-sm" onclick='editarClienteEmpresa(<?= json_encode($cl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                            ✏️ Editar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Nuevo Cliente Empresa -->
<div class="modal-backdrop" id="modalNuevoClienteEmpresa">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Cliente-Empresa</h3>
            <button class="close-modal" onclick="closeModal('modalNuevoClienteEmpresa')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_cliente_empresa" value="1">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Código Cliente:</label>
                    <input type="text" name="codigo_cliente" class="form-control" value="CLI-00<?= count($clientes)+1 ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">NIT Corporativo:</label>
                    <input type="text" name="nit" class="form-control" placeholder="ej. 1234567-8" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nombre o Razón Social del Cliente:</label>
                <input type="text" name="nombre_cliente" class="form-control" placeholder="ej. Grupo Empresarial de Guatemala, S.A." required>
            </div>

            <div class="form-group">
                <label class="form-label">Representante Legal:</label>
                <input type="text" name="representante" class="form-control" placeholder="ej. Lic. Fernando Morales">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Teléfono de Contacto:</label>
                    <input type="text" name="telefono" class="form-control" placeholder="ej. +502 2345-6789">
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Electrónico:</label>
                    <input type="email" name="email" class="form-control" placeholder="ej. contacto@grupogt.com">
                </div>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevoClienteEmpresa')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cliente-Empresa & Emitir Licencia</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Cliente Empresa -->
<div class="modal-backdrop" id="modalEditarClienteEmpresa">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Editar Cliente-Empresa</h3>
            <button class="close-modal" onclick="closeModal('modalEditarClienteEmpresa')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_update_cliente_empresa" value="1">
            <input type="hidden" name="id_cliente_empresa" id="edit_id_cliente_empresa">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Código Cliente:</label>
                    <input type="text" name="codigo_cliente" id="edit_codigo_cliente" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">NIT Corporativo:</label>
                    <input type="text" name="nit" id="edit_nit" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nombre o Razón Social del Cliente:</label>
                <input type="text" name="nombre_cliente" id="edit_nombre_cliente" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Representante Legal:</label>
                <input type="text" name="representante" id="edit_representante" class="form-control">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Teléfono de Contacto:</label>
                    <input type="text" name="telefono" id="edit_telefono" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Electrónico:</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Estado:</label>
                <select name="estado" id="edit_estado" class="form-control" required>
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditarClienteEmpresa')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarClienteEmpresa(data) {
    document.getElementById('edit_id_cliente_empresa').value = data.id;
    document.getElementById('edit_codigo_cliente').value = data.codigo_cliente || '';
    document.getElementById('edit_nit').value = data.nit || '';
    document.getElementById('edit_nombre_cliente').value = data.nombre_cliente || '';
    document.getElementById('edit_representante').value = data.representante || '';
    document.getElementById('edit_telefono').value = data.telefono || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_estado').value = data.estado;
    
    openModal('modalEditarClienteEmpresa');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

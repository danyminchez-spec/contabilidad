<?php
require_once __DIR__ . '/../../includes/header.php';
require_admin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_create_rol'])) {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        
        $stmt = $pdo->prepare("INSERT INTO contabilidad_roles (nombre, descripcion) VALUES (?, ?)");
        $stmt->execute([$nombre, $descripcion]);
        $newRolId = $pdo->lastInsertId();

        // Seed default module permissions
        $modulos = ['catalogo', 'partidas', 'libros', 'estados_financieros', 'fiscal', 'fel', 'declaraguate', 'nomina', 'bancos', 'admin'];
        $stmtP = $pdo->prepare("INSERT INTO contabilidad_permisos (id_rol, modulo, puede_ver, puede_crear, puede_editar, puede_eliminar) VALUES (?, ?, 1, 1, 1, 0)");
        foreach ($modulos as $mod) {
            $stmtP->execute([$newRolId, $mod]);
        }

        redirect_to("permisos.php?created=1");
    }

    if (isset($_POST['action_update_permisos'])) {
        $id_rol = (int)$_POST['id_rol'];
        $modulos_perm = $_POST['permisos'] ?? [];

        // Reset permissions for role
        $pdo->prepare("DELETE FROM contabilidad_permisos WHERE id_rol = ?")->execute([$id_rol]);

        $stmtIns = $pdo->prepare("INSERT INTO contabilidad_permisos (id_rol, modulo, puede_ver, puede_crear, puede_editar, puede_eliminar) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($modulos_perm as $mod => $actions) {
            $ver = isset($actions['ver']) ? 1 : 0;
            $crear = isset($actions['crear']) ? 1 : 0;
            $editar = isset($actions['editar']) ? 1 : 0;
            $eliminar = isset($actions['eliminar']) ? 1 : 0;
            $stmtIns->execute([$id_rol, $mod, $ver, $crear, $editar, $eliminar]);
        }

        redirect_to("permisos.php?rol_id={$id_rol}&saved=1");
    }
}

// Fetch Roles
$roles = $pdo->query("SELECT * FROM contabilidad_roles ORDER BY id ASC")->fetchAll();
$selectedRolId = isset($_GET['rol_id']) ? (int)$_GET['rol_id'] : ($roles[0]['id'] ?? 1);

// Fetch permissions for selected role
$stmtPerms = $pdo->prepare("SELECT * FROM contabilidad_permisos WHERE id_rol = ?");
$stmtPerms->execute([$selectedRolId]);
$permisosRows = $stmtPerms->fetchAll();

$permMap = [];
foreach ($permisosRows as $p) {
    $permMap[$p['modulo']] = $p;
}

$allModulos = [
    'catalogo' => 'Catálogo de Cuentas & CC',
    'partidas' => 'Partidas y Asientos Contables',
    'libros' => 'Libros Obligatorios de Comercio',
    'estados_financieros' => 'Estados Financieros',
    'fiscal' => 'IVA, Compras y Ventas SAT',
    'fel' => 'Factura Electrónica FEL',
    'declaraguate' => 'Archivos Declaraguate',
    'nomina' => 'Planilla & Nómina IGSS',
    'bancos' => 'Conciliación Bancaria',
    'admin' => 'Administración General (Usuarios/Licencias)'
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Permisos y Roles de Usuario</h1>
        <p class="page-subtitle">Matriz de control de acceso granular por perfil institucional</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalNuevoRol')">
        + Crear Nuevo Rol
    </button>
</div>

<div class="grid grid-cols-3" style="grid-template-columns: 1fr 3fr; gap:20px;">
    <!-- Roles List -->
    <div class="card">
        <h3 style="font-size:15px; font-weight:700; color:var(--secondary); margin-bottom:12px;">Roles de Sistema</h3>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($roles as $r): ?>
                <a href="?rol_id=<?= $r['id'] ?>" class="btn <?= ($r['id'] == $selectedRolId) ? 'btn-primary' : 'btn-secondary' ?>" style="justify-content:space-between; text-align:left;">
                    <span><?= htmlspecialchars($r['nombre']) ?></span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Permissions Matrix Form -->
    <div class="card">
        <form method="POST" action="">
            <input type="hidden" name="action_update_permisos" value="1">
            <input type="hidden" name="id_rol" value="<?= $selectedRolId ?>">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:12px;">
                <h3 style="font-size:16px; font-weight:700; color:var(--secondary);">
                    Matriz de Permisos para Rol: <span style="color:var(--primary);"><?= htmlspecialchars(array_column($roles, 'nombre', 'id')[$selectedRolId] ?? '') ?></span>
                </h3>
            </div>

            <div class="table-container" style="margin-top:0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Módulo del Sistema</th>
                            <th class="text-center">Ver / Acceder</th>
                            <th class="text-center">Crear / Registrar</th>
                            <th class="text-center">Editar / Modificar</th>
                            <th class="text-center">Eliminar / Anular</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allModulos as $modKey => $modLabel): ?>
                            <?php 
                            $pm = $permMap[$modKey] ?? ['puede_ver'=>1, 'puede_crear'=>1, 'puede_editar'=>1, 'puede_eliminar'=>0];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($modLabel) ?></strong></td>
                                <td class="text-center">
                                    <input type="checkbox" name="permisos[<?= $modKey ?>][ver]" value="1" <?= $pm['puede_ver'] ? 'checked' : '' ?>>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="permisos[<?= $modKey ?>][crear]" value="1" <?= $pm['puede_crear'] ? 'checked' : '' ?>>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="permisos[<?= $modKey ?>][editar]" value="1" <?= $pm['puede_editar'] ? 'checked' : '' ?>>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="permisos[<?= $modKey ?>][eliminar]" value="1" <?= $pm['puede_eliminar'] ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:end; margin-top:15px;">
                <button type="submit" class="btn btn-success">Guardar Matriz de Permisos</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nuevo Rol -->
<div class="modal-backdrop" id="modalNuevoRol">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Crear Nuevo Rol de Sistema</h3>
            <button class="close-modal" onclick="closeModal('modalNuevoRol')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_rol" value="1">
            
            <div class="form-group">
                <label class="form-label">Nombre del Rol:</label>
                <input type="text" name="nombre" class="form-control" placeholder="ej. Subcontador Fiscal" required>
            </div>

            <div class="form-group">
                <label class="form-label">Descripción de Responsabilidades:</label>
                <input type="text" name="descripcion" class="form-control" placeholder="ej. Encargado de compras, ventas y conciliación bancaria">
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevoRol')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Rol & Asignar Permisos</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

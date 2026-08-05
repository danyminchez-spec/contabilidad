<?php
require_once __DIR__ . '/../../includes/header.php';
require_admin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_usuario_empresas'])) {
    $id_usuario = (int)$_POST['id_usuario'];
    $empresas_asignadas = $_POST['empresas'] ?? [];

    // Delete existing access matrix
    $pdo->prepare("DELETE FROM contabilidad_usuario_empresas WHERE id_usuario = ?")->execute([$id_usuario]);

    $stmtIns = $pdo->prepare("INSERT INTO contabilidad_usuario_empresas (id_usuario, id_empresa, estado) VALUES (?, ?, 1)");
    foreach ($empresas_asignadas as $empId) {
        $stmtIns->execute([$id_usuario, (int)$empId]);
    }

    redirect_to("permisos_empresa.php?user_id={$id_usuario}&saved=1");
}

// Fetch Users
$usuarios = $pdo->query("SELECT u.*, r.nombre as nombre_rol FROM contabilidad_usuarios u LEFT JOIN contabilidad_roles r ON u.id_rol = r.id WHERE u.estado = 1 ORDER BY u.nombre_completo ASC")->fetchAll();
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($usuarios[0]['id'] ?? 1);

// Fetch all active companies
$empresas = $pdo->query("SELECT * FROM contabilidad_empresas WHERE estado = 1 ORDER BY id ASC")->fetchAll();

// Fetch company permission mappings for selected user
$stmtUe = $pdo->prepare("SELECT id_empresa FROM contabilidad_usuario_empresas WHERE id_usuario = ? AND estado = 1");
$stmtUe->execute([$selectedUserId]);
$userEmpresas = $stmtUe->fetchAll(PDO::FETCH_COLUMN);

$usuarioActual = null;
foreach ($usuarios as $u) {
    if ($u['id'] == $selectedUserId) {
        $usuarioActual = $u;
        break;
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Permisos de Empresa por Usuario (Matriz Multi-tenant)</h1>
        <p class="page-subtitle">Asigne rápidamente qué empresas puede visualizar y operar cada usuario en el sistema contable</p>
    </div>
</div>

<?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ✓ Asignación de empresas guardada exitosamente.
    </div>
<?php endif; ?>

<div class="grid grid-cols-3" style="grid-template-columns: 1fr 3fr; gap:20px;">
    <!-- Users List -->
    <div class="card">
        <h3 style="font-size:15px; font-weight:700; color:var(--secondary); margin-bottom:12px;">Seleccionar Usuario</h3>
        <input type="text" id="buscarUsuario" class="form-control" placeholder="🔍 Buscar usuario..." style="margin-bottom:12px; font-size:13px;" onkeyup="filtrarUsuarios()">
        
        <div id="listaUsuarios" style="display:flex; flex-direction:column; gap:8px; max-height:550px; overflow-y:auto; padding-right:4px;">
            <?php foreach ($usuarios as $u): ?>
                <a href="?user_id=<?= $u['id'] ?>" class="btn item-usuario <?= ($u['id'] == $selectedUserId) ? 'btn-primary' : 'btn-secondary' ?>" style="justify-content:space-between; text-align:left; font-size:12px; padding:10px 12px;">
                    <div>
                        <strong><?= htmlspecialchars($u['nombre_completo']) ?></strong><br>
                        <small style="opacity:0.85;"><?= htmlspecialchars($u['usuario']) ?> (<?= htmlspecialchars($u['nombre_rol']) ?>)</small>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Company Access Matrix Form -->
    <div class="card">
        <form method="POST" action="">
            <input type="hidden" name="action_update_usuario_empresas" value="1">
            <input type="hidden" name="id_usuario" value="<?= $selectedUserId ?>">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:12px;">
                <div>
                    <h3 style="font-size:16px; font-weight:700; color:var(--secondary); margin-bottom:4px;">
                        Empresas Asignadas a: <span style="color:var(--primary);"><?= htmlspecialchars($usuarioActual['nombre_completo'] ?? '') ?></span>
                    </h3>
                    <span style="font-size:12px; color:var(--text-muted); font-weight:600;">
                        Seleccionadas: <strong id="contadorSeleccionadas" style="color:var(--primary);"><?= count($userEmpresas) ?></strong> de <?= count($empresas) ?> empresas
                    </span>
                </div>
            </div>

            <!-- Quick Action Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; background:#f8fafc; padding:10px 14px; border-radius:8px; border:1px solid var(--border); flex-wrap:wrap; gap:10px;">
                <input type="text" id="buscarEmpresa" class="form-control" placeholder="🔍 Buscar por NIT, Razón Social o Nombre Comercial..." style="font-size:13px; max-width:380px;" onkeyup="filtrarEmpresas()">
                
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTodasEmpresas(true)">
                        ☑️ Marcar Todas
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTodasEmpresas(false)">
                        ◻️ Desmarcar Todas
                    </button>
                </div>
            </div>

            <div class="table-container" style="margin-top:0; max-height:480px; overflow-y:auto;">
                <table class="table" id="tablaEmpresas">
                    <thead>
                        <tr>
                            <th style="width: 80px;" class="text-center">Acceso</th>
                            <th>NIT Empresa</th>
                            <th>Razón Social</th>
                            <th>Nombre Comercial</th>
                            <th>Régimen ISR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empresas as $emp): ?>
                            <?php $tieneAcceso = in_array($emp['id'], $userEmpresas); ?>
                            <tr class="fila-empresa" style="<?= $tieneAcceso ? 'background-color:#f0fdf4;' : '' ?>">
                                <td class="text-center">
                                    <input type="checkbox" name="empresas[]" value="<?= $emp['id'] ?>" class="chk-empresa" <?= $tieneAcceso ? 'checked' : '' ?> onchange="actualizarEstadoFila(this)" style="width:18px; height:18px; cursor:pointer;">
                                </td>
                                <td style="font-family:monospace; font-weight:700;"><?= htmlspecialchars($emp['nit']) ?></td>
                                <td><strong class="col-razon"><?= htmlspecialchars($emp['razon_social']) ?></strong></td>
                                <td class="col-nombre"><?= htmlspecialchars($emp['nombre_comercial']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($emp['regimen_isr']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:end; margin-top:20px; padding-top:15px; border-top:1px solid var(--border);">
                <button type="submit" class="btn btn-success">Guardar Asignación de Empresas</button>
            </div>
        </form>
    </div>
</div>

<script>
function filtrarUsuarios() {
    let filter = document.getElementById('buscarUsuario').value.toLowerCase();
    let items = document.querySelectorAll('#listaUsuarios .item-usuario');
    items.forEach(item => {
        let text = item.textContent.toLowerCase();
        item.style.display = text.includes(filter) ? '' : 'none';
    });
}

function filtrarEmpresas() {
    let filter = document.getElementById('buscarEmpresa').value.toLowerCase();
    let rows = document.querySelectorAll('#tablaEmpresas tbody tr.fila-empresa');
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

function toggleTodasEmpresas(marcar) {
    let chks = document.querySelectorAll('#tablaEmpresas .chk-empresa');
    chks.forEach(chk => {
        // Only toggle visible rows if filter is applied, or all rows
        let row = chk.closest('tr');
        if (row.style.display !== 'none') {
            chk.checked = marcar;
            actualizarEstadoFila(chk);
        }
    });
    actualizarContador();
}

function actualizarEstadoFila(chk) {
    let row = chk.closest('tr');
    if (chk.checked) {
        row.style.backgroundColor = '#f0fdf4';
    } else {
        row.style.backgroundColor = '';
    }
    actualizarContador();
}

function actualizarContador() {
    let count = document.querySelectorAll('#tablaEmpresas .chk-empresa:checked').length;
    document.getElementById('contadorSeleccionadas').textContent = count;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

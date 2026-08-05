<?php
require_once __DIR__ . '/../../includes/header.php';
require_admin();

/**
 * Generate a secure password complying with security standards:
 * Uppercase, lowercase, numbers, special characters.
 */
function generar_password_segura($longitud = 10) {
    $uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lowers = 'abcdefghijkmnopqrstuvwxyz';
    $numbers = '23456789';
    $symbols = '@#$!%*';
    $all = $uppers . $lowers . $numbers . $symbols;

    $pwd = '';
    $pwd .= $uppers[rand(0, strlen($uppers) - 1)];
    $pwd .= $lowers[rand(0, strlen($lowers) - 1)];
    $pwd .= $numbers[rand(0, strlen($numbers) - 1)];
    $pwd .= $symbols[rand(0, strlen($symbols) - 1)];

    for ($i = 4; $i < $longitud; $i++) {
        $pwd .= $all[rand(0, strlen($all) - 1)];
    }

    return str_shuffle($pwd);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_create_user'])) {
        $nombre_completo = trim($_POST['nombre_completo']);
        $usuario = trim($_POST['usuario']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $id_rol = (int)$_POST['id_rol'];
        $id_cliente_empresa = !empty($_POST['id_cliente_empresa']) ? (int)$_POST['id_cliente_empresa'] : null;

        $passHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO contabilidad_usuarios (nombre_completo, usuario, email, password, id_rol, id_cliente_empresa, estado) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$nombre_completo, $usuario, $email, $passHash, $id_rol, $id_cliente_empresa]);
            $newUserId = $pdo->lastInsertId();

            // Map user to all existing companies by default
            $empresas = $pdo->query("SELECT id FROM contabilidad_empresas")->fetchAll(PDO::FETCH_COLUMN);
            $stmtUe = $pdo->prepare("INSERT INTO contabilidad_usuario_empresas (id_usuario, id_empresa) VALUES (?, ?)");
            foreach ($empresas as $empId) {
                $stmtUe->execute([$newUserId, $empId]);
            }

            redirect_to("usuarios.php?created=1");
        } catch (PDOException $e) {
            $error = "Error al crear el usuario: El nombre de usuario o correo ya existe.";
        }
    }

    if (isset($_POST['action_update_user'])) {
        $id = (int)$_POST['id_usuario'];
        $nombre_completo = trim($_POST['nombre_completo']);
        $usuario = trim($_POST['usuario']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $id_rol = (int)$_POST['id_rol'];
        $id_cliente_empresa = !empty($_POST['id_cliente_empresa']) ? (int)$_POST['id_cliente_empresa'] : null;
        $estado = (int)$_POST['estado'];

        try {
            if (!empty($password)) {
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE contabilidad_usuarios SET nombre_completo = ?, usuario = ?, email = ?, password = ?, id_rol = ?, id_cliente_empresa = ?, estado = ? WHERE id = ?");
                $stmt->execute([$nombre_completo, $usuario, $email, $passHash, $id_rol, $id_cliente_empresa, $estado, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE contabilidad_usuarios SET nombre_completo = ?, usuario = ?, email = ?, id_rol = ?, id_cliente_empresa = ?, estado = ? WHERE id = ?");
                $stmt->execute([$nombre_completo, $usuario, $email, $id_rol, $id_cliente_empresa, $estado, $id]);
            }

            redirect_to("usuarios.php?updated=1");
        } catch (PDOException $e) {
            $error = "Error al actualizar usuario: El nombre de usuario o correo ya existe.";
        }
    }

    if (isset($_POST['action_delete_user'])) {
        $userId = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM contabilidad_usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        redirect_to("usuarios.php?msg=deleted");
    }

    if (isset($_POST['action_toggle_user_status'])) {
        $userId = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE contabilidad_usuarios SET estado = NOT estado WHERE id = ?");
        $stmt->execute([$userId]);
        redirect_to("usuarios.php?updated=1");
    }

    if (isset($_POST['action_change_password'])) {
        $userId = (int)$_POST['user_id'];
        $newPass = trim($_POST['new_password']);
        if ($userId > 0 && !empty($newPass)) {
            $passHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE contabilidad_usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$passHash, $userId]);
            redirect_to("usuarios.php?msg=pass_changed");
        }
    }

    if (isset($_POST['action_enviar_correo_usuario'])) {
        $userId = (int)$_POST['user_id'];
        $email = trim($_POST['email']);
        $enlace_video = trim($_POST['enlace_video'] ?? '');
        $nueva_pass = trim($_POST['nueva_password_opt'] ?? '');

        if (empty($nueva_pass)) {
            $nueva_pass = generar_password_segura(10);
        }

        $stmtU = $pdo->prepare("SELECT u.*, r.nombre as nombre_rol FROM contabilidad_usuarios u LEFT JOIN contabilidad_roles r ON u.id_rol = r.id WHERE u.id = ?");
        $stmtU->execute([$userId]);
        $uData = $stmtU->fetch();

        if ($uData) {
            // Update user password in database with secure hash
            $passHash = password_hash($nueva_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE contabilidad_usuarios SET password = ? WHERE id = ?")->execute([$passHash, $userId]);

            $asunto = "Tus credenciales de acceso - Módulo de Contabilidad GT";
            $body = "<div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:20px; border:1px solid #e2e8f0; border-radius:10px;'>";
            $body .= "<h2 style='color:#1e3a8a; border-bottom:2px solid #3b82f6; padding-bottom:10px;'>Nuevas Credenciales de Acceso al Sistema</h2>";
            $body .= "<p>Hola <strong>" . htmlspecialchars($uData['nombre_completo']) . "</strong>,</p>";
            $body .= "<p>Se han generado y actualizado sus credenciales de acceso para ingresar al <strong>Módulo de Contabilidad GT</strong>:</p>";
            $body .= "<div style='background:#f8fafc; padding:15px 25px; border-radius:8px; line-height:1.8; border-left:4px solid #2563eb;'>";
            $body .= "<p style='margin:4px 0;'><strong>Usuario (Login):</strong> <code style='font-size:15px; font-weight:bold; color:#1e293b;'>" . htmlspecialchars($uData['usuario']) . "</code></p>";
            $body .= "<p style='margin:4px 0;'><strong>Nueva Contraseña Segura:</strong> <code style='font-size:16px; font-weight:bold; color:#2563eb; background:#eff6ff; padding:2px 8px; border-radius:4px;'>" . htmlspecialchars($nueva_pass) . "</code></p>";
            $body .= "<p style='margin:4px 0;'><strong>Rol de Sistema:</strong> " . htmlspecialchars($uData['nombre_rol']) . "</p>";
            $body .= "</div>";
            
            if (!empty($enlace_video)) {
                $body .= "<p style='margin-top:15px;'><strong>📹 Tutorial de uso del sistema:</strong> <a href='" . htmlspecialchars($enlace_video) . "' target='_blank'>" . htmlspecialchars($enlace_video) . "</a></p>";
            }

            $body .= "<p style='margin-top:20px;'><a href='http://localhost/contabilidad/login.php' style='background:#2563eb; color:#fff; padding:10px 18px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;'>Ingresar al Sistema Contable</a></p>";
            $body .= "<hr style='border:0; border-top:1px solid #e2e8f0; margin-top:20px;'>";
            $body .= "<small style='color:#64748b;'>Este correo fue enviado de forma automática desde el Módulo Contabilidad GT - Sistemas en Red</small>";
            $body .= "</div>";

            $resMail = enviar_correo_smtp($email, $uData['nombre_completo'], $asunto, $body);

            if ($resMail['exito']) {
                redirect_to("usuarios.php?msg=email_sent&email=" . urlencode($email));
            } else {
                redirect_to("usuarios.php?error_email=" . urlencode($resMail['mensaje']));
            }
        }
    }
}

// Fetch Users with assigned companies list
$sql = "
    SELECT 
        u.*, 
        r.nombre as nombre_rol, 
        c.nombre_cliente,
        GROUP_CONCAT(e.nombre_comercial SEPARATOR ', ') AS empresas_asignadas
    FROM contabilidad_usuarios u 
    LEFT JOIN contabilidad_roles r ON u.id_rol = r.id 
    LEFT JOIN contabilidad_cliente_empresas c ON u.id_cliente_empresa = c.id 
    LEFT JOIN contabilidad_usuario_empresas ue ON u.id = ue.id_usuario AND ue.estado = 1
    LEFT JOIN contabilidad_empresas e ON ue.id_empresa = e.id AND e.estado = 1
    GROUP BY u.id
    ORDER BY u.id ASC
";
$usuarios = $pdo->query($sql)->fetchAll();

// Fetch Roles & Client-Companies for dropdowns
$roles = $pdo->query("SELECT * FROM contabilidad_roles WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$clientes = $pdo->query("SELECT * FROM contabilidad_cliente_empresas WHERE estado = 1 ORDER BY nombre_cliente ASC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Usuarios del Sistema</h1>
        <p class="page-subtitle">Administre cuentas de usuario, credenciales, empresas asignadas y notificaciones por correo</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalNuevoUsuario')">
        + Registrar Nuevo Usuario
    </button>
</div>

<?php if (isset($error)): ?>
    <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'email_sent'): ?>
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ✓ Se generó una nueva contraseña segura y se envió por correo electrónico a <u><?= htmlspecialchars($_GET['email'] ?? '') ?></u>.
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'pass_changed'): ?>
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ✓ Contraseña de usuario actualizada correctamente.
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ✓ Usuario eliminado exitosamente.
    </div>
<?php endif; ?>

<?php if (isset($_GET['error_email'])): ?>
    <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ⚠️ Error al enviar correo: <?= htmlspecialchars($_GET['error_email']) ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre Completo</th>
                <th>Empresas Asignadas</th>
                <th>Usuario</th>
                <th>Correo Electrónico</th>
                <th>Rol / Perfil</th>
                <th class="text-center">Estado</th>
                <th class="text-center" style="width:160px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>#<?= $u['id'] ?></td>
                    <td><strong><?= htmlspecialchars((string)($u['nombre_completo'] ?? '')) ?></strong></td>
                    <td>
                        <span style="font-size:12px; color:var(--secondary); font-weight:600;">
                            🏢 <?= htmlspecialchars((string)($u['empresas_asignadas'] ?: 'Todas las empresas')) ?>
                        </span>
                    </td>
                    <td style="font-family:monospace; color:var(--primary); font-weight:700;"><?= htmlspecialchars((string)($u['usuario'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($u['email'] ?? '')) ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars((string)($u['nombre_rol'] ?: 'Sin Rol')) ?></span></td>
                    <td class="text-center">
                        <span class="badge <?= $u['estado'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $u['estado'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td class="text-center" style="white-space:nowrap;">
                        <div style="display:flex; gap:4px; justify-content:center;">
                            <!-- ✏️ Editar -->
                            <button class="btn btn-secondary btn-sm" style="background:#fef3c7; color:#92400e; border-color:#fde68a;" onclick='editarUsuario(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Editar Usuario">
                                ✏️
                            </button>

                            <!-- 🗑️ Eliminar -->
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar de forma permanente al usuario <?= htmlspecialchars($u['nombre_completo']) ?>?');">
                                <input type="hidden" name="action_delete_user" value="1">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="background:#fee2e2; color:#991b1b; border-color:#fecaca;" title="Eliminar Usuario">
                                    🗑️
                                </button>
                            </form>

                            <!-- 🚫 / ✓ Activar/Desactivar -->
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action_toggle_user_status" value="1">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="background:#f1f5f9; color:#475569; border-color:#cbd5e1;" title="<?= $u['estado'] ? 'Desactivar Acceso' : 'Activar Acceso' ?>">
                                    <?= $u['estado'] ? '🚫' : '✓' ?>
                                </button>
                            </form>

                            <!-- 📧 Enviar Correo -->
                            <button class="btn btn-secondary btn-sm" style="background:#e0f2fe; color:#075985; border-color:#bae6fd;" onclick='prepararCorreoUsuario(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Enviar Credenciales por Correo">
                                📧
                            </button>

                            <!-- 🔑 Cambiar Contraseña -->
                            <button class="btn btn-secondary btn-sm" style="background:#fef9c3; color:#854d0e; border-color:#fef08a;" onclick='prepararCambioPassword(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Cambiar Contraseña">
                                🔑
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Nuevo Usuario -->
<div class="modal-backdrop" id="modalNuevoUsuario">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Nuevo Usuario</h3>
            <button class="close-modal" onclick="closeModal('modalNuevoUsuario')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_user" value="1">
            
            <div class="form-group">
                <label class="form-label">Nombre Completo:</label>
                <input type="text" name="nombre_completo" class="form-control" placeholder="ej. Lic. Mario Roberto Estrada" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Nombre de Usuario (Login):</label>
                    <input type="text" name="usuario" class="form-control" placeholder="ej. mestrada" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Electrónico:</label>
                    <input type="email" name="email" class="form-control" placeholder="ej. mestrada@empresa.com" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Contraseña de Acceso:</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Rol Institucional:</label>
                    <select name="id_rol" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Cliente Corporativo:</label>
                    <select name="id_cliente_empresa" class="form-control">
                        <option value="">-- General / Administrador --</option>
                        <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nombre_cliente']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevoUsuario')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal-backdrop" id="modalEditarUsuario">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Editar Usuario</h3>
            <button class="close-modal" onclick="closeModal('modalEditarUsuario')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_update_user" value="1">
            <input type="hidden" name="id_usuario" id="edit_id_usuario">
            
            <div class="form-group">
                <label class="form-label">Nombre Completo:</label>
                <input type="text" name="nombre_completo" id="edit_nombre_completo" class="form-control" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Nombre de Usuario (Login):</label>
                    <input type="text" name="usuario" id="edit_usuario" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Electrónico:</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nueva Contraseña (dejar en blanco para no cambiar):</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Rol Institucional:</label>
                    <select name="id_rol" id="edit_id_rol" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Cliente Corporativo:</label>
                    <select name="id_cliente_empresa" id="edit_id_cliente_empresa" class="form-control">
                        <option value="">-- General / Administrador --</option>
                        <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nombre_cliente']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Estado:</label>
                <select name="estado" id="edit_estado_usuario" class="form-control" required>
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditarUsuario')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Enviar Correo de Acceso -->
<div class="modal-backdrop" id="modalEnviarCorreoUsuario">
    <div class="modal-content">
        <div class="modal-header" style="background:var(--primary); color:#fff; border-radius:12px 12px 0 0; padding:15px 20px;">
            <h3 class="modal-title" style="color:#fff;">Enviar Acceso por Correo Electrónico</h3>
            <button class="close-modal" onclick="closeModal('modalEnviarCorreoUsuario')" style="color:#fff;">&times;</button>
        </div>
        <form method="POST" action="" style="padding:20px;">
            <input type="hidden" name="action_enviar_correo_usuario" value="1">
            <input type="hidden" name="user_id" id="mail_id_usuario">

            <div class="form-group">
                <label class="form-label">Usuario:</label>
                <input type="text" id="mail_nombre_usuario" class="form-control" readonly style="background:#f1f5f9;">
            </div>

            <div class="form-group">
                <label class="form-label">Correo Electrónico Destinatario *</label>
                <input type="email" name="email" id="mail_destinatario_email" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Nueva Contraseña Segura (Generada y Enmascarada):</label>
                <div style="display:flex; gap:8px;">
                    <input type="password" name="nueva_password_opt" id="mail_nueva_password" class="form-control" style="font-family:monospace; font-weight:700; color:var(--primary); background:#eff6ff;" required readonly>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="regenerarClaveModal()" title="Generar Otra Contraseña Segura" style="white-space:nowrap;">
                        🔄 Regenerar
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Enlace del Video Tutorial (Opcional):</label>
                <input type="url" name="enlace_video" class="form-control" placeholder="https://youtube.com/...">
            </div>

            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:6px; font-size:12px; color:#166534; margin-bottom:15px;">
                🔒 Se generó una contraseña enmascarada cumpliendo con altos estándares de seguridad (mayúsculas, minúsculas, números y símbolos). La clave se actualizará de forma privada y sólo el usuario la recibirá por correo.
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEnviarCorreoUsuario')">Cancelar</button>
                <button type="submit" class="btn btn-primary">📧 Enviar Credenciales</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cambiar Contraseña Rápida -->
<div class="modal-backdrop" id="modalCambiarPassword">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Cambiar Contraseña de Usuario</h3>
            <button class="close-modal" onclick="closeModal('modalCambiarPassword')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_change_password" value="1">
            <input type="hidden" name="user_id" id="pass_id_usuario">

            <div class="form-group">
                <label class="form-label">Usuario:</label>
                <input type="text" id="pass_nombre_usuario" class="form-control" readonly style="background:#f1f5f9;">
            </div>

            <div class="form-group">
                <label class="form-label">Nueva Contraseña de Acceso *</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" name="new_password" id="input_nueva_password_directa" class="form-control" placeholder="••••••••" required style="font-family:monospace;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="generarClaveDirecta()" style="white-space:nowrap;">⚡ Generar Segura</button>
                </div>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalCambiarPassword')">Cancelar</button>
                <button type="submit" class="btn btn-primary">🔑 Guardar Nueva Contraseña</button>
            </div>
        </form>
    </div>
</div>

<script>
function generarPasswordSeguraJS(longitud = 10) {
    const uppers = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    const lowers = "abcdefghijkmnopqrstuvwxyz";
    const numbers = "23456789";
    const symbols = "@#$!%*";
    const all = uppers + lowers + numbers + symbols;

    let pwd = "";
    pwd += uppers.charAt(Math.floor(Math.random() * uppers.length));
    pwd += lowers.charAt(Math.floor(Math.random() * lowers.length));
    pwd += numbers.charAt(Math.floor(Math.random() * numbers.length));
    pwd += symbols.charAt(Math.floor(Math.random() * symbols.length));

    for (let i = 4; i < longitud; i++) {
        pwd += all.charAt(Math.floor(Math.random() * all.length));
    }

    return pwd.split('').sort(() => 0.5 - Math.random()).join('');
}

function regenerarClaveModal() {
    document.getElementById('mail_nueva_password').value = generarPasswordSeguraJS(10);
}

function generarClaveDirecta() {
    document.getElementById('input_nueva_password_directa').value = generarPasswordSeguraJS(10);
}

function editarUsuario(data) {
    document.getElementById('edit_id_usuario').value = data.id;
    document.getElementById('edit_nombre_completo').value = data.nombre_completo || '';
    document.getElementById('edit_usuario').value = data.usuario || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_id_rol').value = data.id_rol;
    document.getElementById('edit_id_cliente_empresa').value = data.id_cliente_empresa || '';
    document.getElementById('edit_estado_usuario').value = data.estado;

    openModal('modalEditarUsuario');
}

function prepararCorreoUsuario(data) {
    document.getElementById('mail_id_usuario').value = data.id;
    document.getElementById('mail_nombre_usuario').value = (data.nombre_completo || '') + ' (' + (data.usuario || '') + ')';
    document.getElementById('mail_destinatario_email').value = data.email || '';
    document.getElementById('mail_nueva_password').value = generarPasswordSeguraJS(10);

    openModal('modalEnviarCorreoUsuario');
}

function prepararCambioPassword(data) {
    document.getElementById('pass_id_usuario').value = data.id;
    document.getElementById('pass_nombre_usuario').value = (data.nombre_completo || '') + ' (' + (data.usuario || '') + ')';
    document.getElementById('input_nueva_password_directa').value = generarPasswordSeguraJS(10);

    openModal('modalCambiarPassword');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

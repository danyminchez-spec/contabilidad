<?php
require_once __DIR__ . '/../../includes/header.php';
require_admin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_create_licencia'])) {
        $id_cliente_empresa = (int)$_POST['id_cliente_empresa'];
        $tipo_licencia = $_POST['tipo_licencia'];
        $max_empresas = (int)$_POST['max_empresas'];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_expiracion = $_POST['fecha_expiracion'];

        $clave_licencia = 'CONTABILIDAD-GT-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $stmt = $pdo->prepare("INSERT INTO contabilidad_licencias (id_cliente_empresa, clave_licencia, tipo_licencia, max_empresas, fecha_inicio, fecha_expiracion, estado) VALUES (?, ?, ?, ?, ?, ?, 'Activa')");
        $stmt->execute([$id_cliente_empresa, $clave_licencia, $tipo_licencia, $max_empresas, $fecha_inicio, $fecha_expiracion]);

        redirect_to("licencias.php?created=1");
    }

    if (isset($_POST['action_update_licencia'])) {
        $id = (int)$_POST['id_licencia'];
        $tipo_licencia = $_POST['tipo_licencia'];
        $max_empresas = (int)$_POST['max_empresas'];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_expiracion = $_POST['fecha_expiracion'];
        $estado = $_POST['estado'];

        $stmt = $pdo->prepare("UPDATE contabilidad_licencias SET tipo_licencia = ?, max_empresas = ?, fecha_inicio = ?, fecha_expiracion = ?, estado = ? WHERE id = ?");
        $stmt->execute([$tipo_licencia, $max_empresas, $fecha_inicio, $fecha_expiracion, $estado, $id]);

        redirect_to("licencias.php?updated=1");
    }

    if (isset($_POST['action_registrar_pago'])) {
        $id = (int)$_POST['id_licencia'];
        $meses = (int)$_POST['meses_pagados'];

        if ($id > 0 && $meses > 0) {
            $stmtLic = $pdo->prepare("SELECT fecha_expiracion FROM contabilidad_licencias WHERE id = ?");
            $stmtLic->execute([$id]);
            $currentExp = $stmtLic->fetchColumn();

            $hoy = date('Y-m-d');
            $base_date = ($currentExp && $currentExp >= $hoy) ? $currentExp : $hoy;
            
            // Add specified months
            $fecha_fin = date('Y-m-t', strtotime("+$meses months", strtotime(date('Y-m-01', strtotime($base_date)))));

            $stmtUpd = $pdo->prepare("UPDATE contabilidad_licencias SET fecha_expiracion = ?, estado = 'Activa' WHERE id = ?");
            $stmtUpd->execute([$fecha_fin, $id]);

            redirect_to("licencias.php?msg=pago_ok");
        }
    }

    if (isset($_POST['action_enviar_correo_licencia'])) {
        $id_licencia = (int)$_POST['id_licencia'];
        $email = trim($_POST['email']);
        $asunto = trim($_POST['asunto']);
        $enlace_video = trim($_POST['enlace_video'] ?? '');
        $mensaje_custom = trim($_POST['mensaje_custom'] ?? '');

        // Fetch license details
        $stmtL = $pdo->prepare("SELECT l.*, c.nombre_cliente FROM contabilidad_licencias l JOIN contabilidad_cliente_empresas c ON l.id_cliente_empresa = c.id WHERE l.id = ?");
        $stmtL->execute([$id_licencia]);
        $lic = $stmtL->fetch();

        if ($lic) {
            $body = "<div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:20px; border:1px solid #e2e8f0; border-radius:10px;'>";
            $body .= "<h2 style='color:#1e3a8a; border-bottom:2px solid #3b82f6; padding-bottom:10px;'>Aviso de Vencimiento de Licencia Contable</h2>";
            $body .= "<p>Estimado cliente <strong>" . htmlspecialchars($lic['nombre_cliente']) . "</strong>,</p>";
            $body .= "<p>Le notificamos que su licencia <strong>" . htmlspecialchars($lic['clave_licencia']) . "</strong> vencerá el <strong>" . date('d/m/Y', strtotime($lic['fecha_expiracion'])) . "</strong>.</p>";
            if (!empty($enlace_video)) {
                $body .= "<p><strong>Tutorial / Enlace de ayuda:</strong> <a href='" . htmlspecialchars($enlace_video) . "' target='_blank'>" . htmlspecialchars($enlace_video) . "</a></p>";
            }
            if (!empty($mensaje_custom)) {
                $body .= "<p><strong>Nota importante del administrador:</strong><br>" . nl2br(htmlspecialchars($mensaje_custom)) . "</p>";
            }
            $body .= "<p style='margin-top:20px; background:#f8fafc; padding:12px; border-radius:6px; font-size:13px;'>Por favor póngase en contacto con administración para realizar el pago de renovación y mantener su servicio activo sin interrupciones.</p>";
            $body .= "<hr style='border:0; border-top:1px solid #e2e8f0; margin-top:20px;'>";
            $body .= "<small style='color:#64748b;'>Enviado automáticamente desde el Módulo de Contabilidad GT - Sistemas en Red</small>";
            $body .= "</div>";

            $resMail = enviar_correo_smtp($email, $lic['nombre_cliente'], $asunto, $body);

            if ($resMail['exito']) {
                redirect_to("licencias.php?msg=email_sent&email=" . urlencode($email));
            } else {
                redirect_to("licencias.php?error_email=" . urlencode($resMail['mensaje']));
            }
        }
    }
}

// Fetch Licenses
$stmtLic = $pdo->prepare("SELECT l.*, c.nombre_cliente, c.nit, c.email, c.representante FROM contabilidad_licencias l JOIN contabilidad_cliente_empresas c ON l.id_cliente_empresa = c.id ORDER BY l.id DESC");
$stmtLic->execute();
$licencias = $stmtLic->fetchAll();

// Fetch Client Companies for dropdown
$clientes = $pdo->query("SELECT * FROM contabilidad_cliente_empresas WHERE estado = 1 ORDER BY nombre_cliente ASC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Módulo de Licenciamiento y Suscripciones</h1>
        <p class="page-subtitle">Control de llaves de licencia, envío de avisos por correo antes del vencimiento y registro de pagos</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalNuevaLicencia')">
        + Generar Nueva Licencia
    </button>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'pago_ok'): ?>
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ✓ Pago registrado correctamente. La fecha de expiración se ha ampliado con éxito.
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'email_sent'): ?>
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ✓ Correo de notificación de licencia enviado exitosamente a <u><?= htmlspecialchars($_GET['email'] ?? '') ?></u>.
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
                <th>Cliente Corporativo</th>
                <th>Clave (Serial)</th>
                <th>Tipo</th>
                <th class="text-center">Empresas</th>
                <th>Expiración Actual</th>
                <th class="text-center">Estado</th>
                <th>Registrar Pago (Meses)</th>
                <th class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($licencias as $l): ?>
                <?php 
                $vencida = (strtotime($l['fecha_expiracion']) < time() && $l['tipo_licencia'] !== 'Vitalicia');
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string)($l['nombre_cliente'] ?? '')) ?></strong><br>
                        <small style="color:var(--text-muted);"><?= htmlspecialchars((string)($l['email'] ?: 'Sin correo')) ?> | NIT: <?= htmlspecialchars((string)($l['nit'] ?? '')) ?></small>
                    </td>
                    <td><strong style="font-family:monospace; color:var(--primary); font-size:12px;"><?= htmlspecialchars((string)($l['clave_licencia'] ?? '')) ?></strong></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars((string)($l['tipo_licencia'] ?? '')) ?></span></td>
                    <td class="text-center"><strong><?= $l['max_empresas'] ?></strong></td>
                    <td>
                        <strong><?= date('d/m/Y', strtotime($l['fecha_expiracion'])) ?></strong>
                    </td>
                    <td class="text-center">
                        <?php if ($vencida): ?>
                            <span class="badge badge-danger">⚠️ Vencida</span>
                        <?php else: ?>
                            <span class="badge badge-success">✓ <?= htmlspecialchars((string)($l['estado'] ?? 'Activa')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" action="" style="display:flex; align-items:center; gap:6px;">
                            <input type="hidden" name="action_registrar_pago" value="1">
                            <input type="hidden" name="id_licencia" value="<?= $l['id'] ?>">
                            <select name="meses_pagados" class="form-control form-control-sm" style="padding:3px 6px; font-size:12px; width:110px;" required>
                                <option value="">Meses...</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?> mes<?= $i > 1 ? 'es' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 10px; font-size:12px;" title="Registrar Pago">💾 Pagar</button>
                        </form>
                    </td>
                    <td class="text-center">
                        <div style="display:flex; gap:6px; justify-content:center;">
                            <button class="btn btn-secondary btn-sm" style="color:var(--primary); border-color:#bfdbfe; background:#eff6ff;" onclick='prepararCorreoLicencia(<?= json_encode($l, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Enviar Notificación de Licencia por Correo">
                                📧 Enviar Correo
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick='editarLicencia(<?= json_encode($l, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Editar Configuración">
                                ✏️ Editar
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Enviar Correo de Licencia -->
<div class="modal-backdrop" id="modalEnviarCorreoLicencia">
    <div class="modal-content">
        <div class="modal-header" style="background:var(--primary); color:#fff; border-radius:12px 12px 0 0; padding:15px 20px;">
            <h3 class="modal-title" style="color:#fff;">Enviar Notificación de Licencia por Correo</h3>
            <button class="close-modal" onclick="closeModal('modalEnviarCorreoLicencia')" style="color:#fff;">&times;</button>
        </div>
        <form method="POST" action="" style="padding:20px;">
            <input type="hidden" name="action_enviar_correo_licencia" value="1">
            <input type="hidden" name="id_licencia" id="email_id_licencia">

            <div class="form-group">
                <label class="form-label">Correo Electrónico Destinatario *</label>
                <input type="email" name="email" id="email_destinatario" class="form-control" placeholder="ej. cliente@empresa.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">Asunto del Correo *</label>
                <input type="text" name="asunto" id="email_asunto" class="form-control" value="Recordatorio de Vencimiento de Licencia Contable" required>
            </div>

            <div class="form-group">
                <label class="form-label">Enlace de Video Tutorial / Pago (Opcional):</label>
                <input type="url" name="enlace_video" class="form-control" placeholder="https://youtube.com/... o https://pago.empresa.com">
            </div>

            <div class="form-group">
                <label class="form-label">Mensaje Adicional para el Cliente:</label>
                <textarea name="mensaje_custom" class="form-control" rows="3" placeholder="ej. Estimado cliente, su suscripción vence pronto. Favor comunicarse para realizar el pago."></textarea>
            </div>

            <div style="background:#fffbe6; border:1px solid #ffe58f; padding:12px; border-radius:6px; font-size:12px; color:#873800; margin-bottom:15px;">
                ℹ️ Se enviará un correo formal especificando el serial de la licencia, la fecha exacta de expiración y las instrucciones de renovación.
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEnviarCorreoLicencia')">Cancelar</button>
                <button type="submit" class="btn btn-primary">📧 Confirmar y Enviar Correo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nueva Licencia -->
<div class="modal-backdrop" id="modalNuevaLicencia">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Generar Nueva Licencia de Sistema</h3>
            <button class="close-modal" onclick="closeModal('modalNuevaLicencia')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_licencia" value="1">
            
            <div class="form-group">
                <label class="form-label">Cliente Corporativo:</label>
                <select name="id_cliente_empresa" class="form-control" required>
                    <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nombre_cliente'] . ' (NIT: ' . $cl['nit'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Tipo de Licencia:</label>
                    <select name="tipo_licencia" class="form-control" required>
                        <option value="Mensual">Mensual</option>
                        <option value="Anual" selected>Anual</option>
                        <option value="Vitalicia">Vitalicia (Sin Expiración)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Límite Máximo de Empresas:</label>
                    <input type="number" name="max_empresas" class="form-control" value="10" min="1" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha Inicio:</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha Expiración:</label>
                    <input type="date" name="fecha_expiracion" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                </div>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevaLicencia')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Generar Serial & Activar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Licencia -->
<div class="modal-backdrop" id="modalEditarLicencia">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Editar Licencia</h3>
            <button class="close-modal" onclick="closeModal('modalEditarLicencia')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_update_licencia" value="1">
            <input type="hidden" name="id_licencia" id="edit_id_licencia">
            
            <div class="form-group">
                <label class="form-label">Clave de Licencia (Serial):</label>
                <input type="text" id="edit_clave_licencia" class="form-control" readonly style="background:#f1f5f9;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Tipo de Licencia:</label>
                    <select name="tipo_licencia" id="edit_tipo_licencia" class="form-control" required>
                        <option value="Mensual">Mensual</option>
                        <option value="Anual">Anual</option>
                        <option value="Vitalicia">Vitalicia</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Límite Máximo de Empresas:</label>
                    <input type="number" name="max_empresas" id="edit_max_empresas" class="form-control" min="1" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha Inicio:</label>
                    <input type="date" name="fecha_inicio" id="edit_fecha_inicio" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha Expiración:</label>
                    <input type="date" name="fecha_expiracion" id="edit_fecha_expiracion" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Estado:</label>
                <select name="estado" id="edit_estado_licencia" class="form-control" required>
                    <option value="Activa">Activa</option>
                    <option value="Vencida">Vencida</option>
                    <option value="Suspendida">Suspendida</option>
                </select>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditarLicencia')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Licencia</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarLicencia(data) {
    document.getElementById('edit_id_licencia').value = data.id;
    document.getElementById('edit_clave_licencia').value = data.clave_licencia || '';
    document.getElementById('edit_tipo_licencia').value = data.tipo_licencia || 'Anual';
    document.getElementById('edit_max_empresas').value = data.max_empresas || 10;
    document.getElementById('edit_fecha_inicio').value = data.fecha_inicio || '';
    document.getElementById('edit_fecha_expiracion').value = data.fecha_expiracion || '';
    document.getElementById('edit_estado_licencia').value = data.estado || 'Activa';

    openModal('modalEditarLicencia');
}

function prepararCorreoLicencia(data) {
    document.getElementById('email_id_licencia').value = data.id;
    document.getElementById('email_destinatario').value = data.email || '';
    document.getElementById('email_asunto').value = 'Recordatorio de Vencimiento de Licencia Contable (' + (data.nombre_cliente || '') + ')';
    
    openModal('modalEnviarCorreoLicencia');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

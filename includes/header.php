<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';

// Enforce Login Protection
require_login();

$usuario_actual = get_current_user_data();

// Handle company switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_switch_empresa'])) {
    $new_empresa_id = (int)$_POST['switch_empresa_id'];
    set_active_empresa_id($new_empresa_id);
    redirect_to($_SERVER['REQUEST_URI']);
}

$empresa_actual = get_active_empresa();
$todas_empresas = get_todas_empresas();

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Contable Guatemala - <?= htmlspecialchars($empresa_actual['nombre_comercial']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/contabilidad/assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                Modulo Contabilidad
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">General</div>
            <a href="/contabilidad/index.php" class="nav-link <?= ($current_page === 'index.php' && $current_dir === 'contabilidad') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a href="/contabilidad/modules/empresas/index.php" class="nav-link <?= ($current_dir === 'empresas') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 7v14M21 7v14M6 18h12M6 14h12M6 10h12M9 3h6v4H9z"/></svg>
                Empresas
            </a>

            <div class="nav-section-title">Contabilidad Core</div>
            <a href="/contabilidad/modules/catalogo/index.php" class="nav-link <?= ($current_dir === 'catalogo') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h12"/></svg>
                Catálogo de Cuentas & CC
            </a>
            <a href="/contabilidad/modules/operaciones/partidas.php" class="nav-link <?= ($current_page === 'partidas.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Partidas / Asientos
            </a>
            <a href="/contabilidad/modules/operaciones/libros.php" class="nav-link <?= ($current_page === 'libros.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Libros Obligatorios
            </a>
            <a href="/contabilidad/modules/estados_financieros/index.php" class="nav-link <?= ($current_dir === 'estados_financieros') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Estados Financieros
            </a>

            <div class="nav-section-title">Fiscal Guatemala (SAT)</div>
            <a href="/contabilidad/modules/fiscal/compras_ventas.php" class="nav-link <?= ($current_page === 'compras_ventas.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                Libros IVA Compras/Ventas
            </a>
            <a href="/contabilidad/modules/fiscal/isr.php" class="nav-link <?= ($current_page === 'isr.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                Regímenes de ISR
            </a>
            <a href="/contabilidad/modules/fel/dte.php" class="nav-link <?= ($current_dir === 'fel') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                FEL (Factura Electrónica)
            </a>
            <a href="/contabilidad/modules/fiscal/declaraguate.php" class="nav-link <?= ($current_page === 'declaraguate.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Archivos Declaraguate
            </a>

            <div class="nav-section-title">Módulos Auxiliares</div>
            <a href="/contabilidad/modules/nomina/index.php" class="nav-link <?= ($current_dir === 'nomina') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Nómina & IGSS (Planilla)
            </a>
            <a href="/contabilidad/modules/bancos/conciliacion.php" class="nav-link <?= ($current_dir === 'bancos') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Conciliación Bancaria
            </a>

            <?php if (is_admin()): ?>
                <!-- SECCIÓN ADMINISTRACIÓN -->
                <div class="nav-section-title">ADMINISTRACIÓN</div>
                <a href="/contabilidad/modules/admin/permisos.php" class="nav-link <?= ($current_page === 'permisos.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Permisos
                </a>
                <a href="/contabilidad/modules/admin/usuarios.php" class="nav-link <?= ($current_page === 'usuarios.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Usuarios
                </a>
                <a href="/contabilidad/modules/admin/cliente_empresas.php" class="nav-link <?= ($current_page === 'cliente_empresas.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    Cliente-Empresa
                </a>
                <a href="/contabilidad/modules/admin/licencias.php" class="nav-link <?= ($current_page === 'licencias.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    Licenciamiento
                </a>
                <a href="/contabilidad/modules/admin/permisos_empresa.php" class="nav-link <?= ($current_page === 'permisos_empresa.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Permisos Empresa
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Topbar -->
        <header class="topbar no-print">
            <div class="topbar-company-selector">
                <form method="POST" action="" style="display: flex; align-items: center; gap: 8px;">
                    <input type="hidden" name="action_switch_empresa" value="1">
                    <label style="font-size:12px; font-weight:700; color:var(--text-muted);">Empresa Activa:</label>
                    <select name="switch_empresa_id" class="company-select" onchange="this.form.submit()">
                        <?php foreach ($todas_empresas as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ($emp['id'] == $empresa_actual['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nombre_comercial']) ?> (NIT: <?= htmlspecialchars($emp['nit']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <span class="company-badge"><?= htmlspecialchars($empresa_actual['regimen_isr']) ?></span>
                <?php if ($empresa_actual['es_agente_retencion']): ?>
                    <span class="badge badge-warning">Agente de Retención</span>
                <?php endif; ?>

                <?php 
                $licencia_actual = get_licencia_actual();
                $fecha_exp_fmt = date('d/m/Y', strtotime($licencia_actual['fecha_expiracion']));
                $dias_restantes = (int)ceil((strtotime($licencia_actual['fecha_expiracion']) - time()) / 86400);
                ?>
                <span class="badge <?= ($dias_restantes > 0 || $licencia_actual['tipo_licencia'] === 'Vitalicia') ? 'badge-success' : 'badge-danger' ?>" style="display:inline-flex; align-items:center; gap:4px; font-weight:700;" title="Serial: <?= htmlspecialchars($licencia_actual['clave_licencia']) ?>">
                    📜 Licencia <?= htmlspecialchars($licencia_actual['tipo_licencia']) ?>: 
                    <?= $licencia_actual['tipo_licencia'] === 'Vitalicia' ? 'Sin Expiración' : 'Expira ' . $fecha_exp_fmt ?>
                </span>
            </div>

            <div class="topbar-actions">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="text-align:right;">
                        <div style="font-size:13px; font-weight:700; color:var(--secondary);"><?= htmlspecialchars($usuario_actual['nombre_completo'] ?? 'Usuario') ?></div>
                        <span class="badge badge-secondary" style="font-size:10px;"><?= htmlspecialchars($usuario_actual['nombre_rol'] ?? 'Usuario') ?></span>
                    </div>
                </div>

                <a href="/contabilidad/logout.php" class="btn btn-secondary btn-sm" style="color:var(--danger); border-color:#fecaca; background:#fef2f2;" title="Cerrar Sesión">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Cierre de sesión
                </a>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="content">

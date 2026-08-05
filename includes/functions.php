<?php
// functions.php - Core accounting and tax utilities for Guatemala

if (ob_get_level() == 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

/**
 * Safe Redirect Function (Works even if HTML headers were already output)
 */
function redirect_to($url) {
    if (headers_sent()) {
        echo "<script>window.location.href=" . json_encode($url) . ";</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
    } else {
        header("Location: " . $url);
    }
    exit;
}

/**
 * Session & Authentication Helpers
 */
function is_logged_in() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect_to("/contabilidad/login.php");
    }
}

function get_current_user_data() {
    global $pdo;
    if (!is_logged_in()) return null;
    $id = (int)$_SESSION['usuario_id'];
    $stmt = $pdo->prepare("SELECT u.*, r.nombre as nombre_rol FROM contabilidad_usuarios u LEFT JOIN contabilidad_roles r ON u.id_rol = r.id WHERE u.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function is_admin() {
    $u = get_current_user_data();
    if (!$u) return false;
    $rol = strtolower(trim($u['nombre_rol'] ?? ''));
    return ($rol === 'administrador' || $rol === 'administrador empresa' || $rol === 'administrador_empresa');
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        redirect_to("/contabilidad/index.php?error=no_permission");
    }
}

/**
 * Get active license info for the system/client
 */
function get_licencia_actual() {
    global $pdo;
    $usuario = get_current_user_data();
    $id_cliente_empresa = $usuario['id_cliente_empresa'] ?? null;
    
    if ($id_cliente_empresa) {
        $stmt = $pdo->prepare("SELECT l.*, c.nombre_cliente FROM contabilidad_licencias l JOIN contabilidad_cliente_empresas c ON l.id_cliente_empresa = c.id WHERE l.id_cliente_empresa = ? ORDER BY l.id DESC LIMIT 1");
        $stmt->execute([$id_cliente_empresa]);
        $lic = $stmt->fetch();
        if ($lic) return $lic;
    }
    
    $stmt = $pdo->query("SELECT l.*, c.nombre_cliente FROM contabilidad_licencias l JOIN contabilidad_cliente_empresas c ON l.id_cliente_empresa = c.id ORDER BY l.id DESC LIMIT 1");
    $lic = $stmt->fetch();
    return $lic ?: [
        'clave_licencia' => 'CONTABILIDAD-GT-DEMO',
        'tipo_licencia' => 'Anual',
        'fecha_expiracion' => date('Y-12-31'),
        'estado' => 'Activa'
    ];
}

/**
 * Get companies available to the current user
 */
function get_todas_empresas() {
    global $pdo;
    if (!is_logged_in()) {
        $stmt = $pdo->query("SELECT * FROM contabilidad_empresas WHERE estado = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    $user_id = (int)$_SESSION['usuario_id'];

    // Admin & Administrador_Empresa have access to all companies
    if (is_admin()) {
        $stmt = $pdo->query("SELECT * FROM contabilidad_empresas WHERE estado = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    // Regular users: filter by contabilidad_usuario_empresas
    $stmt = $pdo->prepare("SELECT e.* FROM contabilidad_empresas e JOIN contabilidad_usuario_empresas ue ON e.id = ue.id_empresa WHERE ue.id_usuario = ? AND e.estado = 1 AND ue.estado = 1 ORDER BY e.id ASC");
    $stmt->execute([$user_id]);
    $empresas = $stmt->fetchAll();

    if (empty($empresas)) {
        $stmtAll = $pdo->query("SELECT * FROM contabilidad_empresas WHERE estado = 1 ORDER BY id ASC");
        return $stmtAll->fetchAll();
    }
    return $empresas;
}

/**
 * Get currently selected active company ID or default to first company
 */
function get_active_empresa_id() {
    global $pdo;
    if (isset($_SESSION['active_empresa_id']) && !empty($_SESSION['active_empresa_id'])) {
        return (int)$_SESSION['active_empresa_id'];
    }
    
    $empresas = get_todas_empresas();
    if (!empty($empresas)) {
        $id = (int)$empresas[0]['id'];
        $_SESSION['active_empresa_id'] = $id;
        return $id;
    }
    return 1;
}

/**
 * Set active company ID in session
 */
function set_active_empresa_id($empresa_id) {
    $_SESSION['active_empresa_id'] = (int)$empresa_id;
}

/**
 * Get active company row array
 */
function get_active_empresa() {
    global $pdo;
    $id = get_active_empresa_id();
    $stmt = $pdo->prepare("SELECT * FROM contabilidad_empresas WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: [
        'id' => 1,
        'nit' => 'CF',
        'razon_social' => 'Empresa Principal, S.A.',
        'nombre_comercial' => 'Empresa Principal',
        'regimen_isr' => 'Opcional Simplificado',
        'es_agente_retencion' => 1
    ];
}

/**
 * Format currency in Guatemalan Quetzales (GTQ)
 */
function format_gtq($amount, $currency = 'GTQ') {
    $symbol = ($currency === 'USD') ? '$ ' : 'Q ';
    return $symbol . number_format((float)$amount, 2, '.', ',');
}

/**
 * Guatemala IVA Calculation (12%)
 */
function calcular_iva($total) {
    $total = (float)$total;
    $neto = round($total / 1.12, 2);
    $iva = round($total - $neto, 2);
    return [
        'neto' => $neto,
        'iva' => $iva,
        'total' => $total
    ];
}

/**
 * Guatemala ISR Régimen Opcional Simplificado sobre Ingresos (Mensual)
 */
function calcular_isr_opcional_mensual($monto_mensual) {
    $monto = (float)$monto_mensual;
    if ($monto <= 0) return 0.00;
    
    if ($monto <= 30000.00) {
        return round($monto * 0.05, 2);
    } else {
        $excedente = $monto - 30000.00;
        return round(1500.00 + ($excedente * 0.07), 2);
    }
}

/**
 * Calculation of ISR Retention on Supplier Invoice
 */
function calcular_retencion_isr_proveedor($monto_neto, $regimen_proveedor = 'Opcional Simplificado') {
    if ($regimen_proveedor !== 'Opcional Simplificado') return 0.00;
    $neto = (float)$monto_neto;
    if ($neto < 2500.00) return 0.00;
    
    return calcular_isr_opcional_mensual($neto);
}

/**
 * Guatemala Payroll Statutory Formulas
 */
function calcular_igss_laboral($salario_devengado) {
    return round((float)$salario_devengado * 0.0483, 2);
}

function calcular_igss_patronal($salario_devengado) {
    return round((float)$salario_devengado * 0.1067, 2);
}

function calcular_irtra_patronal($salario_devengado) {
    return round((float)$salario_devengado * 0.01, 2);
}

function calcular_intecap_patronal($salario_devengado) {
    return round((float)$salario_devengado * 0.01, 2);
}

function calcular_bonificacion_decreto_37_2001($dias_trabajados = 30) {
    $dias = (int)$dias_trabajados;
    return round((250.00 / 30.00) * $dias, 2);
}

/**
 * Labor Provisions for Benefits
 */
function calcular_provisiones_laborales($salario_base) {
    $salario = (float)$salario_base;
    return [
        'aguinaldo' => round($salario * (1 / 12), 2),
        'bono14' => round($salario * (1 / 12), 2),
        'vacaciones' => round($salario * (15 / 365), 2),
        'indemnizacion' => round($salario * (1 / 12), 2),
        'total_provisiones' => round($salario * ((1/12) + (1/12) + (15/365) + (1/12)), 2)
    ];
}

/**
 * Annual Projection for Employee ISR Retention
 */
function calcular_isr_asalariado_mensual($salario_mensual, $bonificacion_mensual = 250.00) {
    $salario = (float)$salario_mensual;
    $salario_anual = $salario * 12;
    $aguinaldo = $salario;
    $bono14 = $salario;
    $ingreso_bruto_anual = $salario_anual + $aguinaldo + $bono14;
    
    $igss_anual = calcular_igss_laboral($salario) * 12;
    $deducciones_anuales = 48000.00 + $igss_anual + $aguinaldo + $bono14;
    
    $renta_imponible = $ingreso_bruto_anual - $deducciones_anuales;
    if ($renta_imponible <= 0) return 0.00;
    
    if ($renta_imponible <= 300000.00) {
        $isr_anual = $renta_imponible * 0.05;
    } else {
        $excedente = $renta_imponible - 300000.00;
        $isr_anual = 15000.00 + ($excedente * 0.07);
    }
    
    return round($isr_anual / 12, 2);
}

/**
 * Get Next Journal Entry Correlative for active company
 */
function get_siguiente_correlativo_partida($id_empresa) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT MAX(correlativo) FROM contabilidad_partidas WHERE id_empresa = ?");
    $stmt->execute([(int)$id_empresa]);
    $max = $stmt->fetchColumn();
    return ($max ? (int)$max : 0) + 1;
}

/**
 * Send HTML Email via SMTP with PHPMailer
 */
function enviar_correo_smtp($toEmail, $toName, $subject, $bodyHtml) {
    $phpmailerPath = 'C:/xampp/htdocs/sistemasenred/PHPMailer/src/';
    $envPath = 'C:/xampp/htdocs/sistemasenred/.env';

    if (!file_exists($phpmailerPath . 'PHPMailer.php')) {
        return ['exito' => false, 'mensaje' => 'No se encontró la librería PHPMailer en la ruta especificada.'];
    }

    require_once $phpmailerPath . 'Exception.php';
    require_once $phpmailerPath . 'PHPMailer.php';
    require_once $phpmailerPath . 'SMTP.php';

    $env = [];
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $env[trim($name)] = trim($value);
            }
        }
    }

    $smtpPass = $env['SMTP_PASS'] ?? 'Sj32a803a';
    $correoContacto = $env['CORREO'] ?? 'info@sistemasenred.com';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.sistemasenred.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $correoContacto;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($correoContacto, 'Modulo Contabilidad - Sistemas en Red');
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
        return ['exito' => true, 'mensaje' => 'Correo enviado correctamente'];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error SMTP: ' . $mail->ErrorInfo];
    }
}

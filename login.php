<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';

// Handle AJAX request to fetch allowed companies for user input
if (isset($_GET['action']) && $_GET['action'] === 'get_empresas_usuario') {
    header('Content-Type: application/json');
    $userVal = trim($_GET['usuario'] ?? '');
    if (empty($userVal)) {
        echo json_encode([]);
        exit;
    }
    
    $stmtU = $pdo->prepare("SELECT u.id, r.nombre as nombre_rol FROM contabilidad_usuarios u LEFT JOIN contabilidad_roles r ON u.id_rol = r.id WHERE u.usuario = ? OR u.email = ? LIMIT 1");
    $stmtU->execute([$userVal, $userVal]);
    $uData = $stmtU->fetch();
    
    if (!$uData) {
        echo json_encode([]);
        exit;
    }
    
    $rol = strtolower(trim($uData['nombre_rol'] ?? ''));
    if ($rol === 'administrador' || $rol === 'administrador empresa' || $rol === 'administrador_empresa') {
        $stmtE = $pdo->query("SELECT id, nit, nombre_comercial FROM contabilidad_empresas WHERE estado = 1 ORDER BY id ASC");
        echo json_encode($stmtE->fetchAll(PDO::FETCH_ASSOC));
        exit;
    } else {
        $stmtE = $pdo->prepare("SELECT e.id, e.nit, e.nombre_comercial FROM contabilidad_empresas e JOIN contabilidad_usuario_empresas ue ON e.id = ue.id_empresa WHERE ue.id_usuario = ? AND e.estado = 1 AND ue.estado = 1 ORDER BY e.id ASC");
        $stmtE->execute([$uData['id']]);
        echo json_encode($stmtE->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
}

// If already logged in, redirect to index
if (is_logged_in()) {
    redirect_to("/contabilidad/index.php");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnIngresar'])) {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    $id_empresa = isset($_POST['empresa']) ? (int)$_POST['empresa'] : 0;

    if (empty($usuario) || empty($password)) {
        $error = "Debe ingresar su usuario y contraseña.";
    } else {
        $stmt = $pdo->prepare("SELECT u.*, r.nombre as nombre_rol FROM contabilidad_usuarios u LEFT JOIN contabilidad_roles r ON u.id_rol = r.id WHERE (u.usuario = ? OR u.email = ?) AND u.estado = 1 LIMIT 1");
        $stmt->execute([$usuario, $usuario]);
        $row = $stmt->fetch();

        if ($row && (password_verify($password, $row['password']) || $password === $row['password'])) {
            // Login success
            $_SESSION['usuario_id'] = (int)$row['id'];
            $_SESSION['usuario_nombre'] = $row['nombre_completo'];
            $_SESSION['usuario_login'] = $row['usuario'];
            $_SESSION['usuario_rol'] = $row['nombre_rol'];
            
            // Update last login
            $stmtUpd = $pdo->prepare("UPDATE contabilidad_usuarios SET ultimo_login = NOW() WHERE id = ?");
            $stmtUpd->execute([$row['id']]);

            if ($id_empresa > 0) {
                set_active_empresa_id($id_empresa);
            } else {
                // Default to first company allowed for this user
                $userEmpresas = get_todas_empresas();
                if (!empty($userEmpresas)) {
                    set_active_empresa_id($userEmpresas[0]['id']);
                }
            }

            redirect_to("/contabilidad/index.php");
        } else {
            $error = "Usuario o contraseña incorrectos, o usuario inactivo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema Contable Guatemala</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/contabilidad/assets/css/main.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0284c7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            width: 100%;
            max-width: 440px;
            padding: 36px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3), 0 8px 10px -6px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            box-shadow: 0 8px 16px rgba(37,99,235,0.35);
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .login-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h1 class="login-title">Sistema Contable GT</h1>
        <p class="login-subtitle">Ingrese sus credenciales de acceso institucional</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-danger">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label">Usuario o Correo Electrónico:</label>
            <input type="text" name="usuario" id="usuarioInput" class="form-control" placeholder="ej. admin" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label">Contraseña:</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <div class="form-group">
            <label class="form-label">Empresa Principal Asignada:</label>
            <select name="empresa" id="empresaSelect" class="form-control">
                <option value="0">-- Ingrese su usuario para cargar empresas --</option>
            </select>
        </div>

        <button type="submit" name="btnIngresar" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; margin-top: 10px;">
            🔐 Iniciar Sesión
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userInput = document.getElementById('usuarioInput');
    const empresaSelect = document.getElementById('empresaSelect');

    function cargarEmpresasUsuario() {
        const val = userInput.value.trim();
        if (!val) {
            empresaSelect.innerHTML = '<option value="0">-- Ingrese su usuario para cargar empresas --</option>';
            return;
        }

        fetch('login.php?action=get_empresas_usuario&usuario=' + encodeURIComponent(val))
            .then(res => res.json())
            .then(data => {
                empresaSelect.innerHTML = '';
                if (!data || data.length === 0) {
                    empresaSelect.innerHTML = '<option value="0">-- No hay empresas asignadas --</option>';
                } else {
                    if (data.length > 1) {
                        empresaSelect.innerHTML = '<option value="0">-- Seleccionar Empresa al Iniciar --</option>';
                    }
                    data.forEach(emp => {
                        const opt = document.createElement('option');
                        opt.value = emp.id;
                        opt.textContent = emp.nombre_comercial + ' (NIT: ' + emp.nit + ')';
                        empresaSelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error(err));
    }

    userInput.addEventListener('blur', cargarEmpresasUsuario);
    userInput.addEventListener('input', cargarEmpresasUsuario);

    if (userInput.value.trim()) {
        cargarEmpresasUsuario();
    }
});
</script>

</body>
</html>

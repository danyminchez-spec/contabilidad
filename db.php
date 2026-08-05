<?php
if (ob_get_level() == 0) {
    ob_start();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Obtener el dominio actual
$dominio_actual = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($dominio_actual, 'sistemasenred.com') !== false) {
    // -------------------------------------------------------------
    // Configuración para Producción (sistemasenred.com)
    // -------------------------------------------------------------
  // Configuración Servidor Remoto (StackCP)
        $host     = 'sdb-84.hosting.stackcp.net';
        $db       = 'sistemas-353039362ef3';
        $user     = 'usr_sistemas';
        $pass     = 'g%(tuh:#6Ty8';
        $charset  = 'utf8mb4';
       // $port     = '43306';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Timeout de 5 segundos para no dejar colgada la página si el servidor no responde
            PDO::ATTR_TIMEOUT            => 5 
        ];

} else {
    // -------------------------------------------------------------
    // Configuración para XAMPP (Entorno Local)
    // -------------------------------------------------------------
      $host     = 'mysql.us.stackcp.com';
        $db       = 'sistemas-353039362ef3';
        $user     = 'usr_sistemas';
        $pass     = 'g%(tuh:#6Ty8';
        $charset  = 'utf8mb4';
        $port     = '43306';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Timeout de 5 segundos para no dejar colgada la página si el servidor no responde
            PDO::ATTR_TIMEOUT            => 5 
        ];
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si la conexión falla, se interrumpe la ejecución y se despliega este mensaje formatteado
    die("
        <div style='font-family: Arial, sans-serif; margin: 30px auto; max-width: 600px; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 6px;'>
            <h3 style='margin-top:0;'>⚠️ Error de Conexión a la Base de Datos Remota</h3>
            <p>No se pudo establecer comunicación con el servidor MySQL (<strong>{$host}</strong>).</p>
            <p><strong>Detalle del Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <hr style='border:0; border-top:1px solid #f5c6cb;'>
            <small><strong>Posible solución:</strong> Si estás ejecutando el proyecto localmente (XAMPP), asegúrate de autorizar tu dirección IP pública en el panel de control de <strong>StackCP</strong> (sección <em>MySQL / Allowed IP Addresses</em>).</small>
        </div>
    ");
}
?>

<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Disable foreign key checks during creation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. contabilidad_empresas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_empresas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nit` VARCHAR(20) NOT NULL,
        `razon_social` VARCHAR(150) NOT NULL,
        `nombre_comercial` VARCHAR(150) NOT NULL,
        `direccion` TEXT NULL,
        `regimen_isr` ENUM('Opcional Simplificado', 'Sobre Utilidades') NOT NULL DEFAULT 'Opcional Simplificado',
        `es_agente_retencion` TINYINT(1) NOT NULL DEFAULT 0,
        `logo` VARCHAR(255) NULL,
        `estado` TINYINT(1) NOT NULL DEFAULT 1,
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. contabilidad_centros_costo
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_centros_costo` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `codigo` VARCHAR(20) NOT NULL,
        `nombre` VARCHAR(100) NOT NULL,
        `tipo` ENUM('Área', 'Proyecto', 'Sucursal') NOT NULL DEFAULT 'Área',
        `estado` TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. contabilidad_cuentas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_cuentas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `codigo_cuenta` VARCHAR(30) NOT NULL,
        `nombre_cuenta` VARCHAR(150) NOT NULL,
        `nivel` TINYINT NOT NULL DEFAULT 1,
        `id_padre` INT NULL,
        `tipo_cuenta` ENUM('Activo', 'Pasivo', 'Patrimonio', 'Ingresos', 'Costos', 'Gastos') NOT NULL,
        `requiere_centro_costo` TINYINT(1) NOT NULL DEFAULT 0,
        `rubro_sat` VARCHAR(50) NULL,
        `saldo_inicial` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `saldo_actual` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `estado` TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. contabilidad_periodos
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_periodos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `anio` INT NOT NULL,
        `mes` TINYINT NOT NULL,
        `fecha_inicio` DATE NOT NULL,
        `fecha_fin` DATE NOT NULL,
        `estado` ENUM('Abierto', 'Cerrado') NOT NULL DEFAULT 'Abierto',
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. contabilidad_partidas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_partidas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `correlativo` INT NOT NULL,
        `fecha` DATE NOT NULL,
        `tipo_partida` ENUM('Diario', 'Apertura', 'Ajuste', 'Cierre', 'Nómina', 'Ventas', 'Compras') NOT NULL DEFAULT 'Diario',
        `id_centro_costo` INT NULL,
        `concepto` TEXT NOT NULL,
        `total_debe` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_haber` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `id_periodo` INT NULL,
        `creado_por` VARCHAR(100) DEFAULT 'Sistema',
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. contabilidad_partida_detalles
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_partida_detalles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_partida` INT NOT NULL,
        `id_cuenta` INT NOT NULL,
        `id_centro_costo` INT NULL,
        `concepto_linea` VARCHAR(255) NULL,
        `debe` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `haber` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `orden` INT NOT NULL DEFAULT 1,
        FOREIGN KEY (`id_partida`) REFERENCES `contabilidad_partidas`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`id_cuenta`) REFERENCES `contabilidad_cuentas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. contabilidad_clientes_proveedores
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_clientes_proveedores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `tipo` ENUM('Cliente', 'Proveedor', 'Ambos') NOT NULL DEFAULT 'Cliente',
        `nit` VARCHAR(20) NOT NULL,
        `nombre_razon_social` VARCHAR(150) NOT NULL,
        `direccion` TEXT NULL,
        `regimen_isr` ENUM('Opcional Simplificado', 'Sobre Utilidades', 'Pequeño Contribuyente') NOT NULL DEFAULT 'Opcional Simplificado',
        `es_agente_retencion` TINYINT(1) NOT NULL DEFAULT 0,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 8. contabilidad_libro_compras
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_libro_compras` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `id_partida` INT NULL,
        `id_proveedor` INT NOT NULL,
        `fecha_factura` DATE NOT NULL,
        `tipo_documento` ENUM('Factura', 'Nota de Crédito', 'Nota de Débito', 'DTE') NOT NULL DEFAULT 'Factura',
        `serie` VARCHAR(20) NOT NULL,
        `numero` VARCHAR(30) NOT NULL,
        `nit_proveedor` VARCHAR(20) NOT NULL,
        `nombre_proveedor` VARCHAR(150) NOT NULL,
        `monto_exento` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `monto_neto` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `monto_iva` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `estado_retencion_isr` ENUM('No Aplica', 'Pendiente', 'Retenido', 'Constancia Emitida') NOT NULL DEFAULT 'No Aplica',
        `constancia_retencion_num` VARCHAR(50) NULL,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 9. contabilidad_libro_ventas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_libro_ventas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `id_partida` INT NULL,
        `id_cliente` INT NOT NULL,
        `fecha_factura` DATE NOT NULL,
        `tipo_documento` ENUM('Factura', 'Nota de Crédito', 'Nota de Débito', 'Factura Pequeño Contribuyente') NOT NULL DEFAULT 'Factura',
        `serie` VARCHAR(20) NOT NULL,
        `numero` VARCHAR(30) NOT NULL,
        `nit_cliente` VARCHAR(20) NOT NULL,
        `nombre_cliente` VARCHAR(150) NOT NULL,
        `monto_exento` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `monto_neto` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `monto_iva` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `uuid_fel` VARCHAR(100) NULL,
        `fecha_certificacion_fel` DATETIME NULL,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 10. contabilidad_empleados
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_empleados` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `codigo` VARCHAR(20) NOT NULL,
        `nit` VARCHAR(20) NULL,
        `dpi` VARCHAR(20) NOT NULL,
        `nombres` VARCHAR(100) NOT NULL,
        `apellidos` VARCHAR(100) NOT NULL,
        `fecha_ingreso` DATE NOT NULL,
        `puesto` VARCHAR(100) NOT NULL,
        `id_centro_costo` INT NULL,
        `salario_base` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `bonificacion_ley` DECIMAL(12,2) NOT NULL DEFAULT 250.00,
        `es_sujeto_isr` TINYINT(1) NOT NULL DEFAULT 0,
        `deducciones_otras` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `estado` TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 11. contabilidad_nominas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_nominas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `periodo_nomina` VARCHAR(50) NOT NULL,
        `anio` INT NOT NULL,
        `mes` TINYINT NOT NULL,
        `fecha_inicio` DATE NOT NULL,
        `fecha_fin` DATE NOT NULL,
        `total_salarios` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_bonificaciones` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_igss_laboral` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_igss_patronal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_irtra` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_intecap` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_isr_asalariados` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total_neto_pagar` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `id_partida` INT NULL,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 12. contabilidad_nomina_detalles
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_nomina_detalles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_nomina` INT NOT NULL,
        `id_empleado` INT NOT NULL,
        `salario_base` DECIMAL(12,2) NOT NULL,
        `dias_trabajados` INT NOT NULL DEFAULT 30,
        `salario_devengado` DECIMAL(12,2) NOT NULL,
        `bonificacion_incentivo` DECIMAL(12,2) NOT NULL DEFAULT 250.00,
        `igss_laboral` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `igss_patronal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `irtra_patronal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `intecap_patronal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `prov_aguinaldo` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `prov_bono14` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `prov_vacaciones` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `prov_indemnizacion` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `isr_asalariados` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_descuentos` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `liquido_recibir` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (`id_nomina`) REFERENCES `contabilidad_nominas`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`id_empleado`) REFERENCES `contabilidad_empleados`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 13. contabilidad_bancos_cuentas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_bancos_cuentas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `nombre_banco` VARCHAR(100) NOT NULL,
        `numero_cuenta` VARCHAR(50) NOT NULL,
        `moneda` ENUM('GTQ', 'USD') NOT NULL DEFAULT 'GTQ',
        `id_cuenta_contable` INT NULL,
        `saldo_banco` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 14. contabilidad_bancos_movimientos
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_bancos_movimientos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_cuenta_banco` INT NOT NULL,
        `fecha` DATE NOT NULL,
        `tipo_movimiento` ENUM('Deposito', 'Cheque', 'Transferencia', 'Nota_Debito', 'Nota_Credito') NOT NULL,
        `referencia` VARCHAR(50) NOT NULL,
        `concepto` VARCHAR(255) NOT NULL,
        `monto` DECIMAL(14,2) NOT NULL,
        `conciliado` TINYINT(1) NOT NULL DEFAULT 0,
        `id_partida` INT NULL,
        FOREIGN KEY (`id_cuenta_banco`) REFERENCES `contabilidad_bancos_cuentas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 15. contabilidad_fel_dte
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_fel_dte` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_empresa` INT NOT NULL,
        `tipo_dte` ENUM('FACT', 'FCAM', 'FPEQ', 'NCRE', 'NDEB', 'NABO') NOT NULL DEFAULT 'FACT',
        `serie` VARCHAR(20) NOT NULL,
        `numero` VARCHAR(30) NOT NULL,
        `uuid` VARCHAR(100) NOT NULL,
        `fecha_emision` DATETIME NOT NULL,
        `nit_emisor` VARCHAR(20) NOT NULL,
        `nit_receptor` VARCHAR(20) NOT NULL,
        `monto_neto` DECIMAL(14,2) NOT NULL,
        `monto_iva` DECIMAL(14,2) NOT NULL,
        `monto_total` DECIMAL(14,2) NOT NULL,
        `xml_dte` LONGTEXT NULL,
        `estado_fel` ENUM('Certificado', 'Anulado', 'Error') NOT NULL DEFAULT 'Certificado',
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- NUEVAS TABLAS DE ADMINISTRACIÓN ---

    // 16. contabilidad_cliente_empresas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_cliente_empresas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `codigo_cliente` VARCHAR(30) NOT NULL,
        `nombre_cliente` VARCHAR(150) NOT NULL,
        `nit` VARCHAR(20) NOT NULL,
        `representante` VARCHAR(100) NULL,
        `telefono` VARCHAR(30) NULL,
        `email` VARCHAR(100) NULL,
        `estado` TINYINT(1) NOT NULL DEFAULT 1,
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 17. contabilidad_licencias
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_licencias` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_cliente_empresa` INT NOT NULL,
        `clave_licencia` VARCHAR(100) NOT NULL,
        `tipo_licencia` ENUM('Mensual', 'Anual', 'Vitalicia') NOT NULL DEFAULT 'Anual',
        `max_empresas` INT NOT NULL DEFAULT 5,
        `fecha_inicio` DATE NOT NULL,
        `fecha_expiracion` DATE NOT NULL,
        `estado` ENUM('Activa', 'Vencida', 'Suspendida') NOT NULL DEFAULT 'Activa',
        FOREIGN KEY (`id_cliente_empresa`) REFERENCES `contabilidad_cliente_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 18. contabilidad_roles
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_roles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(50) NOT NULL,
        `descripcion` VARCHAR(255) NULL,
        `estado` TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 19. contabilidad_usuarios
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_usuarios` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre_completo` VARCHAR(120) NOT NULL,
        `usuario` VARCHAR(50) NOT NULL UNIQUE,
        `email` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `id_rol` INT NOT NULL,
        `id_cliente_empresa` INT NULL,
        `estado` TINYINT(1) NOT NULL DEFAULT 1,
        `ultimo_login` DATETIME NULL,
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`id_rol`) REFERENCES `contabilidad_roles`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 20. contabilidad_permisos
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_permisos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_rol` INT NOT NULL,
        `modulo` VARCHAR(50) NOT NULL,
        `puede_ver` TINYINT(1) NOT NULL DEFAULT 1,
        `puede_crear` TINYINT(1) NOT NULL DEFAULT 1,
        `puede_editar` TINYINT(1) NOT NULL DEFAULT 1,
        `puede_eliminar` TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (`id_rol`) REFERENCES `contabilidad_roles`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 21. contabilidad_usuario_empresas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contabilidad_usuario_empresas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_usuario` INT NOT NULL,
        `id_empresa` INT NOT NULL,
        `estado` TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (`id_usuario`) REFERENCES `contabilidad_usuarios`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`id_empresa`) REFERENCES `contabilidad_empresas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // --- SEED ROLES ---
    $chkRoles = $pdo->query("SELECT COUNT(*) FROM contabilidad_roles")->fetchColumn();
    if ($chkRoles == 0) {
        $stmtR = $pdo->prepare("INSERT INTO contabilidad_roles (nombre, descripcion) VALUES (?, ?)");
        $stmtR->execute(['Administrador', 'Acceso completo al sistema, configuración y usuarios']);
        $rolAdminId = $pdo->lastInsertId();
        $stmtR->execute(['Administrador Empresa', 'Administrador de Empresa con gestión contable y administrativa configurable']);
        $rolAdminEmpId = $pdo->lastInsertId();
        $stmtR->execute(['Contador General', 'Acceso a partidas, libros, IVA, ISR y estados financieros']);
        $rolContadorId = $pdo->lastInsertId();
        $stmtR->execute(['Auxiliar Contable', 'Ingreso de partidas y consulta de libros']);
        $stmtR->execute(['Auditor', 'Modo lectura de informes y estados financieros']);

        // Seed default Client Corporate
        $stmtCli = $pdo->prepare("INSERT INTO contabilidad_cliente_empresas (codigo_cliente, nombre_cliente, nit, representante, email) VALUES ('CLI-001', 'Grupo Corporativo GT', '123456-7', 'Lic. Fernando Morales', 'admin@grupogt.com')");
        $stmtCli->execute();
        $cliCorpId = $pdo->lastInsertId();

        // Seed License
        $stmtLic = $pdo->prepare("INSERT INTO contabilidad_licencias (id_cliente_empresa, clave_licencia, tipo_licencia, max_empresas, fecha_inicio, fecha_expiracion, estado) VALUES (?, 'CONTABILIDAD-GT-2026-FULL', 'Vitalicia', 100, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 10 YEAR), 'Activa')");
        $stmtLic->execute([$cliCorpId]);

        // Seed Default Superadmin User (admin / admin123)
        $passHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmtUser = $pdo->prepare("INSERT INTO contabilidad_usuarios (nombre_completo, usuario, email, password, id_rol, id_cliente_empresa, estado) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmtUser->execute(['Administrador del Sistema', 'admin', 'admin@contabilidad.gt', $passHash, $rolAdminId, $cliCorpId]);
        $adminUserId = $pdo->lastInsertId();

        // Map admin user to all companies
        $empresas = $pdo->query("SELECT id FROM contabilidad_empresas")->fetchAll(PDO::FETCH_COLUMN);
        $stmtUe = $pdo->prepare("INSERT INTO contabilidad_usuario_empresas (id_usuario, id_empresa) VALUES (?, ?)");
        foreach ($empresas as $empId) {
            $stmtUe->execute([$adminUserId, $empId]);
        }

        // Seed Default Module Permissions for Admin
        $modulos = ['catalogo', 'partidas', 'libros', 'estados_financieros', 'fiscal', 'fel', 'declaraguate', 'nomina', 'bancos', 'admin'];
        $stmtPerm = $pdo->prepare("INSERT INTO contabilidad_permisos (id_rol, modulo, puede_ver, puede_crear, puede_editar, puede_eliminar) VALUES (?, ?, 1, 1, 1, 1)");
        foreach ($modulos as $mod) {
            $stmtPerm->execute([$rolAdminId, $mod]);
        }
    }

    // Seed Demo Companies data if empty
    $chk = $pdo->query("SELECT COUNT(*) FROM contabilidad_empresas")->fetchColumn();
    if ($chk == 0) {
        $stmtEmp = $pdo->prepare("INSERT INTO contabilidad_empresas (nit, razon_social, nombre_comercial, direccion, regimen_isr, es_agente_retencion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtEmp->execute(['1029384-7', 'Corporación Agrícola e Industrial de Guatemala, S.A.', 'CORPAGRO GT', 'Zona 10, Ciudad de Guatemala', 'Opcional Simplificado', 1]);
        $emp1_id = $pdo->lastInsertId();

        $stmtEmp->execute(['9876543-2', 'Comercializadora del Sur, Sociedad Anónima', 'COMERSUR', 'Calzada Aguilar Batres, Guatemala', 'Sobre Utilidades', 0]);
        $emp2_id = $pdo->lastInsertId();
    }

    echo "<div style='font-family: Arial, sans-serif; padding: 30px; background: #e6fffa; border: 2px solid #319795; color: #234e52; border-radius: 8px; max-width: 700px; margin: 40px auto;'>
            <h2 style='margin-top:0;'>✅ ¡Base de Datos y Módulo de Administración Instalado!</h2>
            <p>Se crearon todas las tablas del sistema contable con el prefijo <strong>contabilidad_*</strong> y las tablas de <strong>Autenticación, Permisos, Usuarios, Licencias y Permisos Empresa</strong>.</p>
            <p><strong>Credenciales Iniciales de Administrador:</strong><br>Usuario: <code>admin</code><br>Contraseña: <code>admin123</code></p>
            <hr style='border:0; border-top:1px solid #b2f5ea; margin: 20px 0;'>
            <a href='login.php' style='display: inline-block; background: #319795; color: #fff; text-decoration: none; padding: 12px 24px; font-weight: bold; border-radius: 6px;'>🔑 Iniciar Sesión en el Sistema</a>
          </div>";

} catch (PDOException $e) {
    echo "<div style='font-family: Arial, sans-serif; padding: 30px; background: #fff5f5; border: 2px solid #e53e3e; color: #742a2a; border-radius: 8px; max-width: 700px; margin: 40px auto;'>
            <h2 style='margin-top:0;'>❌ Error en la Instalación</h2>
            <p><strong>Detalle:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
          </div>";
}

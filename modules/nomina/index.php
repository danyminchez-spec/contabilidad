<?php
require_once __DIR__ . '/../../includes/header.php';

$empresa_id = get_active_empresa_id();

// Handle CREATE EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_empleado'])) {
    $codigo = trim($_POST['codigo']);
    $dpi = trim($_POST['dpi']);
    $nit = trim($_POST['nit']);
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $puesto = trim($_POST['puesto']);
    $salario_base = (float)$_POST['salario_base'];
    $bonificacion_ley = (float)($_POST['bonificacion_ley'] ?? 250.00);

    $stmt = $pdo->prepare("INSERT INTO contabilidad_empleados (id_empresa, codigo, dpi, nit, nombres, apellidos, fecha_ingreso, puesto, salario_base, bonificacion_ley) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$empresa_id, $codigo, $dpi, $nit, $nombres, $apellidos, $fecha_ingreso, $puesto, $salario_base, $bonificacion_ley]);
    redirect_to("index.php?msg=emp_created");
}

// Handle PROCESS PAYROLL (PROCESAR PLANILLA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_procesar_nomina'])) {
    $periodo_nomina = trim($_POST['periodo_nomina']);
    $anio = (int)$_POST['anio'];
    $mes = (int)$_POST['mes'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];

    // Fetch active employees
    $stmtEmp = $pdo->prepare("SELECT * FROM contabilidad_empleados WHERE id_empresa = ? AND estado = 1");
    $stmtEmp->execute([$empresa_id]);
    $empleados = $stmtEmp->fetchAll();

    if (!empty($empleados)) {
        $totSalarios = 0;
        $totBonif = 0;
        $totIgssLab = 0;
        $totIgssPat = 0;
        $totIrtra = 0;
        $totIntecap = 0;
        $totIsrAsal = 0;
        $totProvAguinaldo = 0;
        $totProvBono14 = 0;
        $totProvVac = 0;
        $totProvIndem = 0;
        $totNetoPagar = 0;

        $detallesProcesados = [];

        foreach ($empleados as $e) {
            $salDevengado = (float)$e['salario_base'];
            $bonif = (float)$e['bonificacion_ley'];

            $igssLab = calcular_igss_laboral($salDevengado);
            $igssPat = calcular_igss_patronal($salDevengado);
            $irtra = calcular_irtra_patronal($salDevengado);
            $intecap = calcular_intecap_patronal($salDevengado);

            $isrAsal = calcular_isr_asalariado_mensual($salDevengado, $bonif);
            $provs = calcular_provisiones_laborales($salDevengado);

            $totDescuentos = $igssLab + $isrAsal + (float)$e['deducciones_otras'];
            $liquido = ($salDevengado + $bonif) - $totDescuentos;

            $totSalarios += $salDevengado;
            $totBonif += $bonif;
            $totIgssLab += $igssLab;
            $totIgssPat += $igssPat;
            $totIrtra += $irtra;
            $totIntecap += $intecap;
            $totIsrAsal += $isrAsal;
            $totProvAguinaldo += $provs['aguinaldo'];
            $totProvBono14 += $provs['bono14'];
            $totProvVac += $provs['vacaciones'];
            $totProvIndem += $provs['indemnizacion'];
            $totNetoPagar += $liquido;

            $detallesProcesados[] = [
                'id_empleado' => $e['id'],
                'salario_base' => $e['salario_base'],
                'dias_trabajados' => 30,
                'salario_devengado' => $salDevengado,
                'bonificacion_incentivo' => $bonif,
                'igss_laboral' => $igssLab,
                'igss_patronal' => $igssPat,
                'irtra_patronal' => $irtra,
                'intecap_patronal' => $intecap,
                'prov_aguinaldo' => $provs['aguinaldo'],
                'prov_bono14' => $provs['bono14'],
                'prov_vacaciones' => $provs['vacaciones'],
                'prov_indemnizacion' => $provs['indemnizacion'],
                'isr_asalariados' => $isrAsal,
                'total_descuentos' => $totDescuentos,
                'liquido_recibir' => $liquido
            ];
        }

        $pdo->beginTransaction();

        // 1. Create Automatic Journal Entry (Partida de Nómina)
        // Debits:
        // 6.1.01.01.001 - Sueldos Base (totSalarios)
        // 6.1.01.02.001 - Bonificación Incentivo Ley (totBonif)
        // 6.1.01.03.001 - Cuotas Patronales IGSS/IRTRA/INTECAP (totIgssPat + totIrtra + totIntecap)
        // 6.1.01.04.001 - Prestaciones Laborales (totProvAguinaldo + totProvBono14 + totProvVac + totProvIndem)
        // Credits:
        // 2.1.01.03.002 - IGSS Cuota Laboral y Patronal por Pagar (totIgssLab + totIgssPat + totIrtra + totIntecap)
        // 2.1.01.03.003 - ISR Retención Asalariados por Pagar (totIsrAsal)
        // 2.1.02.01.001 - Provisión Aguinaldo (totProvAguinaldo)
        // 2.1.02.02.001 - Provisión Bono 14 (totProvBono14)
        // 2.1.02.03.001 - Provisión Vacaciones (totProvVac)
        // 2.1.02.04.001 - Provisión Indemnización (totProvIndem)
        // 1.1.01.02.001 - Banco Industrial / Pago Sueldos (totNetoPagar)

        $stmtAcc = $pdo->prepare("SELECT id FROM contabilidad_cuentas WHERE id_empresa = ? AND codigo_cuenta = ?");
        
        $getIdAcc = function($code) use ($stmtAcc, $empresa_id) {
            $stmtAcc->execute([$empresa_id, $code]);
            return $stmtAcc->fetchColumn() ?: 1;
        };

        $totCuotasPatronales = $totIgssPat + $totIrtra + $totIntecap;
        $totPrestacionesGasto = $totProvAguinaldo + $totProvBono14 + $totProvVac + $totProvIndem;
        $totDebePartida = $totSalarios + $totBonif + $totCuotasPatronales + $totPrestacionesGasto;

        $correlativo = get_siguiente_correlativo_partida($empresa_id);
        $conceptoNom = "Planilla de Sueldos y Cuotas Patronales correspondiente a {$periodo_nomina}";

        $stmtPart = $pdo->prepare("INSERT INTO contabilidad_partidas (id_empresa, correlativo, fecha, tipo_partida, concepto, total_debe, total_haber) VALUES (?, ?, ?, 'Nómina', ?, ?, ?)");
        $stmtPart->execute([$empresa_id, $correlativo, $fecha_fin, $conceptoNom, $totDebePartida, $totDebePartida]);
        $partidaNomId = $pdo->lastInsertId();

        $stmtDet = $pdo->prepare("INSERT INTO contabilidad_partida_detalles (id_partida, id_cuenta, concepto_linea, debe, haber, orden) VALUES (?, ?, ?, ?, ?, ?)");
        
        // DEBITS
        $stmtDet->execute([$partidaNomId, $getIdAcc('6.1.01.01.001'), 'Sueldos y Salarios Ordinarios', $totSalarios, 0.00, 1]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('6.1.01.02.001'), 'Bonificación Incentivo Decreto 37-2001', $totBonif, 0.00, 2]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('6.1.01.03.001'), 'Cuotas Patronales IGSS (10.67%) + IRTRA + INTECAP', $totCuotasPatronales, 0.00, 3]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('6.1.04.01.001'), 'Provisión Prestaciones Laborales de Ley', $totPrestacionesGasto, 0.00, 4]);

        // CREDITS
        $totIgssPagar = $totIgssLab + $totCuotasPatronales;
        $stmtDet->execute([$partidaNomId, $getIdAcc('2.1.01.03.002'), 'IGSS Cuota Laboral y Patronal por Pagar', 0.00, $totIgssPagar, 5]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('2.1.01.03.003'), 'ISR Asalariados Retenido por Pagar', 0.00, $totIsrAsal, 6]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('2.1.02.01.001'), 'Provisión Aguinaldo (8.33%)', 0.00, $totProvAguinaldo, 7]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('2.1.02.02.001'), 'Provisión Bono 14 (8.33%)', 0.00, $totProvBono14, 8]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('2.1.02.03.001'), 'Provisión Vacaciones (4.11%)', 0.00, $totProvVac, 9]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('2.1.02.04.001'), 'Provisión Indemnización (8.33%)', 0.00, $totProvIndem, 10]);
        $stmtDet->execute([$partidaNomId, $getIdAcc('1.1.01.02.001'), 'Pago Sueldos Líquidos con Banco Industrial', 0.00, $totNetoPagar, 11]);

        // 2. Insert Header Nómina
        $stmtNom = $pdo->prepare("INSERT INTO contabilidad_nominas (id_empresa, periodo_nomina, anio, mes, fecha_inicio, fecha_fin, total_salarios, total_bonificaciones, total_igss_laboral, total_igss_patronal, total_irtra, total_intecap, total_isr_asalariados, total_neto_pagar, id_partida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtNom->execute([$empresa_id, $periodo_nomina, $anio, $mes, $fecha_inicio, $fecha_fin, $totSalarios, $totBonif, $totIgssLab, $totIgssPat, $totIrtra, $totIntecap, $totIsrAsal, $totNetoPagar, $partidaNomId]);
        $nominaId = $pdo->lastInsertId();

        // 3. Insert Details Nómina
        $stmtNomDet = $pdo->prepare("INSERT INTO contabilidad_nomina_detalles (id_nomina, id_empleado, salario_base, dias_trabajados, salario_devengado, bonificacion_incentivo, igss_laboral, igss_patronal, irtra_patronal, intecap_patronal, prov_aguinaldo, prov_bono14, prov_vacaciones, prov_indemnizacion, isr_asalariados, total_descuentos, liquido_recibir) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($detallesProcesados as $dp) {
            $stmtNomDet->execute([$nominaId, $dp['id_empleado'], $dp['salario_base'], $dp['dias_trabajados'], $dp['salario_devengado'], $dp['bonificacion_incentivo'], $dp['igss_laboral'], $dp['igss_patronal'], $dp['irtra_patronal'], $dp['intecap_patronal'], $dp['prov_aguinaldo'], $dp['prov_bono14'], $dp['prov_vacaciones'], $dp['prov_indemnizacion'], $dp['isr_asalariados'], $dp['total_descuentos'], $dp['liquido_recibir']]);
        }

        $pdo->commit();
        redirect_to("index.php?tab=nominas&created=1");
    }
}

// Fetch Employees
$stmtEmp = $pdo->prepare("SELECT * FROM contabilidad_empleados WHERE id_empresa = ? ORDER BY apellidos ASC");
$stmtEmp->execute([$empresa_id]);
$empleadosList = $stmtEmp->fetchAll();

// Fetch Nominas
$stmtNomList = $pdo->prepare("SELECT * FROM contabilidad_nominas WHERE id_empresa = ? ORDER BY id DESC");
$stmtNomList->execute([$empresa_id]);
$nominasList = $stmtNomList->fetchAll();

$tab = $_GET['tab'] ?? 'empleados';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Módulo Laboral y Planilla (Nómina Guatemala)</h1>
        <p class="page-subtitle">Descuentos de ley (IGSS 4.83%), Aportes Patronales (12.67%), Decreto 37-2001 y Provisiones</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" onclick="openModal('modalNuevoEmpleado')">
            + Nuevo Empleado
        </button>
        <button class="btn btn-success" onclick="openModal('modalProcesarNomina')">
            🚀 Procesar Planilla del Mes
        </button>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tabs">
    <a href="?tab=empleados" class="tab-btn <?= ($tab === 'empleados') ? 'active' : '' ?>">Colaboradores / Empleados</a>
    <a href="?tab=nominas" class="tab-btn <?= ($tab === 'nominas') ? 'active' : '' ?>">Planillas de Sueldos Procesadas</a>
</div>

<?php if ($tab === 'empleados'): ?>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código / DPI</th>
                    <th>Nombre Completo</th>
                    <th>Puesto</th>
                    <th>Fecha Ingreso</th>
                    <th class="text-right">Salario Base</th>
                    <th class="text-right">Bono Ley (Dec 37-2001)</th>
                    <th class="text-right">IGSS Lab (4.83%)</th>
                    <th class="text-right">Ret. ISR Asalariado</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleadosList as $emp): ?>
                    <?php 
                    $igss = calcular_igss_laboral($emp['salario_base']);
                    $isr = calcular_isr_asalariado_mensual($emp['salario_base'], $emp['bonificacion_ley']);
                    ?>
                    <tr>
                        <td>
                            <strong style="font-family:monospace; color:var(--primary);"><?= htmlspecialchars($emp['codigo']) ?></strong><br>
                            <small style="color:var(--text-muted);">DPI: <?= htmlspecialchars($emp['dpi']) ?></small>
                        </td>
                        <td><strong><?= htmlspecialchars($emp['apellidos'] . ', ' . $emp['nombres']) ?></strong></td>
                        <td><?= htmlspecialchars($emp['puesto']) ?></td>
                        <td><?= date('d/m/Y', strtotime($emp['fecha_ingreso'])) ?></td>
                        <td class="text-right"><strong><?= format_gtq($emp['salario_base']) ?></strong></td>
                        <td class="text-right"><?= format_gtq($emp['bonificacion_ley']) ?></td>
                        <td class="text-right" style="color:var(--danger);"><?= format_gtq($igss) ?></td>
                        <td class="text-right" style="color:var(--warning);"><?= format_gtq($isr) ?></td>
                        <td class="text-center"><span class="badge badge-success">Activo</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Período Planilla</th>
                    <th>Período Fechas</th>
                    <th class="text-right">Total Salarios</th>
                    <th class="text-right">IGSS Laboral</th>
                    <th class="text-right">Aportes Patronales (12.67%)</th>
                    <th class="text-right">ISR Asalariados</th>
                    <th class="text-right">Total Líquido a Pagar</th>
                    <th class="text-center">Partida Contable</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($nominasList)): ?>
                    <tr><td colspan="8" class="text-center" style="padding:25px; color:var(--text-muted);">No hay planillas procesadas aún.</td></tr>
                <?php else: ?>
                    <?php foreach ($nominasList as $nom): ?>
                        <?php $totPatronal = $nom['total_igss_patronal'] + $nom['total_irtra'] + $nom['total_intecap']; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($nom['periodo_nomina']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($nom['fecha_inicio'])) ?> al <?= date('d/m/Y', strtotime($nom['fecha_fin'])) ?></td>
                            <td class="text-right"><?= format_gtq($nom['total_salarios']) ?></td>
                            <td class="text-right" style="color:var(--danger);"><?= format_gtq($nom['total_igss_laboral']) ?></td>
                            <td class="text-right" style="color:var(--warning);"><?= format_gtq($totPatronal) ?></td>
                            <td class="text-right"><?= format_gtq($nom['total_isr_asalariados']) ?></td>
                            <td class="text-right" style="color:var(--success); font-weight:800; font-size:14px;"><?= format_gtq($nom['total_neto_pagar']) ?></td>
                            <td class="text-center"><span class="badge badge-success">Partida #<?= $nom['id_partida'] ?> Generada</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Nuevo Empleado -->
<div class="modal-backdrop" id="modalNuevoEmpleado">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Nuevo Colaborador</h3>
            <button class="close-modal" onclick="closeModal('modalNuevoEmpleado')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_create_empleado" value="1">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Código Empleado:</label>
                    <input type="text" name="codigo" class="form-control" value="EMP-00<?= count($empleadosList)+1 ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">DPI (CUI Guatemala 13 dígitos):</label>
                    <input type="text" name="dpi" class="form-control" placeholder="ej. 2415987450101" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Nombres:</label>
                    <input type="text" name="nombres" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Apellidos:</label>
                    <input type="text" name="apellidos" class="form-control" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">NIT:</label>
                    <input type="text" name="nit" class="form-control" placeholder="ej. 4455667-8">
                </div>
                <div class="form-group">
                    <label class="form-label">Puesto / Cargo:</label>
                    <input type="text" name="puesto" class="form-control" placeholder="ej. Auxiliar de Contabilidad" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha de Ingreso:</label>
                    <input type="date" name="fecha_ingreso" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Salario Base Mensual (GTQ):</label>
                    <input type="number" step="0.01" name="salario_base" class="form-control" placeholder="ej. 4500.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Bonificación Ley (Dec 37-2001):</label>
                    <input type="number" step="0.01" name="bonificacion_ley" class="form-control" value="250.00" required>
                </div>
            </div>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevoEmpleado')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Colaborador</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Procesar Nomina -->
<div class="modal-backdrop" id="modalProcesarNomina">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Procesar Planilla Mensual de Sueldos</h3>
            <button class="close-modal" onclick="closeModal('modalProcesarNomina')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action_procesar_nomina" value="1">
            
            <div class="form-group">
                <label class="form-label">Nombre del Período / Planilla:</label>
                <input type="text" name="periodo_nomina" class="form-control" value="Planilla Ordinaria Mes <?= date('m/Y') ?>" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Año:</label>
                    <input type="number" name="anio" class="form-control" value="<?= date('Y') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mes:</label>
                    <input type="number" name="mes" class="form-control" value="<?= date('m') ?>" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Fecha Inicio Período:</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-01') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha Fin Período:</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= date('Y-m-t') ?>" required>
                </div>
            </div>

            <p style="font-size:12px; color:var(--text-muted); background:#f8fafc; padding:12px; border-radius:6px; margin-top:10px;">
                💡 <strong>Generador de Asiento Automático:</strong> Al procesar la planilla, el sistema calculará IGSS Laboral (4.83%), IGSS Patronal (10.67%), IRTRA (1%), INTECAP (1%), Provisión Aguinaldo (8.33%), Bono 14 (8.33%), Vacaciones (4.11%), Indemnización (8.33%) y registrará la partida contable cuadrada en el Libro Diario.
            </p>

            <div style="display:flex; justify-content:end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalProcesarNomina')">Cancelar</button>
                <button type="submit" class="btn btn-success">Procesar & Generar Asiento Contable</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

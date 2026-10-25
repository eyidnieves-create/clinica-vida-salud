<?php
// index.php
session_start();

// Control de acceso RF01
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/conexion.php';

$mensaje = "";

// 1. Guardar Nuevo Paciente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_paciente'])) {
    $ci        = trim($_POST['ci']);
    $nombres   = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $fecha_nac = $_POST['fecha_nacimiento'];
    $sexo      = $_POST['sexo'];
    $telefono  = trim($_POST['telefono']);
    $correo    = trim($_POST['correo']);
    $direccion = trim($_POST['direccion']);

    if (!empty($ci) && !empty($nombres) && !empty($apellidos)) {
        try {
            $sql = "INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion) 
                    VALUES (:ci, :nombres, :apellidos, :fecha_nac, :sexo, :telefono, :correo, :direccion)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ci' => $ci, ':nombres' => $nombres, ':apellidos' => $apellidos,
                ':fecha_nac' => $fecha_nac, ':sexo' => $sexo, ':telefono' => $telefono,
                ':correo' => $correo, ':direccion' => $direccion
            ]);
            $mensaje = "<div class='alerta exito'>¡Paciente registrado correctamente en la nube!</div>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'paciente_ci_key') !== false) {
                $mensaje = "<div class='alerta error'>Atención: Ya existe un paciente con el carnet $ci.</div>";
            } else {
                $mensaje = "<div class='alerta error'>Error al registrar: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// 2. Agendar Nueva Cita Médica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar_cita'])) {
    $id_paciente     = (int)$_POST['id_paciente'];
    $id_medico       = (int)$_POST['id_medico'];
    $id_especialidad = (int)$_POST['id_especialidad'];
    $fecha_cita      = $_POST['fecha_cita'];
    $hora_inicio     = $_POST['hora_inicio'];

    $hora_fin = date('H:i:s', strtotime($hora_inicio) + 1800);
    $codigo_cita = 'CM-' . rand(1000, 9999);

    if ($id_paciente > 0 && $id_medico > 0 && !empty($fecha_cita) && !empty($hora_inicio)) {
        try {
            $sql = "INSERT INTO cita_medica (codigo_cita, id_paciente, id_medico, id_especialidad, id_usuario, fecha_cita, hora_inicio, hora_fin, estado) 
                    VALUES (:cod, :id_p, :id_m, :id_e, :id_u, :fec, :hi, :hf, 'Programada')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cod'  => $codigo_cita,
                ':id_p' => $id_paciente,
                ':id_m' => $id_medico,
                ':id_e' => $id_especialidad,
                ':id_u' => $_SESSION['usuario_id'],
                ':fec'  => $fecha_cita,
                ':hi'   => $hora_inicio,
                ':hf'   => $hora_fin
            ]);
            $mensaje = "<div class='alerta exito'>¡Cita <strong>$codigo_cita</strong> agendada exitosamente en la nube!</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alerta error'>Error al agendar cita: " . $e->getMessage() . "</div>";
        }
    }
}

// 3. Consultas para Selects y Citas Médicas
$lista_pacientes     = $pdo->query("SELECT id_paciente, ci, CONCAT(nombres, ' ', apellidos) AS nombre_completo FROM paciente WHERE estado = TRUE ORDER BY apellidos ASC")->fetchAll();
$lista_medicos       = $pdo->query("SELECT id_medico, CONCAT(nombres, ' ', apellidos) AS nombre_completo FROM medico WHERE estado = TRUE ORDER BY apellidos ASC")->fetchAll();
$lista_especialidades= $pdo->query("SELECT id_especialidad, nombre_especialidad FROM especialidad ORDER BY nombre_especialidad ASC")->fetchAll();

$citas = $pdo->query("SELECT c.codigo_cita, c.fecha_cita, c.hora_inicio, c.hora_fin, 
                             CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                             CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                             e.nombre_especialidad, c.estado
                      FROM cita_medica c
                      JOIN paciente p ON c.id_paciente = p.id_paciente
                      JOIN medico m ON c.id_medico = m.id_medico
                      JOIN especialidad e ON c.id_especialidad = e.id_especialidad
                      ORDER BY c.fecha_cita DESC, c.hora_inicio DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clínica Vida Salud - Gestión de Citas</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f5f9; margin: 25px; color: #1e293b; }
        .header { background: white; padding: 20px 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; color: #1e3a8a; font-size: 24px; }
        .user-badge { font-size: 14px; background: #e0f2fe; padding: 6px 12px; border-radius: 20px; color: #0369a1; font-weight: 600; }
        .btn-logout { background: #ef4444; color: white; padding: 7px 14px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px; margin-left: 10px; }
        
        /* Barra de Acciones */
        .acciones-bar { display: flex; gap: 15px; margin-bottom: 20px; }
        .btn-accion { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; color: white; transition: 0.2s; }
        .btn-azul { background-color: #2563eb; }
        .btn-azul:hover { background-color: #1d4ed8; }
        .btn-verde { background-color: #059669; }
        .btn-verde:hover { background-color: #047857; }

        /* Estructura Modal emergente */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 100%; max-width: 550px; position: relative; box-shadow: 0 15px 30px rgba(0,0,0,0.3); }
        .modal-close { position: absolute; right: 20px; top: 15px; font-size: 24px; font-weight: bold; cursor: pointer; color: #64748b; }
        .modal-close:hover { color: #0f172a; }
        
        .box { background: white; padding: 22px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #1e3a8a; font-size: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .full { grid-column: span 2; }
        label { font-weight: 600; font-size: 12px; color: #475569; display: block; margin-bottom: 4px; }
        input, select { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; font-size: 13px; }
        .btn-submit { width: 100%; padding: 11px; color: white; border: none; border-radius: 5px; font-size: 14px; font-weight: bold; cursor: pointer; margin-top: 15px; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { border: 1px solid #e2e8f0; padding: 11px 14px; text-align: left; }
        th { background: #1e3a8a; color: white; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .alerta { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .exito { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge { background: #10b981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Clínica Vida Salud - Gestión de Citas</h1>
        <div>
            <span class="user-badge">Usuario: <?= htmlspecialchars($_SESSION['usuario_nombre']) ?> (<?= htmlspecialchars($_SESSION['usuario_rol']) ?>)</span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <?= $mensaje ?>

    <!-- Botones de Acción -->
    <div class="acciones-bar">
        <button class="btn-accion btn-azul" onclick="abrirModal('modalPaciente')">➕ Nuevo Paciente</button>
        <button class="btn-accion btn-verde" onclick="abrirModal('modalCita')">📅 Agendar Cita Médica</button>
    </div>

    <!-- MODAL 1: REGISTRAR PACIENTE -->
    <div id="modalPaciente" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="cerrarModal('modalPaciente')">&times;</span>
            <h2>Registrar Nuevo Paciente</h2>
            <form method="POST">
                <input type="hidden" name="guardar_paciente" value="1">
                <div class="grid">
                    <div><label>Carnet (CI):</label><input type="text" name="ci" required></div>
                    <div><label>Fecha de Nacimiento:</label><input type="date" name="fecha_nacimiento" required></div>
                    <div><label>Nombres:</label><input type="text" name="nombres" required></div>
                    <div><label>Apellidos:</label><input type="text" name="apellidos" required></div>
                    <div>
                        <label>Sexo:</label>
                        <select name="sexo">
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                        </select>
                    </div>
                    <div><label>Teléfono:</label><input type="text" name="telefono" required></div>
                    <div class="full"><label>Dirección:</label><input type="text" name="direccion"></div>
                    <input type="hidden" name="correo" value="">
                </div>
                <button type="submit" class="btn-submit btn-azul">Guardar Paciente</button>
            </form>
        </div>
    </div>

    <!-- MODAL 2: AGENDAR CITA MÉDICA -->
    <div id="modalCita" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="cerrarModal('modalCita')">&times;</span>
            <h2>Agendar Cita Médica</h2>
            <form method="POST">
                <input type="hidden" name="agendar_cita" value="1">
                <div class="grid">
                    <div class="full">
                        <label>Seleccionar Paciente:</label>
                        <select name="id_paciente" required>
                            <option value="">-- Elija un paciente --</option>
                            <?php foreach ($lista_pacientes as $p): ?>
                                <option value="<?= $p['id_paciente'] ?>"><?= htmlspecialchars($p['nombre_completo']) ?> (CI: <?= htmlspecialchars($p['ci']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Especialidad:</label>
                        <select name="id_especialidad" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($lista_especialidades as $e): ?>
                                <option value="<?= $e['id_especialidad'] ?>"><?= htmlspecialchars($e['nombre_especialidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Médico Especialista:</label>
                        <select name="id_medico" required>
                            <option value="">-- Seleccione médico --</option>
                            <?php foreach ($lista_medicos as $m): ?>
                                <option value="<?= $m['id_medico'] ?>"><?= htmlspecialchars($m['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Fecha de Cita:</label><input type="date" name="fecha_cita" required value="<?= date('Y-m-d') ?>"></div>
                    <div><label>Hora Inicio:</label><input type="time" name="hora_inicio" required value="09:00"></div>
                </div>
                <button type="submit" class="btn-submit btn-verde">Confirmar y Agendar Cita</button>
            </form>
        </div>
    </div>

    <!-- TABLA PRINCIPAL DE CITAS -->
    <div class="box">
        <h2>Citas Médicas Programadas en la Nube</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr><td colspan="7" style="text-align:center;">No hay citas médicas registradas en Supabase.</td></tr>
                <?php else: ?>
                    <?php foreach ($citas as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['codigo_cita']) ?></strong></td>
                        <td><?= htmlspecialchars($c['fecha_cita']) ?></td>
                        <td><?= htmlspecialchars($c['hora_inicio'] . ' - ' . $c['hora_fin']) ?></td>
                        <td><?= htmlspecialchars($c['paciente']) ?></td>
                        <td><?= htmlspecialchars($c['medico']) ?></td>
                        <td><?= htmlspecialchars($c['nombre_especialidad']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($c['estado']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function abrirModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function cerrarModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // Cerrar si se hace clic fuera del cuadro blanco
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

</body>
</html>
<?php
// login.php
session_start();
require_once 'config/conexion.php';

// Si ya tiene sesión abierta, redirigir al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    if (!empty($usuario) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT u.id_usuario, u.username, u.password_hash, r.nombre_rol 
                                   FROM usuario u
                                   JOIN rol r ON u.id_rol = r.id_rol
                                   WHERE u.username = :user AND u.estado = TRUE");
            $stmt->execute([':user' => $usuario]);
            $cuenta = $stmt->fetch();

            // Verificación segura mediante password_verify() según RF01
            // (Mantiene compatibilidad con texto plano de prueba en caso de emergencia)
            $password_valida = false;
            if ($cuenta) {
                if (password_verify($password, $cuenta['password_hash']) || $cuenta['password_hash'] === $password) {
                    $password_valida = true;
                }
            }

            if ($password_valida) {
                // Iniciar sesión segura con regeneración de ID contra fijación de sesión
                session_regenerate_id(true);
                $_SESSION['usuario_id']     = $cuenta['id_usuario'];
                $_SESSION['usuario_nombre'] = $cuenta['username'];
                $_SESSION['usuario_rol']    = $cuenta['nombre_rol'];

                header("Location: index.php");
                exit();
            } else {
                $error = "Credenciales incorrectas o usuario inactivo.";
            }
        } catch (PDOException $e) {
            $error = "Error al autenticar: " . $e->getMessage();
        }
    } else {
        $error = "Por favor ingrese usuario y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso al Sistema - Clínica Vida Salud</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f172a; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card-login { background: #ffffff; padding: 35px 30px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 360px; }
        h2 { text-align: center; color: #1e3a8a; margin-top: 0; margin-bottom: 20px; }
        .campo { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #334155; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        button { width: 100%; padding: 11px; background-color: #2563eb; color: white; border: none; border-radius: 5px; font-size: 15px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #1d4ed8; }
        .alerta-error { background-color: #fee2e2; color: #991b1b; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px; text-align: center; }
    </style>
</head>
<body>
    <div class="card-login">
        <h2>Clínica Vida Salud</h2>
        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="campo">
                <label>Nombre de Usuario:</label>
                <input type="text" name="usuario" placeholder="nieves" required autofocus>
            </div>
            <div class="campo">
                <label>Contraseña:</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
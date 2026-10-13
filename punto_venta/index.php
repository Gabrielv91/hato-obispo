<?php
session_start();

// Si el usuario ya tiene una sesión abierta, lo mandamos directo al menú principal
if (isset($_SESSION['usuario_id'])) {
    header("Location: menu.php");
    exit;
}

require_once 'conexion.php';
$error = '';

// Lógica de autenticación intacta y segura
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $clave = trim($_POST['clave']);

    if (!empty($usuario) && !empty($clave)) {
        $usuario_esc = $conexion->real_escape_string($usuario);
        $query = "SELECT * FROM usuarios WHERE usuario = '$usuario_esc' LIMIT 1";
        $resultado = $conexion->query($query);

        if ($resultado && $resultado->num_rows > 0) {
            $user = $resultado->fetch_assoc();
            
            // Verificación de credenciales
            if ($clave === $user['clave']) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_rol'] = $user['rol'];
                
                header("Location: menu.php");
                exit;
            } else {
                $error = '❌ Contraseña incorrecta. Inténtalo de nuevo.';
            }
        } else {
            $error = '❌ El usuario ingresado no existe.';
        }
    } else {
        $error = '⚠️ Por favor, complete ambos campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="manifest" href="manifest.json">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Título de pestaña adaptado a la Agrotienda -->
    <title>Agrotienda El Hato - Acceso al Sistema</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        .cuerpo-login {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .tarjeta-autenticacion {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            border-top: 6px solid #12269a; /* Color Terracota cálido del logo */
            width: 100%;
            max-width: 420px; 
            box-sizing: border-box;
            text-align: center;
        }
        .contenedor-logo {
            margin-bottom: 1.2rem;
        }
        /* Estilo para que el logo del jinete y el toro resalte perfecto */
        .logo-empresa {
            width: 100%;
            max-width: 200px; 
            height: auto;
            object-fit: contain;
            margin: 0 auto;
            display: block;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15));
        }
        .encabezado-bienvenida {
            margin-bottom: 1.8rem;
        }
        .encabezado-bienvenida h3 {
            margin: 0.5rem 0 0.2rem 0;
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 800;
        }
        .encabezado-bienvenida p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .subtitulo-agro {
            font-size: 0.75rem;
            font-weight: bold;
            color: #0914b4;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }
        .alerta-error {
            background: #fee2e2;
            color: #ef4444;
            padding: 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            border: 1px solid #fca5a5;
            text-align: left;
        }
        .bloque-formulario {
            text-align: left;
            margin-bottom: 1.2rem;
        }
        .bloque-formulario label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.5rem;
        }
        .campo-entrada {
            width: 100%;
            box-sizing: border-box;
            padding: 0.8rem 1rem;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 8px;
            font-size: 16px; 
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
        }
        .campo-entrada:focus {
            outline: none;
            background-color: white;
            border-color: #15803d; /* Verde agro al enfocar el input */
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }
        .btn-ingresar {
            width: 100%;
            background: #9a3412; /* Terracota oscuro estilo Agrotienda */
            color: white;
            border: none;
            padding: 1rem;
            font-size: 1.05rem;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 0.5rem;
        }
        .btn-ingresar:hover {
            background: #7c2d12;
        }
        .btn-ingresar:active {
            transform: scale(0.98);
        }
        .pie-login {
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }
    </style>
</head>
<body class="cuerpo-login">

    <div class="tarjeta-autenticacion">
        
        <div class="contenedor-logo">
            <!-- AQUÍ CARGA EL LOGO DE AGROTIENDA EL HATO -->
            <!-- Asegúrate de guardar la imagen en la carpeta img/ como logo.png o logo.jpg -->
            <img src="img/logo.png" onerror="this.src='img/logo.jpg'" alt="Agrotienda El Hato" class="logo-empresa">
            <div class="subtitulo-agro">MEDICINAS • ALIMENTOS • INSUMOS</div>
        </div>

        <div class="encabezado-bienvenida">
            <h3>¡Bienvenido al Hato Obispo!</h3>
            <p>Ingresa tus credenciales para iniciar el turno.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alerta-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" autocomplete="off">
            <div class="bloque-formulario">
                <label for="usuario">Nombre de Usuario</label>
                <input type="text" name="usuario" id="usuario" class="campo-entrada" required placeholder="Ej: admin" autofocus>
            </div>

            <div class="bloque-formulario">
                <label for="clave">Contraseña de Seguridad</label>
                <input type="password" name="clave" id="clave" class="campo-entrada" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-ingresar">Iniciar Sesión</button>
        </form>

        <div class="pie-login">
            LiaPOS &copy; <?php echo date('Y'); ?> - Sistema Inteligente de Ventas
        </div>
    </div>

</body>
</html>
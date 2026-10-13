<?php
    // 1. SEGURIDAD DE SESIÓN Y ROLES
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] != 'admin' && $_SESSION['usuario_rol'] != 'almacenista')) {
        header("Location: index.php");
        exit;
    }

    require_once 'conexion.php';
    $mensaje = '';

    // =========================================================================
    // 2. AUTO-PARCHE DE BASE DE DATOS (TABLA INDEPENDIENTE)
    // =========================================================================
    $conexion->query("CREATE TABLE IF NOT EXISTS equipos_herramientas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) NOT NULL UNIQUE,
        nombre VARCHAR(150) NOT NULL,
        ubicacion VARCHAR(150) NOT NULL,
        estado ENUM('Disponible', 'Prestado') DEFAULT 'Disponible',
        prestado_a VARCHAR(100) DEFAULT NULL,
        fecha_prestamo DATE DEFAULT NULL,
        fecha_devolucion DATE DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // =========================================================================
    // 3. PROCESAR ACCIONES (GUARDAR, PRESTAR, DEVOLVER, ELIMINAR)
    // =========================================================================
    if (isset($_POST['guardar_equipo'])) {
        $codigo = $conexion->real_escape_string(trim($_POST['codigo']));
        $nombre = $conexion->real_escape_string(trim($_POST['nombre']));
        $ubicacion = $conexion->real_escape_string(trim($_POST['ubicacion']));
        
        $q_ins = "INSERT INTO equipos_herramientas (codigo, nombre, ubicacion) VALUES ('$codigo', '$nombre', '$ubicacion')";
        if ($conexion->query($q_ins)) {
            $mensaje = "✅ Herramienta/Equipo registrado con éxito.";
        } else {
            $mensaje = "❌ Error: Ya existe un equipo con ese código.";
        }
    }

    if (isset($_POST['prestar_equipo'])) {
        $id_eq = intval($_POST['equipo_id']);
        $prestado_a = $conexion->real_escape_string(trim($_POST['prestado_a']));
        $fecha_prestamo = date('Y-m-d');
        
        if (!empty($prestado_a)) {
            $conexion->query("UPDATE equipos_herramientas SET estado='Prestado', prestado_a='$prestado_a', fecha_prestamo='$fecha_prestamo', fecha_devolucion=NULL WHERE id=$id_eq");
            $mensaje = "📤 Equipo entregado en calidad de préstamo a $prestado_a.";
        }
    }

    if (isset($_GET['devolver_equipo'])) {
        $id_eq = intval($_GET['devolver_equipo']);
        $fecha_devolucion = date('Y-m-d');
        $conexion->query("UPDATE equipos_herramientas SET estado='Disponible', fecha_devolucion='$fecha_devolucion' WHERE id=$id_eq");
        header("Location: herramientas.php?msg=devuelto");
        exit;
    }
    
    if (isset($_POST['eliminar_equipo'])) {
        $id_eq = intval($_POST['equipo_id_eliminar']);
        $conexion->query("DELETE FROM equipos_herramientas WHERE id=$id_eq");
        $mensaje = "🗑️ Registro de equipo eliminado.";
    }

    if (isset($_GET['msg']) && $_GET['msg'] == 'devuelto') {
        $mensaje = "📥 Equipo recibido y devuelto al almacén.";
    }

    // =========================================================================
    // 4. CONSULTA PARA MOSTRAR LOS DATOS
    // =========================================================================
    $lista_equipos = [];
    $resultado_equipos = $conexion->query("SELECT * FROM equipos_herramientas ORDER BY nombre ASC");
    if ($resultado_equipos) {
        while($eq = $resultado_equipos->fetch_assoc()) {
            $lista_equipos[] = $eq;
        }
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Herramientas y Equipos</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        .btn-accion { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.4rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; transition: 0.2s; }
        .btn-accion:hover { background: #e2e8f0; }
        
        /* Ocultar encabezado de impresión en pantalla normal */
        .header-impresion { display: none; }

        @media print {
            @page { margin: 1cm; }
            body { background: white !important; padding: 0 !important; font-family: Arial, sans-serif !important; }
            
            /* Ocultamos menú, botones, form y la barra de búsqueda en el papel */
            .top-bar, .ocultar-impresion, form { display: none !important; }
            
            /* ROMPEMOS EL GRID para que la tabla use el 100% de la hoja */
            .contenedor-menu { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .grid-principal { display: block !important; width: 100% !important; }
            
            /* Mostramos el encabezado bonito solo en impresión */
            .header-impresion { display: block !important; text-align: center; margin-bottom: 20px; }
            .header-impresion h1 { font-size: 24px; margin: 0; color: #000; text-transform: uppercase; }
            .header-impresion p { font-size: 14px; margin: 5px 0 0 0; color: #333; }
            
            /* Quitamos bordes curvos y sombras a la caja de la tabla */
            .panel-tabla { box-shadow: none !important; border: none !important; border-radius: 0 !important; }
            .titulo-pantalla { display: none !important; } 
            
            /* Estilizamos la tabla para reporte físico */
            table { border-collapse: collapse !important; width: 100% !important; margin: 0 !important; }
            th { background-color: #f1f5f9 !important; color: #000 !important; border: 1px solid #000 !important; padding: 10px !important; font-size: 14px !important; text-align: center !important; }
            td { border: 1px solid #000 !important; padding: 8px !important; font-size: 13px !important; color: #000 !important; }
            
            /* Forzar que se impriman los colores de los cuadritos de Disponible/Prestado */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body style="background-color: #f1f5f9;">
    <header class="top-bar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="menu.php" style="text-decoration: none; font-size: 1.5rem; color: var(--texto-principal);" title="Volver al Menú">⬅️</a>
            <h2>Control de Herramientas y Equipos Internos</h2>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span style="color: var(--texto-secundario);">Usuario: <strong style="color: var(--primario);"><?php echo $_SESSION['usuario_nombre']; ?></strong></span>
            <span style="background: #e2e8f0; color: #334155; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.8rem;"><?php echo strtoupper($_SESSION['usuario_rol']); ?></span>
        </div>
    </header>

    <main class="contenedor-menu" style="max-width: 1400px; padding: 2rem; box-sizing: border-box;">
        
        <!-- Encabezado exclusivo para la hoja impresa -->
        <div class="header-impresion">
            <h1>AgroTienda el Hato</h1>
            <p><b>Reporte de Control de Herramientas y Equipos Internos</b></p>
            <p style="font-size: 12px; color: #666;">Fecha de impresión: <?php echo date('d/m/Y h:i A'); ?></p>
        </div>

        <?php if($mensaje): ?>
            <div class="ocultar-impresion" style="background: #eff6ff; color: #1e40af; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: bold; border-left: 4px solid #3b82f6;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="ocultar-impresion" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <p style="color: #64748b; font-size: 0.95rem;">Módulo de control de activos de la empresa. Estos equipos <b>no aparecen en el módulo de ventas ni suman capital comercial</b>.</p>
            <button onclick="window.print()" class="btn-login" style="width: auto; margin: 0; padding: 0.8rem 1.5rem; background-color: #64748b; color: white; font-weight: bold;">🖨️ Imprimir Inventario</button>
        </div>

        <div class="grid-principal" style="display: grid; grid-template-columns: 1fr 3fr; gap: 2rem; align-items: start;">
            
            <!-- FORMULARIO DE REGISTRO (Izquierda) -->
            <div class="ocultar-impresion" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-top: 5px solid #475569;">
                <h3 style="margin-bottom: 0.5rem; color: #334155;">🛠️ Registrar Activo</h3>
                <form method="POST" action="herramientas.php">
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: bold; font-size: 0.85rem;">Código del Equipo:</label>
                        <input type="text" name="codigo" required class="input-cliente" style="width: 100%; padding: 0.8rem; box-sizing: border-box;" placeholder="Ej: HER-001">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: bold; font-size: 0.85rem;">Nombre / Descripción:</label>
                        <input type="text" name="nombre" required class="input-cliente" style="width: 100%; padding: 0.8rem; box-sizing: border-box;" placeholder="Ej: Esmeril Angular Bosch">
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="font-weight: bold; font-size: 0.85rem;">Ubicación Fija:</label>
                        <input type="text" name="ubicacion" required class="input-cliente" style="width: 100%; padding: 0.8rem; box-sizing: border-box;" placeholder="Ej: Depósito Principal">
                    </div>
                    <button type="submit" name="guardar_equipo" class="btn-login" style="background: #475569; width: 100%; margin: 0; padding: 0.8rem; font-weight:bold; color:white;">Guardar Equipo</button>
                </form>
            </div>

            <!-- TABLA DE CONTROL (Derecha) -->
            <div class="panel-tabla" style="background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden;">
                
                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 1.5rem;">
                    <h3 class="titulo-pantalla" style="margin: 0; color: #1e293b;">📋 Listado y Control de Préstamos</h3>
                </div>

                <!-- BARRA DE BÚSQUEDA INTEGRADA -->
                <div style="padding: 1rem 1.5rem; background: white; border-bottom: 1px solid #e2e8f0;" class="ocultar-impresion">
                    <input type="text" id="buscador-herramientas" placeholder="🔍 Buscar por código, nombre de herramienta o ubicación..." style="width: 100%; padding: 0.8rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.95rem;">
                </div>
                
                <table style="width: 100%; border-collapse: collapse; text-align: left;" id="tabla-herramientas">
                    <thead>
                        <tr>
                            <th style="padding: 1rem; background: white; border-bottom: 1px solid #e2e8f0;">Código</th>
                            <th style="padding: 1rem; background: white; border-bottom: 1px solid #e2e8f0;">Herramienta / Maquinaria</th>
                            <th style="padding: 1rem; background: white; border-bottom: 1px solid #e2e8f0;">Ubicación</th>
                            <th style="padding: 1rem; background: white; border-bottom: 1px solid #e2e8f0; text-align: center;">Estado</th>
                            <th style="padding: 1rem; background: white; border-bottom: 1px solid #e2e8f0;">Detalles de Préstamo</th>
                            <th style="padding: 1rem; background: white; border-bottom: 1px solid #e2e8f0; text-align: center;" class="ocultar-impresion">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($lista_equipos) > 0): ?>
                            <?php foreach ($lista_equipos as $eq): ?>
                                <tr class="fila-equipo" style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 1rem; font-family: monospace; color: #475569;"><?php echo htmlspecialchars($eq['codigo']); ?></td>
                                    <td style="padding: 1rem; font-weight: bold; color: #1e293b;"><?php echo htmlspecialchars($eq['nombre']); ?></td>
                                    <td style="padding: 1rem; color: #475569;"><?php echo htmlspecialchars($eq['ubicacion']); ?></td>
                                    
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php if($eq['estado'] == 'Disponible'): ?>
                                            <span style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-weight:bold; font-size:0.8rem;">Disponible</span>
                                        <?php else: ?>
                                            <span style="background: #fee2e2; color: #b91c1c; padding: 4px 8px; border-radius: 4px; font-weight:bold; font-size:0.8rem;">Prestado</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="padding: 1rem; font-size: 0.85rem; color: #64748b;">
                                        <?php if($eq['estado'] == 'Prestado'): ?>
                                            Responsable: <b style="color: #b91c1c;"><?php echo htmlspecialchars($eq['prestado_a']); ?></b><br>
                                            Salida: <?php echo date('d/m/Y', strtotime($eq['fecha_prestamo'])); ?>
                                        <?php elseif(!empty($eq['fecha_devolucion'])): ?>
                                            Últ. Dev: <?php echo date('d/m/Y', strtotime($eq['fecha_devolucion'])); ?>
                                        <?php else: ?>
                                            <i>Sin movimientos</i>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="padding: 1rem; vertical-align: middle; text-align: center;" class="ocultar-impresion">
                                        <?php if($eq['estado'] == 'Disponible'): ?>
                                            
                                            <!-- Formulario con alto fijo (height: 34px) para que el Input y el Botón midan exactamente lo mismo -->
                                            <form method="POST" action="herramientas.php" style="display: flex; justify-content: center; align-items: stretch; gap: 5px; height: 34px; margin-bottom: 8px;">
                                                <input type="hidden" name="equipo_id" value="<?php echo $eq['id']; ?>">
                                                
                                                <input type="text" name="prestado_a" placeholder="Entregado a..." required style="margin: 0; padding: 0 8px; width: 110px; font-size: 0.85rem; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; height: 100%; outline: none;">
                                                
                                                <button type="submit" name="prestar_equipo" style="margin: 0; padding: 0 12px; background: #f59e0b; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; box-sizing: border-box; height: 100%; font-size: 0.85rem;">Prestar</button>
                                            </form>
                                            
                                            <form method="POST" action="herramientas.php" style="margin: 0;" onsubmit="return confirm('¿Seguro que deseas eliminar esta herramienta del sistema?');">
                                                <input type="hidden" name="equipo_id_eliminar" value="<?php echo $eq['id']; ?>">
                                                <button type="submit" name="eliminar_equipo" style="background: none; border: none; color: #ef4444; font-size: 0.8rem; cursor: pointer; text-decoration: underline; padding: 0;">Eliminar</button>
                                            </form>
                                            
                                        <?php else: ?>
                                            
                                            <a href="herramientas.php?devolver_equipo=<?php echo $eq['id']; ?>" style="display: inline-flex; align-items: center; justify-content: center; background: #10b981; color: white; font-weight: bold; text-decoration: none; border-radius: 4px; height: 34px; padding: 0 16px; font-size: 0.85rem; margin-bottom: 8px;">📥 Devolver</a>
                                            
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="padding: 2rem; text-align: center; color: #64748b;">No hay herramientas ni equipos registrados en el sistema.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- SCRIPT PARA EL BUSCADOR EN TIEMPO REAL -->
    <script>
        document.getElementById('buscador-herramientas').addEventListener('input', function() {
            let filtro = this.value.toLowerCase().trim();
            let filas = document.querySelectorAll('.fila-equipo');
            
            filas.forEach(function(fila) {
                // Obtenemos el texto de toda la fila para buscar coincidencias
                let textoFila = fila.innerText.toLowerCase();
                
                if (textoFila.includes(filtro)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
<?php
// 1. SEGURIDAD 
session_start();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] != 'admin' && $_SESSION['usuario_rol'] != 'vendedor')) {
    header("Location: index.php");
    exit; 
}

require_once 'conexion.php'; 

$mensaje = '';
$tipo_alerta = '';

// --- LÓGICA DE ELIMINACIÓN ---
if (isset($_GET['eliminar_id'])) {
    $id_eliminar = intval($_GET['eliminar_id']);
    
    $stmt_check = $conexion->prepare("SELECT SUM(deuda_usd) as total_deuda, COUNT(id) as total_ventas FROM ventas WHERE cliente_id = ?");
    $stmt_check->bind_param("i", $id_eliminar);
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    $total_deuda = floatval($resultado_check['total_deuda']);
    $total_ventas = intval($resultado_check['total_ventas']);

    if ($total_deuda > 0) {
        $mensaje = "⚠️ No se puede eliminar: El cliente tiene una deuda activa de $" . number_format($total_deuda, 2);
        $tipo_alerta = "error";
    } elseif ($total_ventas > 0) {
        $mensaje = "⚠️ No se puede eliminar: Este cliente tiene historial de compras. Borrarlo dañaría tus reportes de ventas.";
        $tipo_alerta = "error";
    } else {
        $query_delete = "DELETE FROM clientes WHERE id = $id_eliminar";
        if ($conexion->query($query_delete)) {
            $mensaje = "Cliente eliminado correctamente.";
            $tipo_alerta = "exito";
        } else {
            $mensaje = "Error técnico al eliminar: " . $conexion->error;
            $tipo_alerta = "error";
        }
    }
}

// --- LÓGICA DE GUARDAR ---
if (isset($_POST['guardar_cliente'])) {
    $cedula = $conexion->real_escape_string($_POST['cedula']);
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $telefono = $conexion->real_escape_string($_POST['telefono']);
    $id_cliente = $_POST['id_cliente']; 

    if (empty($id_cliente)) {
        $query_save = "INSERT INTO clientes (cedula, nombre, telefono) VALUES ('$cedula', '$nombre', '$telefono')";
        $accion_texto = "registrado";
    } else {
        $id_cliente = intval($id_cliente); 
        $query_save = "UPDATE clientes SET cedula = '$cedula', nombre = '$nombre', telefono = '$telefono' WHERE id = $id_cliente";
        $accion_texto = "actualizado";
    }

    if ($conexion->query($query_save)) {
        $mensaje = "Cliente $accion_texto correctamente.";
        $tipo_alerta = "exito";
    } else {
        $mensaje = "Error al intentar guardar el cliente: " . $conexion->error;
        $tipo_alerta = "error";
    }
}

$query_clientes = "SELECT * FROM clientes ORDER BY nombre ASC";
$resultado_clientes = $conexion->query($query_clientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Mi Negocio POS</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        /* =========================================
           1. FORZAR SCROLL EN TODA LA PÁGINA
           ========================================= */
        html, body {
            overflow-y: auto !important;
            height: auto !important;
        }

        .area-mensajes { margin-bottom: 20px; text-align: center; width: 100%; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .alerta-exito { background-color: #d1fae5; color: #065f46; padding: 10px; border-radius: 5px; border: 1px solid #059669; }
        .alerta-error { background-color: #fee2e2; color: #991b1b; padding: 10px; border-radius: 5px; border: 1px solid #dc2626; }
        
        .tabla-clientes button, .tabla-clientes a.btn-accion { 
            background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 5px; text-decoration: none; display: inline-block;
        }
        .tabla-clientes a.btn-eliminar { color: #ef4444; }
        .tabla-clientes a.btn-eliminar:hover { color: #dc2626; }
        .tabla-clientes button.btn-editar { color: #3b82f6; }
        .tabla-clientes button.btn-editar:hover { color: #2563eb; }

        .seccion-promocion { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; margin-bottom: 25px; width: 100%; box-sizing: border-box; }
        .textarea-promo { width: 100%; min-height: 70px; padding: 10px; border-radius: 5px; border: 1px solid #cbd5e1; box-sizing: border-box; resize: vertical; font-family: sans-serif; font-size: 0.95rem; }
        .btn-marketing { padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: background 0.2s; }
        .btn-wa { background-color: #25d366; color: white; }
        .btn-wa:hover { background-color: #1ebd59; }
        .btn-sms { background-color: #3b82f6; color: white; }
        .btn-sms:hover { background-color: #1d4ed8; }

        /* =========================================
           2. CLASE ESTRICTA PARA LA TABLA
           ========================================= */
        .caja-scroll {
            display: block !important;
            max-height: 400px !important; /* Puedes aumentar este número si quieres la tabla más larga */
            overflow-y: scroll !important; /* 'scroll' en lugar de 'auto' OBLIGA a dibujar la barra */
            width: 100%;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        /* =========================================
           3. OBLIGAR A PINTAR LA BARRA (Anti-bloqueos)
           ========================================= */
        .caja-scroll::-webkit-scrollbar {
            width: 10px !important;
            display: block !important;
        }
        .caja-scroll::-webkit-scrollbar-track {
            background: #f1f5f9 !important; 
            border-radius: 8px !important;
        }
        .caja-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1 !important; 
            border-radius: 8px !important;
        }
        .caja-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8 !important; 
        }

        .caja-scroll table {
            width: 100%;
            border-collapse: collapse;
        }

        .caja-scroll thead th {
            position: sticky;
            top: 0; 
            background-color: #f8fafc;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            padding: 15px;
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
        }
    </style>
</head>
<body style="background-color: #f8fafc; font-family: sans-serif;">
    
    <header class="top-bar" style="display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="menu.php" style="text-decoration: none; font-size: 1.5rem; color: var(--texto-principal);" title="Volver al Menú Principal">⬅️</a>
            <h1>Directorio de Clientes</h1>
        </div>
        <div style="font-size: 0.95rem; color: var(--texto-secundario);">
            Usuario: <strong style="color: var(--primario);"><?php echo $_SESSION['usuario_nombre']; ?></strong>
        </div>
    </header>

    <main class="contenedor-principal" style="padding: 20px; max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; align-items: center;">
        
        <?php if (!empty($mensaje)): ?>
            <div class="area-mensajes">
                <div class="alerta-<?php echo $tipo_alerta; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="seccion-promocion">
            <h3 style="margin-top: 0; margin-bottom: 8px; color: #1e3a8a;">📢 Panel de Mensajes Promocionales / Marketing</h3>
            <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: #475569;">Escribe tu mensaje aquí. Luego, haz clic en el botón de WhatsApp o SMS en la fila del cliente al que deseas enviárselo.</p>
            <textarea id="texto_promocional" class="textarea-promo" placeholder="Ej: ¡Hola! Te informamos que tenemos una promoción especial del 20% de descuento en toda la tienda solo por esta semana. ¡No te lo pierdas!"></textarea>
        </section>

        <div style="display: flex; gap: 30px; width: 100%; align-items: flex-start; flex-wrap: wrap;">
            
            <!-- PANEL IZQUIERDO: FORMULARIO -->
            <section style="background: white; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1; min-width: 300px; max-width: 350px;">
                <h2 style="color: #1e3a8a; margin-top: 0; margin-bottom: 20px;" id="titulo-formulario">Registrar / Editar Cliente</h2>
                
                <form action="clientes.php" method="POST" id="form-cliente">
                    <input type="hidden" name="id_cliente" id="id_cliente_input" value="">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 5px; font-weight: bold;">Cédula o RIF</label>
                        <input type="text" name="cedula" id="cedula_input" placeholder="Ej: V-12345678" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #e2e8f0; background: #f8fafc;" required>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 5px; font-weight: bold;">Nombre Completo</label>
                        <input type="text" name="nombre" id="nombre_input" placeholder="Ej: Juan Pérez" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #e2e8f0; background: #f8fafc;" required>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 5px; font-weight: bold;">WhatsApp / Celular</label>
                        <input type="text" name="telefono" id="telefono_input" placeholder="Ej: 04141234567" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #e2e8f0; background: #f8fafc;">
                    </div>
                    
                    <div style="display: flex; gap: 10px; flex-direction: column;">
                        <button type="submit" name="guardar_cliente" style="background-color: #10b981; color: white; border: none; padding: 12px; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1rem; width: 100%;">
                            Guardar Cliente
                        </button>
                        
                        <button type="button" id="btn-cancelar-edicion" onclick="resetFormulario()" style="background-color: #fee2e2; color: #dc2626; border: 1px solid #dc2626; padding: 10px; border-radius: 5px; font-weight: bold; cursor: pointer; display: none;">
                            Cancelar Edición
                        </button>
                    </div>
                </form>
            </section>

            <!-- PANEL DERECHO: TABLA CON LA CLASE DE SCROLL APLICADA DIRECTAMENTE -->
            <section style="flex: 2; min-width: 400px;">
                <!-- INICIO NUEVO CÓDIGO: BARRA DE BÚSQUEDA -->
                <div style="margin-bottom: 15px; display: flex; justify-content: flex-end;">
                    <input type="text" id="buscador_clientes" onkeyup="filtrarClientes()" placeholder="🔍 Buscar por cédula o nombre..." style="padding: 10px; width: 100%; max-width: 350px; border-radius: 5px; border: 1px solid #cbd5e1; font-size: 0.95rem; outline: none; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                </div>
                <!-- FIN NUEVO CÓDIGO -->
                <div class="caja-scroll">
                    <table class="tabla-clientes">
                        <thead>
                            <tr>
                                <th>CÉDULA</th>
                                <th>NOMBRE</th>
                                <th>WHATSAPP</th>
                                <th>ENVIAR PROMO</th>
                                <th style="text-align: center;">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($resultado_clientes && $resultado_clientes->num_rows > 0):
                                while ($cliente = $resultado_clientes->fetch_assoc()): 
                                    $num_limpio = preg_replace('/[^0-9]/', '', $cliente['telefono']);
                            ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 15px; color: #334155;"><?php echo htmlspecialchars($cliente['cedula']); ?></td>
                                        <td style="padding: 12px 15px; font-weight: bold; color: black;"><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td style="padding: 12px 15px;">
                                            <?php if(!empty($cliente['telefono'])): ?>
                                                <span style="color: #475569;">📞 <?php echo htmlspecialchars($cliente['telefono']); ?></span>
                                            <?php else: ?>
                                                <span style="color: #cbd5e1; font-style: italic;">Sin número</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td style="padding: 12px 15px;">
                                            <?php if(!empty($num_limpio)): ?>
                                                <div style="display: flex; gap: 8px;">
                                                    <a href="#" class="btn-marketing btn-wa" onclick="enviarPorWhatsApp(event, '<?php echo $num_limpio; ?>')">💬 WhatsApp</a>
                                                    <a href="#" class="btn-marketing btn-sms" onclick="enviarPorSMS(event, '<?php echo $num_limpio; ?>')">✉️ SMS</a>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #cbd5e1; font-size: 0.85rem;">No disponible</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding: 12px 15px; text-align: center;">
                                            <div style="display: flex; gap: 5px; justify-content: center;">
                                                <button type="button" class="btn-editar" title="Editar contacto" onclick="cargarParaEditar('<?php echo $cliente['id']; ?>', '<?php echo htmlspecialchars($cliente['cedula'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES); ?>')">✏️</button>
                                                <a href="clientes.php?eliminar_id=<?php echo $cliente['id']; ?>" class="btn-accion btn-eliminar" title="Eliminar contacto" onclick="return confirm('¿Seguro que deseas eliminar al cliente <?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES); ?>?')">🗑️</a>
                                            </div>
                                        </td>
                                    </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="5" style="padding: 30px; text-align: center; color: #64748b; font-style: italic;">
                                        No hay clientes registrados en la base de datos.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <script>
        function obtenerMensaje() {
            const msg = document.getElementById('texto_promocional').value.trim();
            if(msg === "") {
                alert("Por favor, escribe primero un mensaje en el panel de promociones.");
                return false;
            }
            return encodeURIComponent(msg); 
        }

        function enviarPorWhatsApp(event, telefono) {
            event.preventDefault(); 
            const mensajeCodificado = obtenerMensaje();
            if(!mensajeCodificado) return;

            let numeroDestino = telefono;
            if (!numeroDestino.startsWith('58') && numeroDestino.startsWith('0')) {
                numeroDestino = '58' + numeroDestino.substring(1); 
            } else if (!numeroDestino.startsWith('58')) {
                numeroDestino = '58' + numeroDestino;
            }

            const urlWa = `https://wa.me/${numeroDestino}?text=${mensajeCodificado}`;
            window.open(urlWa, '_blank');
        }

        function enviarPorSMS(event, telefono) {
            event.preventDefault();
            const mensajeCodificado = obtenerMensaje();
            if(!mensajeCodificado) return;

            const urlSms = `sms:${telefono}?body=${mensajeCodificado}`;
            window.location.href = urlSms;
        }

        function cargarParaEditar(id, cedula, nombre, telefono) {
            document.getElementById('titulo-formulario').innerText = "✏️ Editando: " + nombre;
            document.getElementById('cedula_input').value = cedula;
            document.getElementById('nombre_input').value = nombre;
            document.getElementById('telefono_input').value = telefono;
            document.getElementById('id_cliente_input').value = id;
            document.getElementById('btn-cancelar-edicion').style.display = 'block';
            document.getElementById('cedula_input').focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetFormulario() {
            document.getElementById('titulo-formulario').innerText = "Registrar / Editar Cliente";
            document.getElementById('form-cliente').reset(); 
            document.getElementById('id_cliente_input').value = ""; 
            document.getElementById('btn-cancelar-edicion').style.display = 'none'; 
        }
        // =========================================
        // FUNCIÓN PARA BUSCAR EN LA TABLA
        // =========================================
        function filtrarClientes() {
            // Obtener el valor escrito en el buscador y pasarlo a minúsculas
            let input = document.getElementById("buscador_clientes");
            let filtro = input.value.toLowerCase();
            
            // Obtener las filas de la tabla
            let tabla = document.querySelector(".tabla-clientes tbody");
            let filas = tabla.getElementsByTagName("tr");

            // Recorrer todas las filas de la tabla
            for (let i = 0; i < filas.length; i++) {
                // Evitar filtrar la fila de "No hay clientes" si existe
                if (filas[i].getElementsByTagName("td").length === 1) continue;

                // Obtener las celdas de Cédula (columna 0) y Nombre (columna 1)
                let celdaCedula = filas[i].getElementsByTagName("td")[0];
                let celdaNombre = filas[i].getElementsByTagName("td")[1];

                if (celdaCedula || celdaNombre) {
                    let textoCedula = celdaCedula.textContent || celdaCedula.innerText;
                    let textoNombre = celdaNombre.textContent || celdaNombre.innerText;

                    // Si el filtro coincide con la cédula o el nombre, mostrar fila; si no, ocultarla
                    if (textoCedula.toLowerCase().indexOf(filtro) > -1 || textoNombre.toLowerCase().indexOf(filtro) > -1) {
                        filas[i].style.display = "";
                    } else {
                        filas[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>
<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) { echo json_encode(["exito" => false, "mensaje" => "Sesión expirada."]); exit; }

$datos = json_decode(file_get_contents("php://input"), true);
if (!$datos || empty($datos['carrito'])) { echo json_encode(["exito" => false, "mensaje" => "Ticket vacío."]); exit; }

// AUTO-PARCHE DE BASE DE DATOS ACTUALIZADO
$columnas = [
    'mixto_usd' => 'DECIMAL(10,2) DEFAULT 0.00',
    'mixto_efec_bs' => 'DECIMAL(10,2) DEFAULT 0.00',
    'mixto_pm_bs' => 'DECIMAL(10,2) DEFAULT 0.00',
    'mixto_pv_bs' => 'DECIMAL(10,2) DEFAULT 0.00',
    'mixto_bp_bs' => 'DECIMAL(10,2) DEFAULT 0.00',
    'mixto_otro_usd' => 'DECIMAL(10,2) DEFAULT 0.00',
    'mixto_otro_detalle' => 'VARCHAR(100) NULL',
    'billete_recibido_usd' => 'DECIMAL(10,2) DEFAULT 0.00',
    'vuelto_entregado_bs' => 'DECIMAL(10,2) DEFAULT 0.00',
    'vuelto_entregado_usd' => 'DECIMAL(10,2) DEFAULT 0.00',
    'metodo_vuelto' => 'VARCHAR(50) DEFAULT NULL',
    'abono_a_cliente_usd' => 'DECIMAL(10,2) DEFAULT 0.00'
];
foreach($columnas as $col => $tipo) {
    $check = $conexion->query("SHOW COLUMNS FROM ventas LIKE '$col'");
    if ($check && $check->num_rows == 0) $conexion->query("ALTER TABLE ventas ADD $col $tipo");
}

$check_ha = $conexion->query("SHOW COLUMNS FROM historico_abonos LIKE 'monto_bs'");
if ($check_ha && $check_ha->num_rows == 0) {
    $conexion->query("ALTER TABLE historico_abonos ADD monto_bs DECIMAL(10,2) NULL AFTER monto_usd");
    $conexion->query("UPDATE historico_abonos ha JOIN ventas v ON ha.venta_id = v.id SET ha.monto_bs = ha.monto_usd * IFNULL(ha.tasa_pago, v.tasa_aplicada)");
}

$usuario_id = $_SESSION['usuario_id'];
$cliente_cedula = trim($datos['clienteCedula']);
$cliente_nombre = trim($datos['clienteNombre']);
$tasa = floatval($datos['tasa']);
$total_usd = floatval($datos['totalUsd']);
$total_bs = floatval($datos['totalBs']);
$metodo_pago = $datos['metodoPago']; 
$abono_usd = floatval($datos['abonoUsd']); 
$deuda_usd = floatval($datos['deudaUsd']); 
$referencia_final = trim($datos['referencia'] ?? ''); 
$observaciones = trim($datos['observaciones'] ?? '');
$foto_base64 = $datos['foto_base64'] ?? '';

// Variables del Desglose Mixto
$m_usd = isset($datos['mixtoUsd']) ? floatval($datos['mixtoUsd']) : 0;
$m_efBs = isset($datos['mixtoEfecBs']) ? floatval($datos['mixtoEfecBs']) : 0;
$m_pmBs = isset($datos['mixtoPmBs']) ? floatval($datos['mixtoPmBs']) : 0;
$m_pvBs = isset($datos['mixtoPvBs']) ? floatval($datos['mixtoPvBs']) : 0;
$m_bpBs = isset($datos['mixtoBpBs']) ? floatval($datos['mixtoBpBs']) : 0;
$m_otroUsd = isset($datos['mixtoOtroUsd']) ? floatval($datos['mixtoOtroUsd']) : 0;
$m_otroDetalle = isset($datos['mixtoOtroDetalle']) ? trim($datos['mixtoOtroDetalle']) : '';

// LÓGICA DE RECEPCIÓN DE DATOS DEL VUELTO
$billete_recibido = isset($datos['billeteRecibidoUsd']) ? floatval($datos['billeteRecibidoUsd']) : $total_usd;
$vuelto_entregado_usd = isset($datos['vueltoDadoUsd']) ? floatval($datos['vueltoDadoUsd']) : 0;
$vuelto_entregado_bs = isset($datos['vueltoDadoBs']) ? floatval($datos['vueltoDadoBs']) : 0;
$moneda_vuelto = isset($datos['metodoVueltoBs']) ? trim($datos['metodoVueltoBs']) : '';
$abono_a_cliente = isset($datos['abonoAClienteUsd']) ? floatval($datos['abonoAClienteUsd']) : 0;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion->begin_transaction();

    $cliente_id = NULL; 
    if (!empty($cliente_cedula) && !empty($cliente_nombre)) {
        $stmt_check = $conexion->prepare("SELECT id FROM clientes WHERE cedula = ?");
        $stmt_check->bind_param("s", $cliente_cedula);
        $stmt_check->execute();
        $res_cliente = $stmt_check->get_result();
        if ($res_cliente->num_rows > 0) { $cliente_id = $res_cliente->fetch_assoc()['id']; } 
        else {
            $stmt_cli = $conexion->prepare("INSERT INTO clientes (cedula, nombre) VALUES (?, ?)");
            $stmt_cli->bind_param("ss", $cliente_cedula, $cliente_nombre);
            $stmt_cli->execute();
            $cliente_id = $stmt_cli->insert_id; $stmt_cli->close();
        }
        $stmt_check->close();
    }

    $stmt_venta = $conexion->prepare("INSERT INTO ventas (cliente_id, usuario_id, tasa_aplicada, total_usd, total_bs, metodo_pago, abono_usd, deuda_usd, referencia, observaciones, mixto_usd, mixto_efec_bs, mixto_pm_bs, mixto_pv_bs, mixto_bp_bs, mixto_otro_usd, mixto_otro_detalle, billete_recibido_usd, vuelto_entregado_bs, vuelto_entregado_usd, metodo_vuelto, abono_a_cliente_usd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_venta->bind_param("iidddsdsssddddddsdddsd", $cliente_id, $usuario_id, $tasa, $total_usd, $total_bs, $metodo_pago, $abono_usd, $deuda_usd, $referencia_final, $observaciones, $m_usd, $m_efBs, $m_pmBs, $m_pvBs, $m_bpBs, $m_otroUsd, $m_otroDetalle, $billete_recibido, $vuelto_entregado_bs, $vuelto_entregado_usd, $moneda_vuelto, $abono_a_cliente);
    $stmt_venta->execute();
    $venta_id = $stmt_venta->insert_id; 
    $stmt_venta->close();

    if (!empty($foto_base64) && $deuda_usd > 0) {
        $directorio = 'img/evidencias/';
        if (!file_exists($directorio)) mkdir($directorio, 0777, true);
        $partes_imagen = explode(',', $foto_base64);
        if (count($partes_imagen) == 2) {
            $datos_imagen = base64_decode($partes_imagen[1]);
            $nombre_archivo = $directorio . 'evidencia_ticket_' . $venta_id . '_' . time() . '.jpg';
            file_put_contents($nombre_archivo, $datos_imagen);
            $stmt_foto = $conexion->prepare("UPDATE ventas SET foto_evidencia = ? WHERE id = ?");
            $stmt_foto->bind_param("si", $nombre_archivo, $venta_id);
            $stmt_foto->execute(); $stmt_foto->close();
        }
    }

    $stmt_detalle = $conexion->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stmt_stock = $conexion->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");

    foreach ($datos['carrito'] as $item) {
        $prod_id = intval($item['id']); $cant = floatval($item['cantidad']); $precio = floatval($item['precioUsd']);
        $subtotal = $cant * $precio;

        $stmt_detalle->bind_param("iiddd", $venta_id, $prod_id, $cant, $precio, $subtotal);
        $stmt_detalle->execute();

        $stmt_check_combo = $conexion->prepare("SELECT producto_id, cantidad FROM combo_detalles WHERE combo_id = ?");
        $stmt_check_combo->bind_param("i", $prod_id);
        $stmt_check_combo->execute();
        $res_ingredientes = $stmt_check_combo->get_result();

        if ($res_ingredientes->num_rows > 0) {
            while ($ingrediente = $res_ingredientes->fetch_assoc()) {
                $ing_id = intval($ingrediente['producto_id']);
                $total_descuento_ingrediente = $cant * floatval($ingrediente['cantidad']);
                $stmt_stock_ing = $conexion->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt_stock_ing->bind_param("di", $total_descuento_ingrediente, $ing_id);
                $stmt_stock_ing->execute(); $stmt_stock_ing->close();
            }
        } else {
            $stmt_stock->bind_param("di", $cant, $prod_id);
            $stmt_stock->execute();
        }
        $stmt_check_combo->close();
    }
    $stmt_detalle->close(); $stmt_stock->close();

    if ($abono_usd > 0 && strpos($metodo_pago, 'credito') !== false) {
        $metodo_real_del_abono = str_replace('credito_', '', $metodo_pago);
        
        $hist_usd = 0; $hist_bs = 0; $hist_met = ''; $hist_ref = '';
        $stmt_hist = $conexion->prepare("INSERT INTO historico_abonos (venta_id, usuario_id, monto_usd, monto_bs, metodo_pago, referencia, tasa_pago) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_hist->bind_param("iiddssd", $venta_id, $usuario_id, $hist_usd, $hist_bs, $hist_met, $hist_ref, $tasa);
        
        if ($metodo_real_del_abono === 'mixto') {
            if ($m_usd > 0) { $hist_usd = $m_usd; $hist_bs = $m_usd * $tasa; $hist_met = 'efectivo_usd'; $hist_ref = ''; $stmt_hist->execute(); }
            if ($m_efBs > 0) { $hist_usd = $m_efBs / $tasa; $hist_bs = $m_efBs; $hist_met = 'efectivo_bs'; $hist_ref = ''; $stmt_hist->execute(); }
            if ($m_pmBs > 0) { $hist_usd = $m_pmBs / $tasa; $hist_bs = $m_pmBs; $hist_met = 'pago_movil'; $hist_ref = $referencia_final; $stmt_hist->execute(); }
            if ($m_pvBs > 0) { $hist_usd = $m_pvBs / $tasa; $hist_bs = $m_pvBs; $hist_met = 'punto_venta'; $hist_ref = ''; $stmt_hist->execute(); }
            if ($m_bpBs > 0) { $hist_usd = $m_bpBs / $tasa; $hist_bs = $m_bpBs; $hist_met = 'biopago'; $hist_ref = ''; $stmt_hist->execute(); }
            if ($m_otroUsd > 0) { $hist_usd = $m_otroUsd; $hist_bs = $m_otroUsd * $tasa; $hist_met = 'otro_' . strtolower(str_replace(' ', '_', $m_otroDetalle)); $hist_ref = ''; $stmt_hist->execute(); }
        } else {
            if ($metodo_real_del_abono == 'credito') $metodo_real_del_abono = 'efectivo_usd';
            $hist_usd = $abono_usd; $hist_bs = $abono_usd * $tasa; $hist_met = $metodo_real_del_abono; $hist_ref = $referencia_final;
            $stmt_hist->execute();
        }
        $stmt_hist->close();
    }

    $conexion->commit();
    ob_clean(); 
    echo json_encode(["exito" => true, "mensaje" => "Venta registrada con éxito.", "id_venta" => $venta_id]);

} catch (Exception $e) {
    $conexion->rollback();
    ob_clean(); 
    echo json_encode(["exito" => false, "mensaje" => "Error interno: " . $e->getMessage()]);
}
?>
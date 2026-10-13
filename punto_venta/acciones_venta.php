<?php
session_start();
require_once 'conexion.php';

// Verificamos de forma estricta el rol de Administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'admin') {
    echo "<script>alert('Acceso denegado'); window.location.href='reportes.php';</script>";
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// =======================================================
// ACCIÓN 1: ANULAR VENTA Y RESTAURAR INVENTARIO
// =======================================================
if ($accion === 'eliminar') {
    $venta_id = intval($_GET['id']);
    
    try {
        $conexion->begin_transaction();
        
        $stmt_det = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_ventas WHERE venta_id = ?");
        $stmt_det->bind_param("i", $venta_id);
        $stmt_det->execute();
        $detalles = $stmt_det->get_result();
        
        while ($item = $detalles->fetch_assoc()) {
            $prod_id = $item['producto_id'];
            $cant = floatval($item['cantidad']);
            
            $stmt_combo = $conexion->prepare("SELECT producto_id, cantidad FROM combo_detalles WHERE combo_id = ?");
            $stmt_combo->bind_param("i", $prod_id);
            $stmt_combo->execute();
            $res_ing = $stmt_combo->get_result();
            
            if ($res_ing->num_rows > 0) {
                while ($ing = $res_ing->fetch_assoc()) {
                    $ing_id = intval($ing['producto_id']);
                    $cant_restaurar = $cant * floatval($ing['cantidad']);
                    $conexion->query("UPDATE productos SET stock = stock + $cant_restaurar WHERE id = $ing_id");
                }
            } else {
                $conexion->query("UPDATE productos SET stock = stock + $cant WHERE id = $prod_id");
            }
            $stmt_combo->close();
        }
        $stmt_det->close();
        
        $res_foto = $conexion->query("SELECT foto_evidencia FROM ventas WHERE id = $venta_id");
        if ($res_foto && $res_foto->num_rows > 0) {
            $foto = $res_foto->fetch_assoc()['foto_evidencia'];
            if (!empty($foto) && file_exists($foto)) { unlink($foto); }
        }
        
        $conexion->query("DELETE FROM detalle_ventas WHERE venta_id = $venta_id");
        $conexion->query("DELETE FROM historico_abonos WHERE venta_id = $venta_id");
        $conexion->query("DELETE FROM ventas WHERE id = $venta_id");
        
        $conexion->commit();
        echo "<script>alert('✅ Venta anulada con éxito. Los productos han vuelto al inventario.'); window.location.href='reportes.php';</script>";
        
    } catch (Exception $e) {
        $conexion->rollback();
        echo "<script>alert('❌ Error al anular la venta: " . addslashes($e->getMessage()) . "'); window.location.href='reportes.php';</script>";
    }
}

// =======================================================
// ACCIÓN 2: EDITAR EL MÉTODO DE PAGO Y VUELTO
// =======================================================
if ($accion === 'editar_pago') {
    $venta_id = intval($_POST['venta_id']);
    $nuevo_metodo_base = $_POST['metodo_pago']; 
    $referencia = trim($_POST['referencia'] ?? '');
    
    // NUEVOS CAMPOS: Atrapando los datos del billete y el vuelto
    $billete = floatval($_POST['billete_recibido_usd'] ?? 0);
    $vuelto_usd = floatval($_POST['vuelto_entregado_usd'] ?? 0);
    $vuelto_bs = floatval($_POST['vuelto_entregado_bs'] ?? 0);
    $met_vuelto = trim($_POST['metodo_vuelto'] ?? '');
    
    // Obtener la radiografía de la venta original
    $res = $conexion->query("SELECT * FROM ventas WHERE id = $venta_id");
    $venta = $res->fetch_assoc();
    $is_credito = (strpos($venta['metodo_pago'], 'credito') !== false);
    $tasa = floatval($venta['tasa_aplicada']);
    $usuario_id = $venta['usuario_id'];
    $abono_usd = floatval($venta['abono_usd']);
    
    // 🚨 DATO CLAVE: Rescatamos la fecha original para engañar al reporte
    $fecha_original = $venta['fecha']; 
    
    $metodo_final = $is_credito ? "credito_" . $nuevo_metodo_base : $nuevo_metodo_base;
    if ($metodo_final == 'credito_credito') $metodo_final = 'credito'; 

    try {
        $conexion->begin_transaction();

        // Limpiamos los rastros viejos
        $conexion->query("DELETE FROM historico_abonos WHERE venta_id = $venta_id");

        if ($nuevo_metodo_base == 'mixto') {
            $m_usd = floatval($_POST['mix_usd'] ?? 0);
            $m_efBs = floatval($_POST['mix_efec_bs'] ?? 0);
            $m_pmBs = floatval($_POST['mix_pm_bs'] ?? 0);
            $m_pvBs = floatval($_POST['mix_pv_bs'] ?? 0);
            $m_bpBs = floatval($_POST['mix_bp_bs'] ?? 0);
            $m_otroUsd = floatval($_POST['mix_otro_usd'] ?? 0);
            $m_otroDetalle = trim($_POST['mix_otro_detalle'] ?? '');
            
            // Sobrescribimos la tabla ventas dejando los vueltos en 0 porque es pago mixto
            $stmt = $conexion->prepare("UPDATE ventas SET metodo_pago=?, referencia=?, mixto_usd=?, mixto_efec_bs=?, mixto_pm_bs=?, mixto_pv_bs=?, mixto_bp_bs=?, mixto_otro_usd=?, mixto_otro_detalle=?, billete_recibido_usd=0, vuelto_entregado_usd=0, vuelto_entregado_bs=0, metodo_vuelto=NULL WHERE id=?");
            $stmt->bind_param("ssddddddsi", $metodo_final, $referencia, $m_usd, $m_efBs, $m_pmBs, $m_pvBs, $m_bpBs, $m_otroUsd, $m_otroDetalle, $venta_id);
            $stmt->execute();
            
            if ($is_credito) {
                $stmt_hist = $conexion->prepare("INSERT INTO historico_abonos (venta_id, usuario_id, monto_usd, monto_bs, metodo_pago, referencia, tasa_pago, fecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                if ($m_usd > 0) { $met = 'efectivo_usd'; $ref = ''; $bs_exact = $m_usd * $tasa; $stmt_hist->bind_param("iiddssds", $venta_id, $usuario_id, $m_usd, $bs_exact, $met, $ref, $tasa, $fecha_original); $stmt_hist->execute(); }
                if ($m_efBs > 0) { $usd_conv = $m_efBs / $tasa; $met = 'efectivo_bs'; $ref = ''; $bs_exact = $m_efBs; $stmt_hist->bind_param("iiddssds", $venta_id, $usuario_id, $usd_conv, $bs_exact, $met, $ref, $tasa, $fecha_original); $stmt_hist->execute(); }
                if ($m_pmBs > 0) { $usd_conv = $m_pmBs / $tasa; $met = 'pago_movil'; $ref = $referencia; $bs_exact = $m_pmBs; $stmt_hist->bind_param("iiddssds", $venta_id, $usuario_id, $usd_conv, $bs_exact, $met, $ref, $tasa, $fecha_original); $stmt_hist->execute(); }
                if ($m_pvBs > 0) { $usd_conv = $m_pvBs / $tasa; $met = 'punto_venta'; $ref = ''; $bs_exact = $m_pvBs; $stmt_hist->bind_param("iiddssds", $venta_id, $usuario_id, $usd_conv, $bs_exact, $met, $ref, $tasa, $fecha_original); $stmt_hist->execute(); }
                if ($m_bpBs > 0) { $usd_conv = $m_bpBs / $tasa; $met = 'biopago'; $ref = ''; $bs_exact = $m_bpBs; $stmt_hist->bind_param("iiddssds", $venta_id, $usuario_id, $usd_conv, $bs_exact, $met, $ref, $tasa, $fecha_original); $stmt_hist->execute(); }
                if ($m_otroUsd > 0) { $met = 'otro_' . strtolower(str_replace(' ', '_', $m_otroDetalle)); $ref = ''; $bs_exact = $m_otroUsd * $tasa; $stmt_hist->bind_param("iiddssds", $venta_id, $usuario_id, $m_otroUsd, $bs_exact, $met, $ref, $tasa, $fecha_original); $stmt_hist->execute(); }
                
                $stmt_hist->close();
            }
        } else {
            // NUEVO: Sobrescribimos incluyendo Billetes y Vueltos en ventas comunes
            $stmt = $conexion->prepare("UPDATE ventas SET metodo_pago=?, referencia=?, mixto_usd=0, mixto_efec_bs=0, mixto_pm_bs=0, mixto_pv_bs=0, mixto_bp_bs=0, mixto_otro_usd=0, mixto_otro_detalle=NULL, billete_recibido_usd=?, vuelto_entregado_usd=?, vuelto_entregado_bs=?, metodo_vuelto=? WHERE id=?");
            $stmt->bind_param("ssdddsi", $metodo_final, $referencia, $billete, $vuelto_usd, $vuelto_bs, $met_vuelto, $venta_id);
            $stmt->execute();
            
            if ($is_credito && $abono_usd > 0) {
                $monto_reinsert_bs = $abono_usd * $tasa;
                $stmt_hist = $conexion->prepare("INSERT INTO historico_abonos (venta_id, usuario_id, monto_usd, monto_bs, metodo_pago, referencia, tasa_pago, fecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_hist->bind_param("iiddssds", $venta_id, $usuario_id, $abono_usd, $monto_reinsert_bs, $nuevo_metodo_base, $referencia, $tasa, $fecha_original);
                $stmt_hist->execute();
                $stmt_hist->close();
            }
        }

        $conexion->commit();
        echo "<script>window.location.href='reportes.php';</script>";
        
    } catch (Exception $e) {
        $conexion->rollback();
        echo "<script>alert('❌ Error al actualizar el pago: " . addslashes($e->getMessage()) . "'); window.location.href='reportes.php';</script>";
    }
}
?>
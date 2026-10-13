<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'admin') {
    header("Location: menu.php");
    exit;
}

require_once 'conexion.php';

// ==========================================
// 1. AUTO-CREACIÓN DE TABLA PARA FONDOS DE CAJA
// ==========================================
$check_ingresos = $conexion->query("SHOW TABLES LIKE 'ingresos_caja'");
if ($check_ingresos && $check_ingresos->num_rows == 0) {
    $conexion->query("CREATE TABLE ingresos_caja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        usuario_id INT,
        descripcion VARCHAR(255),
        metodo_pago VARCHAR(50),
        monto DECIMAL(10,2)
    )");
}

// ==========================================
// 2. PROCESAR NUEVO FONDO DE CAJA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_ingreso'])) {
    $desc = $conexion->real_escape_string($_POST['descripcion']);
    $metodo = $conexion->real_escape_string($_POST['metodo_pago']);
    $monto = floatval($_POST['monto']);
    $user_id = $_SESSION['usuario_id'];
    $conexion->query("INSERT INTO ingresos_caja (usuario_id, descripcion, metodo_pago, monto) VALUES ($user_id, '$desc', '$metodo', $monto)");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ==========================================
// 3. PROCESAR ELIMINAR FONDO DE CAJA (GET)
// ==========================================
if (isset($_GET['eliminar_ingreso'])) {
    $id_del = intval($_GET['eliminar_ingreso']);
    $conexion->query("DELETE FROM ingresos_caja WHERE id = $id_del");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ==========================================
// 4. PROCESAR ELIMINAR/REVERSAR ABONO (GET)
// ==========================================
if (isset($_GET['eliminar_abono'])) {
    $ids_abonos = $conexion->real_escape_string($_GET['eliminar_abono']);
    $array_ids = explode(',', $ids_abonos);
    
    foreach ($array_ids as $id_abono) {
        $id_abono = intval($id_abono);
        if ($id_abono > 0) {
            $q_viejo = $conexion->query("SELECT venta_id, monto_usd FROM historico_abonos WHERE id = $id_abono");
            if ($q_viejo && $q_viejo->num_rows > 0) {
                $viejo = $q_viejo->fetch_assoc();
                $venta_id = intval($viejo['venta_id']);
                $monto_viejo = floatval($viejo['monto_usd']);

                $conexion->query("UPDATE ventas SET deuda_usd = deuda_usd + $monto_viejo WHERE id = $venta_id");
                $conexion->query("DELETE FROM historico_abonos WHERE id = $id_abono");
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$q_tasa = $conexion->query("SELECT tasa_dia FROM configuracion ORDER BY id DESC LIMIT 1");
$tasa_actual = ($q_tasa && $q_tasa->num_rows > 0) ? floatval($q_tasa->fetch_assoc()['tasa_dia']) : 1;

$check = $conexion->query("SHOW COLUMNS FROM historico_abonos LIKE 'tasa_pago'");
if ($check && $check->num_rows == 0) {
    $conexion->query("ALTER TABLE historico_abonos ADD tasa_pago DECIMAL(10,2) NULL");
    $conexion->query("UPDATE historico_abonos ha JOIN ventas v ON ha.venta_id = v.id SET ha.tasa_pago = v.tasa_aplicada");
}

$query_hoy = "SELECT SUM(total_bs) as bs FROM ventas WHERE DATE(fecha) = CURDATE()";
$res_hoy = $conexion->query($query_hoy)->fetch_assoc();
$ventas_bs_hoy = $res_hoy['bs'] ?? 0;
$ventas_usd_hoy = ($tasa_actual > 0) ? ($ventas_bs_hoy / $tasa_actual) : 0;

function obtenerMejorVendedor($conexion, $condicion) {
    $query = "SELECT u.nombre, SUM(v.total_usd) as total FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE $condicion GROUP BY v.usuario_id ORDER BY total DESC LIMIT 1";
    $res = $conexion->query($query);
    return $res ? $res->fetch_assoc() : null;
}

$mejor_dia = obtenerMejorVendedor($conexion, "DATE(v.fecha) = CURDATE()");
$mejor_mes = obtenerMejorVendedor($conexion, "MONTH(v.fecha) = MONTH(CURDATE()) AND YEAR(v.fecha) = YEAR(CURDATE())");
$mejor_ano = obtenerMejorVendedor($conexion, "YEAR(v.fecha) = YEAR(CURDATE())");

$query_grafica = "SELECT MONTH(fecha) as mes_num, SUM(total_usd) as total FROM ventas WHERE YEAR(fecha) = YEAR(CURDATE()) GROUP BY MONTH(fecha) ORDER BY MONTH(fecha)";
$res_grafica = $conexion->query($query_grafica);
$meses_nombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$datos_ventas = array_fill(0, 12, 0); 
if ($res_grafica) {
    while ($fila = $res_grafica->fetch_assoc()) {
        $datos_ventas[$fila['mes_num'] - 1] = floatval($fila['total']);
    }
}

$filtro_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$filtro_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$filtro_vendedor = $_GET['vendedor_id'] ?? '';

$where_ventas = ["1=1"]; 
$where_abonos = ["1=1"]; 
$where_gastos = ["1=1"];
$where_ingresos = ["1=1"];

if (!empty($filtro_inicio)) {
    $fecha_esc = $conexion->real_escape_string($filtro_inicio);
    $where_ventas[] = "DATE(v.fecha) >= '$fecha_esc'";
    $where_abonos[] = "DATE(ha.fecha) >= '$fecha_esc'";
    $where_gastos[] = "DATE(g.fecha) >= '$fecha_esc'";
    $where_ingresos[] = "DATE(i.fecha) >= '$fecha_esc'";
}
if (!empty($filtro_fin)) {
    $fecha_esc = $conexion->real_escape_string($filtro_fin);
    $where_ventas[] = "DATE(v.fecha) <= '$fecha_esc'";
    $where_abonos[] = "DATE(ha.fecha) <= '$fecha_esc'";
    $where_gastos[] = "DATE(g.fecha) <= '$fecha_esc'";
    $where_ingresos[] = "DATE(i.fecha) <= '$fecha_esc'";
}
if (!empty($filtro_vendedor)) {
    $ven_id = intval($filtro_vendedor);
    $where_ventas[] = "v.usuario_id = $ven_id";
    $where_abonos[] = "ha.usuario_id = $ven_id";
    $where_gastos[] = "g.usuario_id = $ven_id";
    $where_ingresos[] = "i.usuario_id = $ven_id";
}

$where_sql_ventas = implode(" AND ", $where_ventas);
$where_sql_abonos = implode(" AND ", $where_abonos);
$where_sql_gastos = implode(" AND ", $where_gastos);
$where_sql_ingresos = implode(" AND ", $where_ingresos);

$q_costo = "SELECT SUM(IFNULL(p.precio_costo, 0) * dv.cantidad) as total_costo_usd 
            FROM detalle_ventas dv 
            JOIN ventas v ON dv.venta_id = v.id 
            LEFT JOIN productos p ON dv.producto_id = p.id 
            WHERE $where_sql_ventas";
$res_costo = $conexion->query($q_costo)->fetch_assoc();
$total_costo_usd = $res_costo['total_costo_usd'] ?? 0;

$q_venta_total = "SELECT SUM(total_bs) as total_venta_bs FROM ventas v WHERE $where_sql_ventas";
$res_venta_total = $conexion->query($q_venta_total)->fetch_assoc();
$total_venta_bs = $res_venta_total['total_venta_bs'] ?? 0;

$total_venta_usd = ($tasa_actual > 0) ? ($total_venta_bs / $tasa_actual) : 0;
$ganancia_neta_usd = $total_venta_usd - $total_costo_usd;
$ganancia_neta_bs = $ganancia_neta_usd * $tasa_actual;

$res_ventas_crudas = $conexion->query("SELECT SUM(total_usd) as t_usd, SUM(total_bs) as t_bs FROM ventas v WHERE $where_sql_ventas AND metodo_pago NOT LIKE 'credito%'")->fetch_assoc();
$t_ventas_usd = $res_ventas_crudas['t_usd'] ?? 0;
$t_ventas_bs = $res_ventas_crudas['t_bs'] ?? 0;

$res_abonos_crudos = $conexion->query("SELECT SUM(ha.monto_usd) as t_usd, SUM(IFNULL(ha.monto_bs, ha.monto_usd * IFNULL(ha.tasa_pago, v.tasa_aplicada))) as t_bs FROM historico_abonos ha JOIN ventas v ON ha.venta_id = v.id WHERE $where_sql_abonos AND ha.metodo_pago != 'descuento'")->fetch_assoc();
$t_abonos_usd = $res_abonos_crudos['t_usd'] ?? 0;
$t_abonos_bs = $res_abonos_crudos['t_bs'] ?? 0;

$caja = [
    'pago_movil'   => ['nombre' => '📱 PAGO MÓVIL', 'usd' => 0, 'bs' => 0, 'tipo' => 'bs', 'color' => '#3b82f6'],
    'biopago'      => ['nombre' => '👆 BIOPAGO', 'usd' => 0, 'bs' => 0, 'tipo' => 'bs', 'color' => '#2563eb'],
    'efectivo_bs'  => ['nombre' => '💵 EFECTIVO BS', 'usd' => 0, 'bs' => 0, 'tipo' => 'bs', 'color' => '#1e40af'],
    'punto_venta'  => ['nombre' => '💳 PUNTO VENTA', 'usd' => 0, 'bs' => 0, 'tipo' => 'bs', 'color' => '#475569'],
    'efectivo_usd' => ['nombre' => '💵 EFECTIVO USD', 'usd' => 0, 'bs' => 0, 'tipo' => 'usd', 'color' => '#10b981'],
    'trueque'      => ['nombre' => '📦 TRUEQUES', 'usd' => 0, 'bs' => 0, 'tipo' => 'usd', 'color' => '#d97706'],
    'otro'         => ['nombre' => '💳 OTROS', 'usd' => 0, 'bs' => 0, 'tipo' => 'usd', 'color' => '#94a3b8']
];

$q_v = "SELECT metodo_pago, total_usd, total_bs, mixto_usd, mixto_efec_bs, mixto_pm_bs, mixto_pv_bs, mixto_bp_bs, mixto_otro_usd, billete_recibido_usd, vuelto_entregado_bs, vuelto_entregado_usd, metodo_vuelto FROM ventas v WHERE $where_sql_ventas AND metodo_pago NOT LIKE 'credito%'";
$r_v = $conexion->query($q_v);
if ($r_v) {
    while($row = $r_v->fetch_assoc()){
        $m = strtolower(trim($row['metodo_pago']));
        
        if ($m == 'mixto') {
            $caja['efectivo_usd']['usd'] += floatval($row['mixto_usd']);
            $caja['efectivo_usd']['bs']  += floatval($row['mixto_usd'] * $tasa_actual);
            $caja['efectivo_bs']['bs']   += floatval($row['mixto_efec_bs']);
            $caja['efectivo_bs']['usd']  += floatval($row['mixto_efec_bs'] / $tasa_actual);
            $caja['pago_movil']['bs']    += floatval($row['mixto_pm_bs']);
            $caja['pago_movil']['usd']   += floatval($row['mixto_pm_bs'] / $tasa_actual);
            $caja['punto_venta']['bs']   += floatval($row['mixto_pv_bs']);
            $caja['punto_venta']['usd']  += floatval($row['mixto_pv_bs'] / $tasa_actual);
            $caja['biopago']['bs']       += floatval($row['mixto_bp_bs']);
            $caja['biopago']['usd']      += floatval($row['mixto_bp_bs'] / $tasa_actual);
            $caja['otro']['usd']         += floatval($row['mixto_otro_usd']);
            $caja['otro']['bs']          += floatval($row['mixto_otro_usd'] * $tasa_actual);
        } else {
            $billete = floatval($row['billete_recibido_usd'] ?? 0);
            $v_usd = floatval($row['vuelto_entregado_usd'] ?? 0);
            $v_bs = floatval($row['vuelto_entregado_bs'] ?? 0);
            $metodo_v = strtolower(trim($row['metodo_vuelto'] ?? 'usd'));

            if ($billete > floatval($row['total_usd']) && $m == 'efectivo_usd') {
                $caja['efectivo_usd']['usd'] += $billete;
                $caja['efectivo_usd']['bs']  += ($billete * $tasa_actual);

                if ($v_usd > 0) {
                     $caja['efectivo_usd']['usd'] -= $v_usd;
                     $caja['efectivo_usd']['bs']  -= ($v_usd * $tasa_actual);
                }
                if ($v_bs > 0) {
                     if ($metodo_v == 'pago_movil') {
                         $caja['pago_movil']['bs'] -= $v_bs;
                         $caja['pago_movil']['usd'] -= ($v_bs / $tasa_actual);
                     } else {
                         $caja['efectivo_bs']['bs'] -= $v_bs;
                         $caja['efectivo_bs']['usd'] -= ($v_bs / $tasa_actual);
                     }
                }
            } 
            elseif ($m == 'efectivo_bs' && ($v_usd > 0 || $v_bs > 0)) {
                $excedente_bs = ($v_usd * $tasa_actual) + $v_bs;
                
                $caja['efectivo_bs']['bs'] += (floatval($row['total_bs']) + $excedente_bs);
                $caja['efectivo_bs']['usd'] += (floatval($row['total_usd']) + ($excedente_bs / $tasa_actual));

                if ($v_usd > 0) {
                     $caja['efectivo_usd']['usd'] -= $v_usd;
                     $caja['efectivo_usd']['bs']  -= ($v_usd * $tasa_actual);
                }
                if ($v_bs > 0) {
                     if ($metodo_v == 'pago_movil') {
                         $caja['pago_movil']['bs'] -= $v_bs;
                         $caja['pago_movil']['usd'] -= ($v_bs / $tasa_actual);
                     } else {
                         $caja['efectivo_bs']['bs'] -= $v_bs;
                         $caja['efectivo_bs']['usd'] -= ($v_bs / $tasa_actual);
                     }
                }
            } 
            else {
                if(isset($caja[$m])) { 
                    $caja[$m]['usd'] += floatval($row['total_usd']); $caja[$m]['bs'] += floatval($row['total_bs']); 
                } elseif(strpos($m, 'otro') !== false) {
                    $caja['otro']['usd'] += floatval($row['total_usd']); $caja['otro']['bs'] += floatval($row['total_bs']); 
                } else { 
                    $caja['otro']['usd'] += floatval($row['total_usd']); $caja['otro']['bs'] += floatval($row['total_bs']); 
                }
            }
        }
    }
}

$q_a = "SELECT ha.metodo_pago, SUM(ha.monto_usd) as usd, SUM(IFNULL(ha.monto_bs, ha.monto_usd * IFNULL(ha.tasa_pago, v.tasa_aplicada))) as bs 
        FROM historico_abonos ha JOIN ventas v ON ha.venta_id = v.id WHERE $where_sql_abonos AND ha.metodo_pago != 'descuento' GROUP BY ha.metodo_pago";
$r_a = $conexion->query($q_a);
if ($r_a) {
    while($row = $r_a->fetch_assoc()){
        $m = str_replace('credito_', '', strtolower(trim($row['metodo_pago'])));
        if(isset($caja[$m])) { 
            $caja[$m]['usd'] += $row['usd']; $caja[$m]['bs'] += $row['bs']; 
        } elseif(strpos($m, 'otro') !== false) {
            $caja['otro']['usd'] += $row['usd']; $caja['otro']['bs'] += $row['bs']; 
        } else { 
            $caja['otro']['usd'] += $row['usd']; $caja['otro']['bs'] += $row['bs']; 
        }
    }
}

$movimientos = [];
$q_detalles = "
    SELECT v.id as ticket_id, v.fecha, u.nombre as vendedor, v.total_usd, v.total_bs, v.metodo_pago, v.abono_usd, v.deuda_usd, v.referencia, v.tasa_aplicada,
           v.mixto_usd, v.mixto_efec_bs, v.mixto_pm_bs, v.mixto_pv_bs, v.mixto_bp_bs, v.mixto_otro_usd, v.mixto_otro_detalle,
           v.billete_recibido_usd, v.vuelto_entregado_bs, v.vuelto_entregado_usd, v.metodo_vuelto,
           GROUP_CONCAT(CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM dv.cantidad)), 'x ', p.nombre) SEPARATOR '<br>') as detalle_productos
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    LEFT JOIN detalle_ventas dv ON dv.venta_id = v.id
    LEFT JOIN productos p ON dv.producto_id = p.id
    WHERE $where_sql_ventas
    GROUP BY v.id
";
$res_detalles = $conexion->query($q_detalles);
if($res_detalles){
    while($v = $res_detalles->fetch_assoc()){
        $v['tipo_registro'] = 'venta';
        $movimientos[] = $v;
    }
}

$q_hist_abonos = "
    SELECT 
        ha.fecha, 
        u.nombre as vendedor,
        c.nombre as cliente_nombre,
        SUM(ha.monto_usd) as abono_usd, 
        SUM(IFNULL(ha.monto_bs, ha.monto_usd * IFNULL(ha.tasa_pago, v.tasa_aplicada))) as abono_bs,
        ha.metodo_pago, 
        ha.referencia, 
        MAX(IFNULL(ha.tasa_pago, v.tasa_aplicada)) as tasa_aplicada,
        GROUP_CONCAT(ha.venta_id SEPARATOR ', ') as tickets_afectados,
        GROUP_CONCAT(ha.id SEPARATOR ',') as abonos_ids
    FROM historico_abonos ha
    JOIN ventas v ON ha.venta_id = v.id
    JOIN usuarios u ON ha.usuario_id = u.id
    LEFT JOIN clientes c ON v.cliente_id = c.id
    WHERE $where_sql_abonos
      AND TIMESTAMPDIFF(SECOND, v.fecha, ha.fecha) > 2 
    GROUP BY ha.fecha, ha.usuario_id, ha.metodo_pago, ha.referencia, c.nombre
";
$res_hist_abonos = $conexion->query($q_hist_abonos);
if($res_hist_abonos){
    while($a = $res_hist_abonos->fetch_assoc()){
        $a['tipo_registro'] = 'abono';
        $t_arr = explode(', ', $a['tickets_afectados']);
        $tickets_format = array_map(function($t) { return "#".str_pad($t, 5, "0", STR_PAD_LEFT); }, $t_arr);
        $nombre_cl = htmlspecialchars($a['cliente_nombre'] ?? 'Desconocido');
        $a['detalle_productos'] = "💰 <strong>Abono a Cuenta</strong> - <span style='color:#0284c7; font-weight:bold;'>{$nombre_cl}</span><br><small style='color:#64748b;'>Aplicado a deudas de: " . implode(', ', $tickets_format) . "</small>";
        $movimientos[] = $a;
    }
}

$total_gastos_usd = 0;
$total_gastos_bs = 0;
$q_gastos = "SELECT g.*, u.nombre as vendedor FROM gastos g JOIN usuarios u ON g.usuario_id = u.id WHERE $where_sql_gastos";
$res_gastos = $conexion->query($q_gastos);

if($res_gastos){
    while($g = $res_gastos->fetch_assoc()){
        $m = strtolower(trim($g['metodo_pago']));
        $monto_crudo = floatval($g['monto']);
        $es_bs = (strpos($m, 'bs') !== false || strpos($m, 'movil') !== false || strpos($m, 'biopago') !== false || strpos($m, 'punto') !== false);
        
        if ($es_bs) {
            $m_bs = $monto_crudo; $m_usd = $monto_crudo / $tasa_actual;
        } else {
            $m_usd = $monto_crudo; $m_bs = $monto_crudo * $tasa_actual;
        }
        $total_gastos_usd += $m_usd; $total_gastos_bs += $m_bs;

        if(isset($caja[$m])) { 
            $caja[$m]['usd'] -= $m_usd; $caja[$m]['bs'] -= $m_bs; 
        } else { 
            $caja['otro']['usd'] -= $m_usd; $caja['otro']['bs'] -= $m_bs; 
        }

        $g['tipo_registro'] = 'gasto';
        $g['monto_usd_calc'] = $m_usd;
        $g['monto_bs_calc'] = $m_bs;
        $movimientos[] = $g;
    }
}

$total_ingresos_extra_usd = 0;
$total_ingresos_extra_bs = 0;
$q_ingresos = "SELECT i.*, u.nombre as vendedor FROM ingresos_caja i JOIN usuarios u ON i.usuario_id = u.id WHERE $where_sql_ingresos";
$res_ingresos = $conexion->query($q_ingresos);

if($res_ingresos){
    while($i = $res_ingresos->fetch_assoc()){
        $m = strtolower(trim($i['metodo_pago']));
        $monto_crudo = floatval($i['monto']);
        $es_bs = (strpos($m, 'bs') !== false || strpos($m, 'movil') !== false || strpos($m, 'biopago') !== false || strpos($m, 'punto') !== false);
        
        if ($es_bs) {
            $m_bs = $monto_crudo; $m_usd = $monto_crudo / $tasa_actual;
        } else {
            $m_usd = $monto_crudo; $m_bs = $monto_crudo * $tasa_actual;
        }
        $total_ingresos_extra_usd += $m_usd; $total_ingresos_extra_bs += $m_bs;

        if(isset($caja[$m])) { 
            $caja[$m]['usd'] += $m_usd; $caja[$m]['bs'] += $m_bs; 
        } else { 
            $caja['otro']['usd'] += $m_usd; $caja['otro']['bs'] += $m_bs; 
        }

        $i['tipo_registro'] = 'ingreso_caja';
        $i['monto_usd_calc'] = $m_usd;
        $i['monto_bs_calc'] = $m_bs;
        $movimientos[] = $i;
    }
}

usort($movimientos, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

$vendedores = $conexion->query("SELECT id, nombre FROM usuarios WHERE rol IN ('admin', 'vendedor')");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Estadísticos - Mi Negocio POS</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .pantalla-completa { padding: 2rem; width: 100%; box-sizing: border-box; }
        .grid-dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .panel-filtros { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .detalle-productos { font-size: 0.85rem; color: #475569; max-width: 300px; line-height: 1.4; }
        
        .cajas-cuadre { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .caja-monto { background: white; padding: 1.2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-top: 4px solid; text-align: center; }
        .caja-monto .titulo { font-size: 0.8rem; font-weight: bold; color: var(--texto-secundario); margin-bottom: 0.5rem; text-transform: uppercase; }
        .caja-monto .monto-principal { font-size: 1.4rem; font-weight: bold; margin-bottom: 0.2rem; }
        .caja-monto .monto-secundario { font-size: 0.85rem; color: #64748b; font-weight: bold; }
        .caja-monto.bs { border-color: #3b82f6; } .caja-monto.bs .monto-principal { color: #1e3a8a; }
        .caja-monto.usd { border-color: #10b981; } .caja-monto.usd .monto-principal { color: #065f46; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-caja { background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 450px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        
        .fila-mixta { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center; }
        .fila-mixta label { font-weight: bold; font-size: 0.85rem; width: 45%; }
        .fila-mixta input { width: 55%; padding: 0.4rem; border: 1px solid var(--borde); border-radius: 4px; }

        #ticket-print { display: none; }

        @media print {
            @page { margin: 0mm; size: 80mm 297mm; }
            html, body { background: white !important; color: black !important; padding: 0; margin: 0; width: 80mm; }
            body > *:not(#ticket-print) { display: none !important; }
            #ticket-print { display: block !important; width: 74mm; margin: 0 auto; padding: 2mm 0; font-family: 'Courier New', Courier, monospace !important; font-size: 13px; line-height: 1.3; color: #000; }
            #ticket-print h2 { font-size: 16px; margin: 0 0 5px 0; font-weight: bold; text-align: center; }
            #ticket-print h3 { font-size: 14px; margin: 0 0 5px 0; text-align: center; }
            #ticket-print p { margin: 2px 0; }
            .ticket-divider { border-bottom: 1px dashed #000; margin: 8px 0; }
            .ticket-text-center { text-align: center; }
            .ticket-bold { font-weight: bold; }
            .ticket-flex { display: flex; justify-content: space-between; }
            .ticket-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            .ticket-table td { padding: 4px 0; border-bottom: 1px dotted #ccc; vertical-align: top; }
            .ticket-table td:last-child { text-align: right; font-weight: bold; }
        }
    </style>
</head>
<body style="background-color: #f1f5f9;">
    <header class="top-bar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="menu.php" style="text-decoration: none; font-size: 1.5rem; color: var(--texto-principal);" title="Volver">⬅️</a>
            <h1>Centro de Comando y Reportes</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
            <button onclick="document.getElementById('modalIngreso').style.display='flex'" class="btn-login" style="background-color: #10b981; padding: 0.5rem 1rem; margin: 0; width: auto;">💰 Fondo de Caja</button>
            <button onclick="document.getElementById('modalGasto').style.display='flex'" class="btn-login" style="background-color: #ef4444; padding: 0.5rem 1rem; margin: 0; width: auto;">💸 Registrar Gasto</button>
            <button onclick="window.print()" class="btn-login" style="background-color: #64748b; padding: 0.5rem 1rem; margin: 0; width: auto;">🖨️ Imprimir Cuadre</button>
            <span class="badge-rol" style="background-color: var(--primario); color: white; margin-left: 5px;">MODO ADMINISTRADOR</span>
        </div>
    </header>

    <main class="pantalla-completa">
        <div class="grid-dashboard">
            <div class="tarjeta-login" style="padding: 1.5rem; border-left: 5px solid #10b981; margin: 0;">
                <h3 style="color: var(--texto-secundario); font-size: 1rem; margin-bottom: 0.5rem;">Ingresos de Hoy</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--texto-principal);">$<?php echo number_format($ventas_usd_hoy, 2); ?></div>
                <div style="color: #10b981; font-weight: bold"><?php echo number_format($ventas_bs_hoy, 2, ',', '.'); ?> Bs</div>
            </div>
            
            <div class="tarjeta-login" style="padding: 1.5rem; border-left: 5px solid #ef4444; margin: 0;">
                <h3 style="color: var(--texto-secundario); font-size: 1rem; margin-bottom: 0.5rem;">Gastos del Filtro</h3>
                <div style="font-size: 1.8rem; font-weight: bold; color: #ef4444;">-$<?php echo number_format($total_gastos_usd, 2); ?></div>
                <div style="color: #ef4444; font-weight: bold;">-<?php echo number_format($total_gastos_bs, 2, ',', '.'); ?> Bs</div>
            </div>

            <div class="tarjeta-login" style="padding: 1.5rem; border-left: 5px solid #3b82f6; margin: 0;">
                <h3 style="color: var(--texto-secundario); font-size: 1rem; margin-bottom: 0.5rem;">Líder del Día</h3>
                <div style="font-size: 1.2rem; font-weight: bold;"><?php echo $mejor_dia ? htmlspecialchars($mejor_dia['nombre']) : 'N/A'; ?></div>
                <div style="color: #64748b;">$<?php echo $mejor_dia ? number_format($mejor_dia['total'], 2) : '0.00'; ?></div>
            </div>
            <div class="tarjeta-login" style="padding: 1.5rem; border-left: 5px solid #8b5cf6; margin: 0;">
                <h3 style="color: var(--texto-secundario); font-size: 1rem; margin-bottom: 0.5rem;">Líder del Mes</h3>
                <div style="font-size: 1.2rem; font-weight: bold;"><?php echo $mejor_mes ? htmlspecialchars($mejor_mes['nombre']) : 'N/A'; ?></div>
                <div style="color: #64748b;">$<?php echo $mejor_mes ? number_format($mejor_mes['total'], 2) : '0.00'; ?></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 2.5fr; gap: 2rem;">
            <div class="tarjeta-login" style="padding: 1.5rem; margin: 0; height: fit-content;">
                <h2 style="margin-bottom: 1rem; font-size: 1.2rem;">Crecimiento <?php echo date('Y'); ?></h2>
                <canvas id="graficaVentas"></canvas>
            </div>

            <div>
                <form class="panel-filtros" method="GET" action="">
                    <div>
                        <label style="font-size: 0.85rem; font-weight: bold;">Fecha Desde</label><br>
                        <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($filtro_inicio); ?>" style="padding: 0.6rem; border: 1px solid var(--borde); border-radius: 5px;">
                    </div>
                    <div>
                        <label style="font-size: 0.85rem; font-weight: bold;">Fecha Hasta</label><br>
                        <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($filtro_fin); ?>" style="padding: 0.6rem; border: 1px solid var(--borde); border-radius: 5px;">
                    </div>
                    <div>
                        <label style="font-size: 0.85rem; font-weight: bold;">Vendedor</label><br>
                        <select name="vendedor_id" style="padding: 0.6rem; border: 1px solid var(--borde); border-radius: 5px; min-width: 150px;">
                            <option value="">Todos</option>
                            <?php while ($v = $vendedores->fetch_assoc()): ?>
                                <option value="<?php echo $v['id']; ?>" <?php if ($filtro_vendedor == $v['id']) echo 'selected'; ?>><?php echo htmlspecialchars($v['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-login" style="margin: 0; padding: 0.6rem 1.5rem;">Filtrar</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" style="margin-left: 0.5rem; color: var(--texto-secundario); text-decoration: none; font-size: 0.9rem;">Limpiar</a>
                    </div>
                </form>

                <h3 style="margin-bottom: 1rem; color: var(--texto-secundario);">💰 Resumen de Dinero y Ganancias</h3>

                <?php 
                $suma_total_bolivares = 0;
                $suma_total_dolares = 0;
                $suma_total_trueques = 0;

                foreach($caja as $key => $datos) {
                    if ($key == 'trueque') {
                        $suma_total_trueques += $datos['usd']; 
                    } else if ($datos['tipo'] == 'bs') { 
                        $suma_total_bolivares += $datos['bs']; 
                    } else { 
                        $suma_total_dolares += $datos['usd']; 
                    }
                }
                ?>
                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <div style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 1.5rem; border-radius: 10px; flex: 1; min-width: 200px; box-shadow: 0 4px 10px rgba(59,130,246,0.3);">
                        <div style="font-size: 0.85rem; font-weight: bold; text-transform: uppercase; opacity: 0.9; margin-bottom: 0.5rem;">🇻🇪 Total Bs en Caja</div>
                        <div style="font-size: 2rem; font-weight: bold;"><?php echo number_format($suma_total_bolivares, 2, ',', '.'); ?> Bs</div>
                        <div style="font-size: 0.75rem; font-weight: 500; opacity: 0.9; margin-top: 0.5rem;">(Pago Móvil + Biopago + Efectivo Bs + P. Venta)</div>
                    </div>
                    <div style="background: linear-gradient(135deg, #064e3b, #10b981); color: white; padding: 1.5rem; border-radius: 10px; flex: 1; min-width: 200px; box-shadow: 0 4px 10px rgba(16,185,129,0.3);">
                        <div style="font-size: 0.85rem; font-weight: bold; text-transform: uppercase; opacity: 0.9; margin-bottom: 0.5rem;">💵 Total $ en Caja</div>
                        <div style="font-size: 2rem; font-weight: bold;">$<?php echo number_format($suma_total_dolares, 2); ?></div>
                        <div style="font-size: 0.75rem; font-weight: 500; opacity: 0.9; margin-top: 0.5rem;">(Efectivo USD + Otros/Zelle)</div>
                    </div>
                    <div style="background: linear-gradient(135deg, #b45309, #d97706); color: white; padding: 1.5rem; border-radius: 10px; flex: 1; min-width: 200px; box-shadow: 0 4px 10px rgba(217,119,6,0.3);">
                        <div style="font-size: 0.85rem; font-weight: bold; text-transform: uppercase; opacity: 0.9; margin-bottom: 0.5rem;">📦 Total Trueques</div>
                        <div style="font-size: 2rem; font-weight: bold;">$<?php echo number_format($suma_total_trueques, 2); ?></div>
                        <div style="font-size: 0.75rem; font-weight: 500; opacity: 0.9; margin-top: 0.5rem;">(Ingreso en Mercancía - No suma al $)</div>
                    </div>
                    <div style="background: linear-gradient(135deg, #7c2d12, #f59e0b); color: white; padding: 1.5rem; border-radius: 10px; flex: 1; min-width: 200px; box-shadow: 0 4px 10px rgba(245,158,11,0.3);">
                        <div style="font-size: 0.85rem; font-weight: bold; text-transform: uppercase; opacity: 0.9; margin-bottom: 0.5rem;">📈 Mis Ganancias de Hoy</div>
                        <div style="font-size: 2rem; font-weight: bold;">$<?php echo number_format($ganancia_neta_usd, 2); ?></div>
                        <div style="font-size: 1rem; font-weight: bold; margin-top: 0.2rem; opacity: 0.9;"><?php echo number_format($ganancia_neta_bs, 2, ',', '.'); ?> Bs</div>
                        <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 0.2rem;">(Precio Vendido - Costo)</div>
                    </div>
                </div>

                <div class="cajas-cuadre">
                    <?php foreach($caja as $key => $datos): ?>
                        <div class="caja-monto <?php echo $datos['tipo']; ?>" style="border-color: <?php echo $datos['color']; ?>">
                            <div class="titulo"><?php echo $datos['nombre']; ?></div>
                            <?php if($datos['tipo'] == 'bs'): ?>
                                <div class="monto-principal" style="<?php echo $datos['bs'] < 0 ? 'color: red;' : ''; ?>"><?php echo number_format($datos['bs'], 2, ',', '.'); ?> Bs</div>
                                <div class="monto-secundario" style="<?php echo $datos['usd'] < 0 ? 'color: red;' : ''; ?>">Ref: $<?php echo number_format($datos['usd'], 2); ?></div>
                            <?php else: ?>
                                <div class="monto-principal" style="<?php echo $datos['usd'] < 0 ? 'color: red;' : ''; ?>">$<?php echo number_format($datos['usd'], 2); ?></div>
                                <div class="monto-secundario" style="<?php echo $datos['bs'] < 0 ? 'color: red;' : ''; ?>"><?php echo number_format($datos['bs'], 2, ',', '.'); ?> Bs</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow-x: auto;">
                    <table class="tabla-inventario" style="margin: 0; width: 100%;">
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th>Fecha / Hora</th>
                                <th>Usuario</th>
                                <th style="width: 35%;">Detalle del Registro</th>
                                <th>Método / Tipo</th>
                                <th style="text-align: right; min-width: 140px;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimientos)): ?>
                                <?php foreach ($movimientos as $mov): 
                                    $es_abono = ($mov['tipo_registro'] == 'abono');
                                    $es_gasto = ($mov['tipo_registro'] == 'gasto');
                                    $es_ingreso = ($mov['tipo_registro'] == 'ingreso_caja'); 
                                    $metodo_limpio = strtolower($mov['metodo_pago']);
                                    $es_moneda_bs = (strpos($metodo_limpio, 'bs') !== false || strpos($metodo_limpio, 'movil') !== false || strpos($metodo_limpio, 'biopago') !== false || strpos($metodo_limpio, 'punto') !== false);
                                    
                                    if ($es_gasto || $es_ingreso) {
                                        $ingreso_usd = $mov['monto_usd_calc']; $ingreso_bs = $mov['monto_bs_calc'];
                                    } elseif ($es_abono) {
                                        $ingreso_usd = $mov['abono_usd']; $ingreso_bs = $mov['abono_bs'];
                                    } else {
                                        $tasa = floatval($mov['tasa_aplicada'] > 0 ? $mov['tasa_aplicada'] : 36.50);
                                        if (strpos($metodo_limpio, 'credito_') === 0) {
                                            $ingreso_usd = $mov['abono_usd']; $ingreso_bs = $mov['abono_usd'] * $tasa;
                                        } elseif ($metodo_limpio == 'credito') {
                                            $ingreso_usd = 0; $ingreso_bs = 0;
                                        } else {
                                            $ingreso_usd = $mov['total_usd']; $ingreso_bs = $mov['total_bs'];
                                        }
                                    }
                                ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; <?php echo ($es_abono && $metodo_limpio == 'descuento') ? 'background-color: #f3e8ff;' : ($es_abono ? 'background-color: #f8fafc;' : ''); echo $es_gasto ? 'background-color: #fef2f2;' : ''; echo $es_ingreso ? 'background-color: #f0fdf4;' : ''; ?>">
                                        <td style="font-weight: bold; text-align: center;">
                                            <?php if ($es_gasto): ?>
                                                <div style="display:flex; gap: 5px; justify-content: center;">
                                                    <button onclick='abrirEditarGasto(<?php echo json_encode($mov); ?>)' style="padding: 0.2rem 0.5rem; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;">✏️</button>
                                                    <a href="acciones_gasto.php?eliminar=<?php echo $mov['id']; ?>" onclick="return confirm('¿Seguro que deseas eliminar este gasto? El dinero volverá a sumarse a la caja.')" style="padding: 0.2rem 0.5rem; background: #ef4444; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem; display:flex; align-items:center;">🗑️</a>
                                                </div>
                                            <?php elseif ($es_ingreso): ?>
                                                <div style="display:flex; gap: 5px; justify-content: center;">
                                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?eliminar_ingreso=<?php echo $mov['id']; ?>" onclick="return confirm('¿Seguro que deseas eliminar este fondo inicial? El dinero se restará de la caja.')" style="padding: 0.2rem 0.5rem; background: #ef4444; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem; display:flex; align-items:center;" title="Eliminar Ingreso">🗑️</a>
                                                </div>
                                            <?php elseif ($es_abono): ?>
                                                <div style="display:flex; gap: 5px; justify-content: center; align-items: center;">
                                                    <?php if ($metodo_limpio == 'descuento'): ?>
                                                        <div style="color: #7e22ce; background: #f3e8ff; padding: 0.3rem 0.6rem; border-radius: 4px; border: 1px solid #d8b4fe; display: inline-block; font-size: 0.85rem; font-weight: bold;">
                                                            📉 Descuento
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="color: #10b981; background: #d1fae5; padding: 0.3rem 0.6rem; border-radius: 4px; border: 1px solid #a7f3d0; display: inline-block; font-size: 0.85rem; font-weight: bold;">
                                                            💰 Abono
                                                        </div>
                                                    <?php endif; ?>
                                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?eliminar_abono=<?php echo $mov['abonos_ids']; ?>" 
                                                       onclick="return confirm('⚠️ ¿Estás seguro de ELIMINAR este registro?\n\nSi eliminas este registro:\n1. El dinero (o descuento) se restará de la caja.\n2. La deuda de $<?php echo number_format($mov['abono_usd'], 2); ?> se le sumará nuevamente al crédito.')" 
                                                       style="padding: 0.3rem 0.5rem; background: #ef4444; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem; display:flex; align-items:center;" 
                                                       title="Reversar Abono">🗑️</a>
                                                </div>
                                            <?php else: ?>
                                                <div style="display:flex; flex-direction:column; gap: 5px; align-items: center;">
                                                    <a href="imprimir_ticket.php?id=<?php echo $mov['ticket_id']; ?>" target="_blank" style="text-decoration: none; color: #3b82f6; background: #eff6ff; padding: 0.3rem 0.6rem; border-radius: 4px; border: 1px solid #bfdbfe; display: inline-flex; align-items: center; gap: 5px; width: 100%; justify-content: center;">
                                                        🖨️ #<?php echo str_pad($mov['ticket_id'], 5, "0", STR_PAD_LEFT); ?>
                                                    </a>
                                                    <div style="display:flex; gap: 5px; width: 100%;">
                                                        <button onclick='abrirEditarVenta(<?php echo htmlspecialchars(json_encode($mov), ENT_QUOTES, 'UTF-8'); ?>)' style="flex:1; padding: 0.2rem; background: #f59e0b; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" title="Editar Pago">✏️</button>
                                                        <a href="acciones_venta.php?accion=eliminar&id=<?php echo $mov['ticket_id']; ?>" onclick="return confirm('⚠️ ¿ESTÁS SEGURO? Esta acción ANULARÁ la factura por completo y devolverá los productos al inventario.')" style="flex:1; padding: 0.2rem; background: #ef4444; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem; text-align: center;" title="Anular Venta">🗑️</a>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--texto-secundario); font-size: 0.85rem;"><?php echo date('d/m/Y h:i A', strtotime($mov['fecha'])); ?></td>
                                        <td style="font-size: 0.9rem;"><?php echo htmlspecialchars($mov['vendedor']); ?></td>
                                        
                                        <td class="detalle-productos">
                                            <?php if ($es_gasto || $es_ingreso): ?>
                                                <strong><?php echo htmlspecialchars($mov['descripcion']); ?></strong>
                                            <?php else: ?>
                                                <?php echo $mov['detalle_productos']; ?>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td style="font-size: 0.9rem;">
                                            <?php 
                                            if ($es_gasto) {
                                                echo "<span style='font-weight:bold; color: #ef4444;'>GASTO (" . strtoupper(str_replace('_', ' ', $mov['metodo_pago'])) . ")</span>";
                                            } elseif ($es_ingreso) {
                                                echo "<span style='font-weight:bold; color: #10b981;'>FONDO CAJA (" . strtoupper(str_replace('_', ' ', $mov['metodo_pago'])) . ")</span>";
                                            } elseif ($es_abono) {
                                                $real = strtoupper(str_replace(['credito_', '_'], ['', ' '], $mov['metodo_pago']));
                                                
                                                if ($mov['metodo_pago'] == 'credito_trueque') {
                                                    echo "<span style='font-weight:bold; color: #d97706;'>📦 ABONO (TRUEQUE)</span>";
                                                } elseif ($mov['metodo_pago'] == 'descuento') {
                                                    echo "<span style='font-weight:bold; color: #7e22ce;'>📉 ABONO (DESCUENTO)</span><br><small style='color: #a855f7;'>Descuento aplicado por pago</small>";
                                                } else {
                                                    echo "<span style='font-weight:bold; color: #10b981;'>ABONO (" . $real . ")</span>";
                                                }
                                                if (!empty($mov['referencia'])) echo "<br><small style='color: #3b82f6; font-weight:bold;'>" . htmlspecialchars($mov['referencia']) . "</small>";
                                                
                                            } else {
                                                if ($metodo_limpio == 'mixto' || $metodo_limpio == 'credito_mixto') {
                                                    if ($metodo_limpio == 'credito_mixto') echo "<span style='font-weight:bold; color: #ef4444;'>CRÉDITO (ABONO MIXTO)</span>";
                                                    else echo "<span style='font-weight:bold; color: #8b5cf6;'>🔄 PAGO MIXTO</span>";
                                                    
                                                    if (isset($mov['mixto_usd']) && floatval($mov['mixto_usd']) > 0) echo "<br><small style='color:#166534;'>💵 Efec $: " . number_format($mov['mixto_usd'], 2) . "</small>";
                                                    if (isset($mov['mixto_efec_bs']) && floatval($mov['mixto_efec_bs']) > 0) echo "<br><small style='color:#1e3a8a;'>💵 Efec Bs: " . number_format($mov['mixto_efec_bs'], 2, ',', '.') . "</small>";
                                                    if (isset($mov['mixto_pm_bs']) && floatval($mov['mixto_pm_bs']) > 0) echo "<br><small style='color:#3b82f6;'>📱 PM: " . number_format($mov['mixto_pm_bs'], 2, ',', '.') . "</small>";
                                                    if (isset($mov['mixto_pv_bs']) && floatval($mov['mixto_pv_bs']) > 0) echo "<br><small style='color:#475569;'>💳 PV: " . number_format($mov['mixto_pv_bs'], 2, ',', '.') . "</small>";
                                                    if (isset($mov['mixto_bp_bs']) && floatval($mov['mixto_bp_bs']) > 0) echo "<br><small style='color:#2563eb;'>👆 Bio: " . number_format($mov['mixto_bp_bs'], 2, ',', '.') . "</small>";
                                                    if (isset($mov['mixto_otro_usd']) && floatval($mov['mixto_otro_usd']) > 0) {
                                                        $detalle_ext = !empty($mov['mixto_otro_detalle']) ? " (" . htmlspecialchars($mov['mixto_otro_detalle']) . ")" : "";
                                                        echo "<br><small style='color:#d97706;'>💳 Otro $" . $detalle_ext . ": " . number_format($mov['mixto_otro_usd'], 2) . "</small>";
                                                    }
                                                    if (!empty($mov['referencia'])) echo "<br><small style='color: #3b82f6; font-weight:bold;'>Ref: " . htmlspecialchars($mov['referencia']) . "</small>";
                                                    
                                                } elseif (strpos($metodo_limpio, 'credito_') === 0) {
                                                    $real = strtoupper(str_replace(['credito_', '_'], ['', ' '], $mov['metodo_pago']));
                                                    echo "<span style='font-weight:bold; color: #ef4444;'>CRÉDITO</span><br><small style='color: #10b981; font-weight:bold;'>Abonó Inicial en " . $real . "</small>";
                                                } elseif ($metodo_limpio == 'credito') {
                                                    echo "<span style='font-weight:bold; color: #ef4444;'>CRÉDITO TOTAL</span>";
                                                } elseif (strpos($metodo_limpio, 'otro_') === 0) {
                                                    $real_otro = strtoupper(str_replace('otro_', '', $metodo_limpio));
                                                    echo "<span style='font-weight:bold; color: #d97706;'>OTRO ($real_otro)</span>";
                                                } else {
                                                    echo "<span style='font-weight:bold;'>" . strtoupper(str_replace('_', ' ', $mov['metodo_pago'])) . "</span>";
                                                    if (!empty($mov['referencia'])) echo "<br><small style='color: #3b82f6; font-weight:bold;'>Ref: " . htmlspecialchars($mov['referencia']) . "</small>";
                                                    
                                                    // NUEVO: CONTROL ESTRICTO PARA VUELTO Y BILLETE SÓLO EN EFECTIVO
                                                    $vuelto_usd = floatval($mov['vuelto_entregado_usd'] ?? 0);
                                                    $vuelto_bs = floatval($mov['vuelto_entregado_bs'] ?? 0);
                                                    $recibido_usd_db = floatval($mov['billete_recibido_usd'] ?? 0);
                                                    
                                                    $mostrar_caja_vuelto = false;
                                                    if ($vuelto_usd > 0 || $vuelto_bs > 0) $mostrar_caja_vuelto = true;
                                                    if (($metodo_limpio == 'efectivo_usd' || $metodo_limpio == 'efectivo_bs') && $recibido_usd_db > 0) $mostrar_caja_vuelto = true;
                                                    
                                                    if ($mostrar_caja_vuelto) {
                                                        echo "<div style='margin-top: 5px; padding: 4px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px; font-size: 0.75rem; line-height: 1.3;'>";
                                                        
                                                        // Calculo Matemático para mostrar la realidad de la gaveta (Solo Efectivo)
                                                        if ($metodo_limpio == 'efectivo_bs') {
                                                            $tasa_v = floatval($mov['tasa_aplicada']);
                                                            $total_bs_v = floatval($mov['total_bs']);
                                                            $vueltos_en_bs = $vuelto_bs + ($vuelto_usd * $tasa_v);
                                                            $monto_recibido_real_bs = $total_bs_v + $vueltos_en_bs;
                                                            
                                                            if ($monto_recibido_real_bs > 0) {
                                                                echo "<strong style='color:#047857;'>Monto Recibido:</strong> " . number_format($monto_recibido_real_bs, 2, ',', '.') . " Bs<br>";
                                                            }
                                                        } elseif ($metodo_limpio == 'efectivo_usd') {
                                                            if ($recibido_usd_db > 0) {
                                                                echo "<strong style='color:#047857;'>Billete Recibido:</strong> $" . number_format($recibido_usd_db, 2) . "<br>";
                                                            }
                                                        }
                                                        
                                                        if ($vuelto_usd > 0 || $vuelto_bs > 0) {
                                                            echo "<strong style='color:#ef4444;'>Vuelto Dado:</strong> ";
                                                            $arr_vueltos = [];
                                                            if ($vuelto_usd > 0) {
                                                                $arr_vueltos[] = "$" . number_format($vuelto_usd, 2);
                                                            }
                                                            if ($vuelto_bs > 0) {
                                                                $nom_met_vuelto = !empty($mov['metodo_vuelto']) ? strtoupper(str_replace('_', ' ', $mov['metodo_vuelto'])) : strtoupper(str_replace('_', ' ', $mov['metodo_pago']));
                                                                $arr_vueltos[] = number_format($vuelto_bs, 2, ',', '.') . " Bs ($nom_met_vuelto)";
                                                            }
                                                            echo implode(" + ", $arr_vueltos);
                                                        }
                                                        echo "</div>";
                                                    }
                                                }
                                            }
                                            ?>
                                        </td>

                                        <td style="text-align: right; line-height: 1.4;">
                                            <?php if ($es_gasto): ?>
                                                <span style="font-weight: bold; font-size: 1.1rem; color: #dc2626;">- $<?php echo number_format($ingreso_usd, 2); ?></span><br>
                                                <small style="color: #ef4444; font-weight: bold;">- <?php echo number_format($ingreso_bs, 2, ',', '.'); ?> Bs</small>
                                            <?php elseif ($es_ingreso): ?>
                                                <span style="font-weight: bold; font-size: 1.1rem; color: #065f46;">+ $<?php echo number_format($ingreso_usd, 2); ?></span><br>
                                                <small style="color: #10b981; font-weight: bold;">+ <?php echo number_format($ingreso_bs, 2, ',', '.'); ?> Bs</small>
                                            <?php elseif ($es_abono && $metodo_limpio == 'descuento'): ?>
                                                <span style="font-weight: bold; font-size: 1.1rem; color: #7e22ce;">+ $<?php echo number_format($ingreso_usd, 2); ?></span><br>
                                                <small style="color: #94a3b8; font-weight: bold;">(No suma a caja)</small>
                                            <?php elseif ($ingreso_usd == 0 && $ingreso_bs == 0): ?>
                                                <span style="font-weight: bold; font-size: 1.1rem; color: #94a3b8;">$0.00</span><br>
                                                <small style="color: #94a3b8;">(Fiado Sin Abono)</small>
                                            <?php else: ?>
                                                <?php if ($metodo_limpio == 'mixto' || $metodo_limpio == 'credito_mixto'): ?>
                                                    <span style='font-weight:bold; font-size: 1.1rem; color: #065f46;'>+ $<?php echo number_format($ingreso_usd, 2); ?></span><br>
                                                    <small style="color: #64748b; font-weight: bold;">+ <?php echo number_format($ingreso_bs, 2, ',', '.'); ?> Bs</small>
                                                <?php elseif ($es_moneda_bs): ?>
                                                    <span style="font-weight: bold; font-size: 1.1rem; color: #1e3a8a;">+ <?php echo number_format($ingreso_bs, 2, ',', '.'); ?> Bs</span><br>
                                                    <small style="color: #64748b; font-weight: bold;">Ref: $<?php echo number_format($ingreso_usd, 2); ?></small>
                                                <?php else: ?>
                                                    <span style="font-weight: bold; font-size: 1.1rem; color: #065f46;">+ $<?php echo number_format($ingreso_usd, 2); ?></span><br>
                                                    <small style="color: #64748b; font-weight: bold;">+ <?php echo number_format($ingreso_bs, 2, ',', '.'); ?> Bs</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center; padding: 2rem;">No se encontraron movimientos registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Editar Pago Venta -->
    <div id="modalEditarVenta" class="modal-overlay">
        <div class="modal-caja" style="border-top: 5px solid #f59e0b;">
            <h2 style="margin-bottom: 0.5rem; color: var(--texto-principal);">✏️ Corregir Método de Pago</h2>
            <p style="font-size:0.85rem; color: #64748b; margin-bottom: 1rem; line-height: 1.3;">
                Cambia el método de pago o redistribuye los montos mixtos.
            </p>
            <form action="acciones_venta.php" method="POST">
                <input type="hidden" name="accion" value="editar_pago">
                <input type="hidden" name="venta_id" id="edit_venta_id">
                <input type="hidden" name="venta_tasa" id="edit_venta_tasa">
                <input type="hidden" name="venta_total_req" id="edit_venta_total_req">
                
                <div style="background: #f8fafc; padding: 0.8rem; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 1rem; text-align: center;">
                    <span style="font-size: 0.85rem; color: #64748b; font-weight: bold;">MONTO A JUSTIFICAR:</span><br>
                    <span id="label_monto_requerido" style="font-size: 1.3rem; font-weight: bold; color: #1e3a8a;">0.00 Bs</span>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">Método de Pago</label>
                    <select name="metodo_pago" id="edit_venta_metodo" onchange="togglePanelesEdicion()" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;">
                        <option value="efectivo_usd">💵 Efectivo USD (En Dólares)</option>
                        <option value="efectivo_bs">💵 Efectivo BS (En Bolívares)</option>
                        <option value="pago_movil">📱 Pago Móvil (En Bolívares)</option>
                        <option value="punto_venta">💳 Punto de Venta (En Bolívares)</option>
                        <option value="biopago">👆 Biopago (En Bolívares)</option>
                        <option value="otro">💳 Otros (Zelle/Bofa/Etc)</option>
                        <option value="mixto" style="font-weight: bold; color: #8b5cf6;">🔄 Pago Mixto (Redistribuir)</option>
                    </select>
                </div>

                <div id="panel_edicion_normal">
                    <div style="margin-bottom: 1.5rem;" id="caja_referencia_venta">
                        <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">N° de Referencia</label>
                        <input type="text" name="referencia" id="edit_venta_referencia" style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;" placeholder="Opcional / Obligatorio para PM">
                    </div>
                    
                    <div id="caja_vueltos_edicion" style="background: #f0fdf4; padding: 1rem; border-radius: 6px; border: 1px solid #bbf7d0; margin-bottom: 1.5rem; display: none;">
                        <h4 style="color: #166534; margin-top:0; margin-bottom: 0.8rem; text-align: center; font-size: 0.9rem;">💵 Detalle de Billetes y Vuelto</h4>
                        
                        <div style="margin-bottom: 0.5rem;" id="div_billete_usd">
                            <label style="font-size:0.8rem; font-weight:bold; color: #334155;">Billete Recibido ($)</label>
                            <input type="number" step="0.01" name="billete_recibido_usd" id="edit_billete_usd" style="width: 100%; padding: 0.5rem; border: 1px solid var(--borde); border-radius: 4px;" placeholder="Ej: 100">
                        </div>

                        <div style="margin-bottom: 0.5rem; display:none;" id="div_billete_bs">
                            <label style="font-size:0.8rem; font-weight:bold; color: #334155;">Monto Recibido (Bs)</label>
                            <input type="number" step="0.01" name="billete_recibido_bs" id="edit_billete_bs" style="width: 100%; padding: 0.5rem; border: 1px solid var(--borde); border-radius: 4px;" placeholder="Ej: 5000">
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-bottom: 0.5rem;">
                            <div style="flex:1;">
                                <label style="font-size:0.8rem; font-weight:bold; color: #334155;">Vuelto Dado ($)</label>
                                <input type="number" step="0.01" name="vuelto_entregado_usd" id="edit_vuelto_usd" style="width: 100%; padding: 0.5rem; border: 1px solid var(--borde); border-radius: 4px;" placeholder="Ej: 0">
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:0.8rem; font-weight:bold; color: #334155;">Vuelto Dado (Bs)</label>
                                <input type="number" step="0.01" name="vuelto_entregado_bs" id="edit_vuelto_bs" style="width: 100%; padding: 0.5rem; border: 1px solid var(--borde); border-radius: 4px;" placeholder="Ej: 9">
                            </div>
                        </div>
                        
                        <div>
                            <label style="font-size:0.8rem; font-weight:bold; color: #334155;">¿De dónde salió el vuelto en Bs?</label>
                            <select name="metodo_vuelto" id="edit_metodo_vuelto" style="width: 100%; padding: 0.5rem; border: 1px solid var(--borde); border-radius: 4px;">
                                <option value="">No aplica / Ninguno</option>
                                <option value="efectivo_bs">💵 Caja: Efectivo Bs</option>
                                <option value="pago_movil">📱 Banco: Pago Móvil</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="panel_edicion_mixta" style="display: none; background: #f0fdf4; padding: 1rem; border-radius: 6px; border: 1px solid #bbf7d0; margin-bottom: 1.5rem;">
                    <h4 style="color: #166534; margin-top:0; margin-bottom: 0.8rem; text-align: center; font-size: 0.9rem;">🧮 Nueva Distribución</h4>
                    <div class="fila-mixta"><label>💵 Efectivo ($):</label><input type="number" step="0.01" name="mix_usd" id="edit_mix_usd" class="input-edicion-mixta" placeholder="0.00"></div>
                    <div class="fila-mixta"><label>💵 Efectivo (Bs):</label><input type="number" step="0.01" name="mix_efec_bs" id="edit_mix_efec_bs" class="input-edicion-mixta" placeholder="0.00"></div>
                    <div class="fila-mixta"><label>📱 Pago Móvil (Bs):</label><input type="number" step="0.01" name="mix_pm_bs" id="edit_mix_pm_bs" class="input-edicion-mixta" placeholder="0.00"></div>
                    <div class="fila-mixta"><label>💳 P. Venta (Bs):</label><input type="number" step="0.01" name="mix_pv_bs" id="edit_mix_pv_bs" class="input-edicion-mixta" placeholder="0.00"></div>
                    <div class="fila-mixta"><label>👆 Biopago (Bs):</label><input type="number" step="0.01" name="mix_bp_bs" id="edit_mix_bp_bs" class="input-edicion-mixta" placeholder="0.00"></div>
                    
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                        <label style="font-weight: bold; font-size: 0.85rem; width: 45%;">💳 Otro ($):</label>
                        <input type="number" step="0.01" name="mix_otro_usd" id="edit_mix_otro_usd" class="input-edicion-mixta" placeholder="0.00" style="width: 25%; padding: 0.4rem; border: 1px solid var(--borde); border-radius: 4px;">
                        <input type="text" name="mix_otro_detalle" id="edit_mix_otro_detalle" placeholder="Ej: Zelle" style="width: 30%; padding: 0.4rem; border: 1px solid var(--borde); border-radius: 4px; font-size: 0.85rem;">
                    </div>

                    <div style="margin-top: 1rem; padding-top: 0.5rem; border-top: 1px dashed #4ade80;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 0.95rem;">
                            <span>Calculado: <span id="edit_mix_total_calculado" style="color: #1e3a8a;">0.00 Bs</span></span>
                            <span id="edit_mix_diferencia" style="color: #ef4444;">Falta: 0.00 Bs</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('modalEditarVenta').style.display='none'" style="padding: 0.8rem 1.5rem; border: none; border-radius: 5px; background: #e2e8f0; color: #475569; cursor: pointer; font-weight: bold;">Cancelar</button>
                    <button type="submit" id="btn_guardar_edicion" class="btn-login" style="margin: 0; padding: 0.8rem 1.5rem; background: #f59e0b;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modales de Gasto e Ingreso omitidos por limpieza visual -->
    <div id="modalIngreso" class="modal-overlay">
        <div class="modal-caja" style="border-top: 5px solid #10b981;">
            <h2 style="margin-bottom: 1rem; color: var(--texto-principal);">💰 Ingresar Fondo de Caja</h2>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                <input type="hidden" name="nuevo_ingreso" value="1">
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">Concepto</label>
                    <input type="text" name="descripcion" value="Dinero de inicio (Fondo de caja)" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">¿En qué formato entró?</label>
                    <select name="metodo_pago" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;">
                        <option value="efectivo_bs">💵 Efectivo BS (En Bolívares)</option>
                        <option value="efectivo_usd">💵 Efectivo USD (En Dólares)</option>
                        <option value="pago_movil">📱 Pago Móvil (En Bolívares)</option>
                        <option value="punto_venta">💳 Punto de Venta (En Bolívares)</option>
                    </select>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">Monto a Sumar en Caja</label>
                    <input type="number" step="0.01" name="monto" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;" placeholder="Monto crudo en su moneda">
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('modalIngreso').style.display='none'" style="padding: 0.8rem 1.5rem; border: none; border-radius: 5px; background: #e2e8f0; color: #475569; cursor: pointer; font-weight: bold;">Cancelar</button>
                    <button type="submit" class="btn-login" style="margin: 0; padding: 0.8rem 1.5rem; background: #10b981;">Añadir Dinero</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gastos -->
    <div id="modalGasto" class="modal-overlay">
        <div class="modal-caja" style="border-top: 5px solid #ef4444;">
            <h2 style="margin-bottom: 1rem; color: var(--texto-principal);">💸 Registrar Nuevo Gasto</h2>
            <form action="guardar_gasto.php" method="POST">
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">Concepto del Gasto</label>
                    <input type="text" name="descripcion" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;" placeholder="¿Qué se pagó?">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">¿De dónde salió el dinero?</label>
                    <select name="metodo_pago" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;">
                        <option value="efectivo_usd">💵 Efectivo USD (En Dólares)</option>
                        <option value="efectivo_bs">💵 Efectivo BS (En Bolívares)</option>
                        <option value="pago_movil">📱 Pago Móvil (En Bolívares)</option>
                        <option value="punto_venta">💳 Punto de Venta (En Bolívares)</option>
                        <option value="biopago">👆 Biopago (En Bolívares)</option>
                        <option value="otro">💳 Otros (En Dólares)</option>
                    </select>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">Monto a descontar</label>
                    <input type="number" step="0.01" name="monto" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;" placeholder="Monto crudo en su moneda">
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('modalGasto').style.display='none'" style="padding: 0.8rem 1.5rem; border: none; border-radius: 5px; background: #e2e8f0; color: #475569; cursor: pointer; font-weight: bold;">Cancelar</button>
                    <button type="submit" class="btn-login" style="margin: 0; padding: 0.8rem 1.5rem; background: #ef4444;">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulario de Edición de Gastos -->
    <div id="modalEditarGasto" class="modal-overlay">
        <div class="modal-caja" style="border-top: 5px solid #3b82f6;">
            <h2 style="margin-bottom: 1rem; color: var(--texto-principal);">✏️ Editar Gasto</h2>
            <form action="acciones_gasto.php" method="POST">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="gasto_id" id="edit_id">
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">Concepto del Gasto</label>
                    <input type="text" name="descripcion" id="edit_descripcion" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">¿De dónde salió el dinero?</label>
                    <select name="metodo_pago" id="edit_metodo" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;">
                        <option value="efectivo_usd">💵 Efectivo USD (En Dólares)</option>
                        <option value="efectivo_bs">💵 Efectivo BS (En Bolívares)</option>
                        <option value="pago_movil">📱 Pago Móvil (En Bolívares)</option>
                        <option value="punto_venta">💳 Punto de Venta (En Bolívares)</option>
                        <option value="biopago">👆 Biopago (En Bolívares)</option>
                        <option value="otro">💳 Otros (En Dólares)</option>
                    </select>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">Monto Corregido</label>
                    <input type="number" step="0.01" name="monto" id="edit_monto" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--borde); border-radius: 5px; box-sizing: border-box;">
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('modalEditarGasto').style.display='none'" style="padding: 0.8rem 1.5rem; border: none; border-radius: 5px; background: #e2e8f0; color: #475569; cursor: pointer; font-weight: bold;">Cancelar</button>
                    <button type="submit" class="btn-login" style="margin: 0; padding: 0.8rem 1.5rem; background: #3b82f6;">Actualizar Gasto</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirEditarVenta(mov) {
            document.getElementById('edit_venta_id').value = mov.ticket_id;
            document.getElementById('edit_venta_tasa').value = mov.tasa_aplicada;
            document.getElementById('edit_venta_referencia').value = mov.referencia || '';

            // CÁLCULO MATEMÁTICO DEL DINERO RECIBIDO PARA LA EDICIÓN
            let tasa = parseFloat(mov.tasa_aplicada);
            let totalBs = parseFloat(mov.total_bs);
            let vUsd = parseFloat(mov.vuelto_entregado_usd) || 0;
            let vBs = parseFloat(mov.vuelto_entregado_bs) || 0;
            
            // Recibido = Total + Vueltos
            let recibidoBsReal = totalBs + vBs + (vUsd * tasa);

            document.getElementById('edit_billete_usd').value = parseFloat(mov.billete_recibido_usd) > 0 ? mov.billete_recibido_usd : '';
            
            // Llenamos la celda de Bolívares con la suma exacta si hubo vueltos
            if (mov.metodo_pago.toLowerCase() === 'efectivo_bs' && (vUsd > 0 || vBs > 0)) {
                document.getElementById('edit_billete_bs').value = recibidoBsReal.toFixed(2);
            } else {
                document.getElementById('edit_billete_bs').value = parseFloat(mov.billete_recibido_usd) > 0 ? (parseFloat(mov.billete_recibido_usd) * tasa).toFixed(2) : '';
            }

            document.getElementById('edit_vuelto_usd').value = vUsd > 0 ? vUsd : '';
            document.getElementById('edit_vuelto_bs').value = vBs > 0 ? vBs : '';
            document.getElementById('edit_metodo_vuelto').value = mov.metodo_vuelto || '';

            let isCredito = mov.metodo_pago.toLowerCase().includes('credito');
            let montoUsd = isCredito ? parseFloat(mov.abono_usd) : parseFloat(mov.total_usd);
            let montoBsReq = Math.round((montoUsd * tasa) * 100) / 100;
            
            document.getElementById('edit_venta_total_req').value = montoBsReq;
            document.getElementById('label_monto_requerido').innerText = montoBsReq.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' Bs ($' + montoUsd.toFixed(2) + ')';

            let met = mov.metodo_pago.toLowerCase().replace('credito_', '');
            let selectMetodo = document.getElementById('edit_venta_metodo');
            
            if (met === 'mixto') selectMetodo.value = 'mixto';
            else if (Array.from(selectMetodo.options).some(opt => opt.value === met)) selectMetodo.value = met;
            else if(met.includes('otro')) selectMetodo.value = 'otro';
            else selectMetodo.value = 'efectivo_usd';

            document.getElementById('edit_mix_usd').value = mov.mixto_usd > 0 ? mov.mixto_usd : '';
            document.getElementById('edit_mix_efec_bs').value = mov.mixto_efec_bs > 0 ? mov.mixto_efec_bs : '';
            document.getElementById('edit_mix_pm_bs').value = mov.mixto_pm_bs > 0 ? mov.mixto_pm_bs : '';
            document.getElementById('edit_mix_pv_bs').value = mov.mixto_pv_bs > 0 ? mov.mixto_pv_bs : '';
            document.getElementById('edit_mix_bp_bs').value = mov.mixto_bp_bs > 0 ? mov.mixto_bp_bs : '';
            document.getElementById('edit_mix_otro_usd').value = mov.mixto_otro_usd > 0 ? mov.mixto_otro_usd : '';
            document.getElementById('edit_mix_otro_detalle').value = mov.mixto_otro_detalle || '';

            togglePanelesEdicion();
            document.getElementById('modalEditarVenta').style.display = 'flex';
        }

        function togglePanelesEdicion() {
            let select = document.getElementById('edit_venta_metodo').value;
            let panelNormal = document.getElementById('panel_edicion_normal');
            let panelMixto = document.getElementById('panel_edicion_mixta');
            let cajaVueltos = document.getElementById('caja_vueltos_edicion');
            
            if (select === 'mixto') {
                panelNormal.style.display = 'none';
                panelMixto.style.display = 'block';
                if(cajaVueltos) cajaVueltos.style.display = 'none';
                calcularEdicionMixta();
            } else {
                panelNormal.style.display = 'block';
                panelMixto.style.display = 'none';
                
                // Mostrar Panel de Vueltos si aplica
                if (select === 'efectivo_usd' || select === 'efectivo_bs') {
                    if(cajaVueltos) cajaVueltos.style.display = 'block';
                    if(select === 'efectivo_bs') {
                        document.getElementById('div_billete_usd').style.display = 'none';
                        document.getElementById('div_billete_bs').style.display = 'block';
                    } else {
                        document.getElementById('div_billete_usd').style.display = 'block';
                        document.getElementById('div_billete_bs').style.display = 'none';
                    }
                } else {
                    if(cajaVueltos) cajaVueltos.style.display = 'none';
                }
                
                document.getElementById('btn_guardar_edicion').disabled = false;
                document.getElementById('btn_guardar_edicion').style.opacity = '1';
                document.getElementById('edit_mix_diferencia').innerText = "Falta: 0,00 Bs";
            }
        }

        function calcularEdicionMixta() {
            let select = document.getElementById('edit_venta_metodo').value;
            if (select !== 'mixto') return;

            let btnSubmit = document.getElementById('btn_guardar_edicion');
            let totalReqBs = parseFloat(document.getElementById('edit_venta_total_req').value);
            let tasa = parseFloat(document.getElementById('edit_venta_tasa').value);
            
            let mUsd = parseFloat(document.getElementById('edit_mix_usd').value) || 0;
            let mEfBs = parseFloat(document.getElementById('edit_mix_efec_bs').value) || 0;
            let mPmBs = parseFloat(document.getElementById('edit_mix_pm_bs').value) || 0;
            let mPvBs = parseFloat(document.getElementById('edit_mix_pv_bs').value) || 0;
            let mBpBs = parseFloat(document.getElementById('edit_mix_bp_bs').value) || 0;
            let mOtroUsd = parseFloat(document.getElementById('edit_mix_otro_usd').value) || 0;

            let sumaBs = Math.round(((mUsd * tasa) + mEfBs + mPmBs + mPvBs + mBpBs + (mOtroUsd * tasa)) * 100) / 100;
            document.getElementById('edit_mix_total_calculado').innerText = sumaBs.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' Bs';
            
            let dif = Math.round((totalReqBs - sumaBs) * 100) / 100;
            let labelDif = document.getElementById('edit_mix_diferencia');

            if (dif > 0.5) {
                labelDif.innerText = "Falta: " + dif.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}) + " Bs ($" + (dif/tasa).toFixed(2) + ")";
                labelDif.style.color = '#ef4444';
                btnSubmit.disabled = true; btnSubmit.style.opacity = '0.5';
            } else if (dif < -0.5) {
                let sobran = Math.abs(dif);
                labelDif.innerText = "Sobra: " + sobran.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}) + " Bs ($" + (sobran/tasa).toFixed(2) + ")";
                labelDif.style.color = '#ef4444';
                btnSubmit.disabled = true; btnSubmit.style.opacity = '0.5';
            } else {
                labelDif.innerText = "¡Cuadre Exacto! ✅";
                labelDif.style.color = '#10b981';
                btnSubmit.disabled = false; btnSubmit.style.opacity = '1';
            }
        }

        document.querySelectorAll('.input-edicion-mixta').forEach(inp => inp.addEventListener('input', calcularEdicionMixta));

        function abrirEditarGasto(datos) {
            document.getElementById('edit_id').value = datos.id;
            document.getElementById('edit_descripcion').value = datos.descripcion;
            document.getElementById('edit_monto').value = datos.monto;
            document.getElementById('edit_metodo').value = datos.metodo_pago;
            document.getElementById('modalEditarGasto').style.display = 'flex';
        }

        const ctx = document.getElementById('graficaVentas').getContext('2d');
        const meses = <?php echo json_encode($meses_nombres); ?>;
        const datos_usd = <?php echo json_encode($datos_ventas); ?>;

        new Chart(ctx, {
            type: 'line',
            data: { labels: meses, datasets: [{ label: 'Ingresos Mensuales (USD)', data: datos_usd, borderColor: '#0284c7', backgroundColor: 'rgba(2, 132, 199, 0.1)', borderWidth: 3, pointBackgroundColor: '#10b981', pointRadius: 5, fill: true, tension: 0.3 }] },
            options: { responsive: true, plugins: { legend: { position: 'top' } } }
        });
    </script>
</body>
</html>
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-08-2026 a las 23:35:43
-- Versión del servidor: 10.4.28-MariaDB
-- Versión de PHP: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `punto_venta`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos_proveedores`
--

CREATE TABLE `abonos_proveedores` (
  `id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `monto_usd` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `referencia` varchar(50) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `moneda_pago` varchar(10) DEFAULT 'usd',
  `monto_moneda` decimal(12,2) DEFAULT NULL,
  `tasa_cambio` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `abonos_proveedores`
--

INSERT INTO `abonos_proveedores` (`id`, `factura_id`, `monto_usd`, `metodo_pago`, `referencia`, `fecha`, `moneda_pago`, `monto_moneda`, `tasa_cambio`) VALUES
(1, 4, 55.56, 'pago_movil', '1222', '2026-08-08 17:25:14', 'bs', 50000.00, 900.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `cedula`, `nombre`, `telefono`) VALUES
(1, '20408381', 'Gabriel Vielma', '04145021471'),
(2, '11395146', 'ZULAY CAMACHO', '04145021471'),
(6, '26959512', 'Yulia Viera', '04145021471'),
(10, '1822550', 'Jhonson Flores', '04245325347'),
(11, '29559523', 'jesson mergarejo', '04145642624'),
(14, '20012188', 'Danni Jimenez', '04122948018'),
(16, '1139512', 'Guillermina Camacho', '0414');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `combos`
--

CREATE TABLE `combos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `combo_detalles`
--

CREATE TABLE `combo_detalles` (
  `id` int(11) NOT NULL,
  `combo_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `combo_detalles`
--

INSERT INTO `combo_detalles` (`id`, `combo_id`, `producto_id`, `cantidad`) VALUES
(1, 9, 6, 2.00),
(2, 9, 8, 1.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL,
  `tasa_dia` decimal(10,2) NOT NULL,
  `tasa_euro` decimal(10,2) NOT NULL DEFAULT 1.08,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `tasa_dia`, `tasa_euro`, `ultima_actualizacion`) VALUES
(8, 800.00, 780.00, '2026-07-27 17:07:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `precio_unitario_usd` decimal(10,2) NOT NULL,
  `subtotal_usd` decimal(10,2) NOT NULL,
  `precio_costo` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`, `precio_unitario_usd`, `subtotal_usd`, `precio_costo`) VALUES
(6, 6, 1, 5.000, 1.00, 5.00, 0.00, 0.00, 0.00),
(36, 36, 1, 6.000, 1.00, 6.00, 0.00, 0.00, 0.00),
(37, 37, 1, 6.000, 1.00, 6.00, 0.00, 0.00, 0.00),
(38, 38, 1, 8.000, 1.00, 8.00, 0.00, 0.00, 0.00),
(53, 53, 1, 8.000, 1.00, 8.00, 0.00, 0.00, 0.00),
(54, 54, 1, 8.000, 1.00, 8.00, 0.00, 0.00, 0.00),
(55, 55, 1, 3.000, 1.00, 3.00, 0.00, 0.00, 0.00),
(56, 56, 1, 5.000, 1.00, 5.00, 0.00, 0.00, 0.00),
(57, 57, 3, 2.000, 10.00, 20.00, 0.00, 0.00, 0.00),
(58, 58, 1, 1.000, 1.00, 1.00, 0.00, 0.00, 0.00),
(59, 58, 3, 1.000, 10.00, 10.00, 0.00, 0.00, 0.00),
(60, 59, 3, 1.000, 10.00, 10.00, 0.00, 0.00, 0.00),
(61, 60, 1, 1.000, 1.00, 1.00, 0.00, 0.00, 0.00),
(62, 61, 1, 1.000, 1.00, 1.00, 0.00, 0.00, 0.00),
(65, 64, 1, 3.000, 1.00, 3.00, 0.00, 0.00, 0.00),
(66, 65, 1, 5.000, 1.00, 5.00, 0.00, 0.00, 0.00),
(67, 66, 1, 7.000, 1.00, 7.00, 0.00, 0.00, 0.00),
(68, 67, 1, 7.000, 1.00, 7.00, 0.00, 0.00, 0.00),
(70, 69, 1, 5.000, 1.00, 5.00, 0.00, 0.00, 0.00),
(71, 70, 1, 50.000, 1.00, 50.00, 0.00, 0.00, 0.00),
(72, 71, 1, 90.000, 1.00, 90.00, 0.00, 0.00, 0.00),
(73, 72, 1, 7.000, 1.00, 7.00, 0.00, 0.00, 0.00),
(74, 73, 1, 5.000, 1.00, 5.00, 0.00, 0.00, 0.00),
(75, 74, 6, 5.000, 6.00, 30.00, 0.00, 0.00, 0.00),
(76, 75, 1, 10.000, 1.00, 10.00, 0.00, 0.00, 0.00),
(78, 77, 8, 10.000, 2.50, 25.00, 0.00, 0.00, 0.00),
(79, 78, 8, 20.000, 2.50, 50.00, 0.00, 0.00, 0.00),
(80, 79, 9, 1.000, 10.00, 10.00, 0.00, 0.00, 0.00),
(81, 80, 1, 5.000, 1.00, 5.00, 0.00, 0.00, 0.00),
(82, 81, 1, 1.000, 1.00, 1.00, 0.00, 0.00, 0.00),
(83, 82, 1, 10.000, 1.00, 10.00, 0.00, 0.00, 0.00),
(84, 83, 4, 5.000, 3.00, 15.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas_proveedores`
--

CREATE TABLE `facturas_proveedores` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `num_factura` varchar(50) NOT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `total_usd` decimal(10,2) NOT NULL,
  `deuda_usd` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `facturas_proveedores`
--

INSERT INTO `facturas_proveedores` (`id`, `proveedor_id`, `num_factura`, `fecha_emision`, `fecha_vencimiento`, `total_usd`, `deuda_usd`) VALUES
(1, 3, '12345', '2026-07-08', '2026-07-02', 650.00, 650.00),
(2, 3, '45627', '2026-07-08', '2026-08-08', 323.00, 323.00),
(3, 2, '56373', '2026-07-08', '2026-08-08', 2348.00, 2348.00),
(4, 1, '1562', '2026-07-11', '2026-07-11', 600.00, 382.71);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `familias`
--

CREATE TABLE `familias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `familias`
--

INSERT INTO `familias` (`id`, `nombre`) VALUES
(18, 'Repuestos y Maquinaria'),
(19, 'Semillas y Abonos'),
(16, 'Venenos y Herbicidas'),
(20, 'viveres');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos`
--

CREATE TABLE `gastos` (
  `id` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `gastos`
--

INSERT INTO `gastos` (`id`, `descripcion`, `monto`, `metodo_pago`, `fecha`, `usuario_id`) VALUES
(3, 'Agua', 2.00, 'efectivo_usd', '2026-07-23 14:31:01', 1),
(4, 'Queso', 3000.00, 'efectivo_bs', '2026-07-23 14:31:31', 1),
(6, 'Agua', 2.00, 'efectivo_usd', '2026-07-27 08:33:46', 1),
(7, 'Compra a caracas', 20000.00, 'efectivo_bs', '2026-07-29 11:02:16', 1),
(8, 'Agua', 5.00, 'efectivo_usd', '2026-08-08 17:26:57', 1),
(9, 'Caletero', 1000.00, 'efectivo_bs', '2026-08-15 17:06:45', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historico_abonos`
--

CREATE TABLE `historico_abonos` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `monto_usd` decimal(10,2) NOT NULL,
  `monto_bs` decimal(10,2) DEFAULT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `referencia` varchar(50) DEFAULT NULL,
  `tasa_pago` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historico_abonos`
--

INSERT INTO `historico_abonos` (`id`, `venta_id`, `usuario_id`, `monto_usd`, `monto_bs`, `metodo_pago`, `fecha`, `referencia`, `tasa_pago`) VALUES
(5, 6, 1, 2.14, 1500.00, 'punto_venta', '2026-07-10 07:36:00', '', 700.22),
(6, 6, 1, 3.00, 0.00, 'credito_efectivo_usd', '2026-07-10 07:36:56', '', 700.22),
(7, 6, 1, 6.86, 4803.51, 'credito_punto_venta', '2026-07-10 07:36:56', '', 700.22),
(19, 69, 1, 5.00, 0.00, 'credito_zelle', '2026-07-23 14:41:22', '', 500.00),
(23, 70, 1, 1.00, 0.00, 'credito_efectivo_usd', '2026-07-24 10:03:31', '', 500.00),
(24, 70, 1, 2.00, 1000.00, 'credito_efectivo_bs', '2026-07-24 10:03:31', '', 500.00),
(25, 70, 1, 2.00, 1000.00, 'credito_pago_movil', '2026-07-24 10:03:31', '', 500.00),
(26, 70, 1, 2.00, 1000.00, 'credito_punto_venta', '2026-07-24 10:03:31', '', 500.00),
(27, 70, 1, 2.00, 1000.00, 'credito_biopago', '2026-07-24 10:03:31', '', 500.00),
(28, 70, 1, 20.00, 0.00, 'credito_trueque', '2026-07-24 10:03:31', 'Detalle: Cartones ', 500.00),
(35, 70, 1, 1.00, 0.00, 'credito_efectivo_usd', '2026-07-27 08:57:32', '', 500.00),
(36, 70, 1, 0.20, 100.00, 'credito_efectivo_bs', '2026-07-27 08:57:32', '', 500.00),
(37, 70, 1, 0.20, 100.00, 'credito_pago_movil', '2026-07-27 08:57:32', '5432', 500.00),
(38, 70, 1, 0.20, 100.00, 'credito_punto_venta', '2026-07-27 08:57:32', '5432', 500.00),
(40, 70, 1, 19.20, 0.00, 'credito_trueque', '2026-07-27 08:57:32', 'Detalle: Cambio por queso', 500.00),
(44, 75, 1, 2.00, 1000.00, 'efectivo_usd', '2026-07-27 08:50:16', '', 500.00),
(45, 75, 1, 0.20, 100.00, 'efectivo_bs', '2026-07-27 08:50:16', '', 500.00),
(46, 75, 1, 0.20, 100.00, 'pago_movil', '2026-07-27 08:50:16', '123', 500.00),
(47, 75, 1, 0.40, 200.00, 'punto_venta', '2026-07-27 08:50:16', '', 500.00),
(48, 75, 1, 1.00, 500.00, 'otro_zelle', '2026-07-27 08:50:16', '', 500.00),
(49, 78, 1, 10.00, 8000.00, 'efectivo_usd', '2026-07-29 11:06:15', '', 800.00),
(50, 78, 1, 6.25, 5000.00, 'pago_movil', '2026-07-29 11:06:15', '4567', 800.00),
(51, 78, 1, 6.25, 5000.00, 'credito_punto_venta', '2026-07-29 11:11:48', '', 800.00),
(53, 78, 1, 0.50, 400.00, 'credito_efectivo_bs', '2026-07-29 15:10:46', '', 800.00),
(54, 79, 1, 2.00, 1600.00, 'efectivo_usd', '2026-08-08 17:23:13', '', 800.00),
(55, 70, 1, 0.20, 0.00, 'credito_efectivo_usd', '2026-08-08 17:24:15', '', 800.00),
(56, 83, 1, 2.00, 1600.00, 'efectivo_usd', '2026-08-15 17:29:14', '', 800.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingresos_caja`
--

CREATE TABLE `ingresos_caja` (
  `id` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ingresos_caja`
--

INSERT INTO `ingresos_caja` (`id`, `fecha`, `usuario_id`, `descripcion`, `metodo_pago`, `monto`) VALUES
(4, '2026-07-15 22:52:30', 1, 'ingreso de caja chika', 'efectivo_bs', 5000.00),
(7, '2026-07-17 13:02:14', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_bs', 5000.00),
(8, '2026-07-17 13:02:27', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_usd', 10.00),
(9, '2026-07-20 12:39:32', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_bs', 2000.00),
(10, '2026-07-20 12:39:44', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_usd', 5.00),
(11, '2026-07-23 14:26:36', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_bs', 2000.00),
(12, '2026-07-23 14:26:49', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_usd', 10.00),
(13, '2026-07-27 08:30:50', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_bs', 5000.00),
(14, '2026-07-27 08:31:04', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_usd', 10.00),
(15, '2026-07-27 08:31:19', 1, 'Dinero de inicio (Fondo de caja)', 'pago_movil', 3000.00),
(16, '2026-07-29 11:00:27', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_bs', 4000.00),
(17, '2026-07-29 11:00:45', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_usd', 10.00),
(18, '2026-08-08 17:26:38', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_bs', 5000.00),
(19, '2026-08-15 17:05:04', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_bs', 5000.00),
(20, '2026-08-15 17:05:16', 1, 'Dinero de inicio (Fondo de caja)', 'efectivo_usd', 10.00),
(21, '2026-08-15 17:05:29', 1, 'Dinero de inicio (Fondo de caja)', 'pago_movil', 2000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste_manual','venta') NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `observacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos_inventario`
--

INSERT INTO `movimientos_inventario` (`id`, `producto_id`, `usuario_id`, `tipo_movimiento`, `cantidad`, `fecha`, `observacion`) VALUES
(1, 1, 1, 'entrada', 1.000, '2026-07-11 09:11:40', ''),
(2, 8, 1, 'entrada', 10.000, '2026-07-27 13:05:59', ''),
(3, 7, 1, 'entrada', 100.000, '2026-07-27 13:06:14', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `codigo_barras` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio_usd` decimal(10,2) NOT NULL,
  `tipo_unidad` varchar(15) DEFAULT 'und',
  `stock` decimal(10,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` decimal(10,2) NOT NULL DEFAULT 5.00,
  `fecha_vencimiento` date DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `precio_mayor_bcv` decimal(10,2) DEFAULT 0.00,
  `precio_efectivo_detal` decimal(10,2) DEFAULT 0.00,
  `precio_efectivo_mayor` decimal(10,2) DEFAULT 0.00,
  `familia_id` int(11) DEFAULT NULL,
  `precio_costo` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo_barras`, `nombre`, `precio_usd`, `tipo_unidad`, `stock`, `stock_minimo`, `fecha_vencimiento`, `proveedor_id`, `precio_mayor_bcv`, `precio_efectivo_detal`, `precio_efectivo_mayor`, `familia_id`, `precio_costo`) VALUES
(1, '001', 'CACAO EN POLVO 100GR', 1.00, 'und', 13858.000, 100.00, NULL, 1, 0.90, 0.85, 0.50, 20, 0.10),
(2, '002', 'CACAO EN POLVO 500GR', 2.50, 'und', 11946.000, 5.00, NULL, NULL, 0.00, 0.00, 2.00, NULL, 0.00),
(3, '003', 'TORTA CHOCOLATE 1KL', 10.00, 'und', 489.000, 5.00, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00),
(4, '004', 'SEMILLAS DE CACAO SECA 1 KL ', 3.00, 'und', 1983.000, 5.00, NULL, NULL, 0.00, 0.00, 2.50, NULL, 0.00),
(5, '005', 'GALLETAS DE CHOCOLATE 20', 1.00, 'und', 9996.000, 5.00, NULL, NULL, 0.00, 0.00, 0.80, NULL, 0.00),
(6, '00456', 'Arroz mari', 6.00, 'kg', 12.000, 20.00, NULL, 1, 5.00, 4.00, 3.00, 18, 2.00),
(7, '105', 'QUESO RAYADO', 1.00, 'und', 200.000, 1.00, '2026-08-05', 1, 1.00, 1.00, 0.50, 18, 2.00),
(8, '987654321', 'Toston tom 80gr', 2.50, 'und', 11.000, 16.00, '2026-08-27', NULL, 0.00, 0.00, 0.00, NULL, 0.87),
(9, 'CMB-13913', 'COMBO: Como Perro', 10.00, 'und', 9999.000, 0.00, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `empresa` varchar(100) NOT NULL,
  `vendedor` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `empresa`, `vendedor`, `telefono`) VALUES
(1, 'Domosa', 'CARLOS VIELMA', '04145021471');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` enum('admin','vendedor','almacenista') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `clave`, `rol`, `estado`) VALUES
(1, '(Admin)', 'admin', '123456', 'admin', 'activo'),
(2, 'Vendedor 1', 'ventas1', '123', 'vendedor', 'activo'),
(3, 'Almacén Principal', 'almacen1', '123456', 'almacenista', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tasa_aplicada` decimal(10,2) NOT NULL,
  `total_usd` decimal(10,2) NOT NULL,
  `total_bs` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `billete_recibido_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vuelto_entregado_bs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vuelto_entregado_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `abono_usd` decimal(10,2) DEFAULT 0.00,
  `deuda_usd` decimal(10,2) DEFAULT 0.00,
  `observaciones` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `referencia` varchar(50) DEFAULT NULL,
  `foto_evidencia` varchar(255) DEFAULT NULL,
  `mixto_usd` decimal(10,2) DEFAULT 0.00,
  `mixto_efec_bs` decimal(10,2) DEFAULT 0.00,
  `mixto_pm_bs` decimal(10,2) DEFAULT 0.00,
  `mixto_pv_bs` decimal(10,2) DEFAULT 0.00,
  `mixto_bp_bs` decimal(10,2) DEFAULT 0.00,
  `mixto_otro_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mixto_otro_detalle` varchar(100) DEFAULT NULL,
  `vuelto_bs` decimal(10,2) DEFAULT 0.00,
  `metodo_vuelto` varchar(50) DEFAULT NULL,
  `abono_a_cliente_usd` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `cliente_id`, `usuario_id`, `tasa_aplicada`, `total_usd`, `total_bs`, `metodo_pago`, `billete_recibido_usd`, `vuelto_entregado_bs`, `vuelto_entregado_usd`, `abono_usd`, `deuda_usd`, `observaciones`, `fecha`, `referencia`, `foto_evidencia`, `mixto_usd`, `mixto_efec_bs`, `mixto_pm_bs`, `mixto_pv_bs`, `mixto_bp_bs`, `mixto_otro_usd`, `mixto_otro_detalle`, `vuelto_bs`, `metodo_vuelto`, `abono_a_cliente_usd`) VALUES
(6, NULL, 1, 700.22, 5.00, 3501.10, 'efectivo_usd', 10.00, 3501.10, 0.00, 5.00, 0.00, '', '2026-07-15 22:51:46', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'bs', 0.00),
(36, NULL, 1, 700.22, 6.00, 4201.32, 'efectivo_usd', 10.00, 2800.88, 0.00, 6.00, 0.00, '', '2026-07-16 17:32:46', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(37, NULL, 1, 700.22, 6.00, 4201.32, 'efectivo_usd', 10.00, 0.00, 4.00, 6.00, 0.00, '', '2026-07-16 17:34:30', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(38, NULL, 1, 700.22, 8.00, 5601.76, 'efectivo_bs', 8.00, 2297.58, 3.00, 8.00, 0.00, '', '2026-07-16 17:35:44', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'pago_movil', 0.00),
(53, NULL, 1, 500.00, 8.00, 4000.00, 'efectivo_usd', 10.00, 0.00, 2.00, 8.00, 0.00, '', '2026-07-17 13:04:18', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(54, NULL, 1, 500.00, 8.00, 4000.00, 'efectivo_usd', 10.00, 1000.00, 0.00, 8.00, 0.00, '', '2026-07-17 13:05:30', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(55, NULL, 1, 500.00, 3.00, 1500.00, 'efectivo_bs', 3.00, 0.00, 1.00, 3.00, 0.00, '', '2026-07-17 13:07:35', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(56, NULL, 1, 500.00, 5.00, 2500.00, 'efectivo_bs', 5.00, 500.00, 0.00, 5.00, 0.00, '', '2026-07-17 13:11:43', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'pago_movil', 0.00),
(57, NULL, 1, 500.00, 20.00, 10000.00, 'punto_venta', 20.00, 0.00, 0.00, 20.00, 0.00, '', '2026-07-17 22:13:30', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(58, NULL, 1, 500.00, 11.00, 5500.00, 'punto_venta', 11.00, 0.00, 0.00, 11.00, 0.00, '', '2026-07-17 22:14:36', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(59, NULL, 1, 500.00, 10.00, 5000.00, 'pago_movil', 10.00, 0.00, 0.00, 10.00, 0.00, '', '2026-07-17 22:16:05', '1234', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(60, NULL, 1, 500.00, 1.00, 500.00, 'punto_venta', 1.00, 0.00, 0.00, 1.00, 0.00, '', '2026-07-17 22:24:38', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(61, NULL, 1, 500.00, 1.00, 500.00, 'punto_venta', 1.00, 0.00, 0.00, 1.00, 0.00, '', '2026-07-17 22:25:00', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(64, NULL, 1, 500.00, 3.00, 1500.00, 'efectivo_usd', 5.00, 500.00, 1.00, 3.00, 0.00, '', '2026-07-20 12:31:42', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(65, NULL, 1, 500.00, 5.00, 2500.00, 'efectivo_bs', 5.00, 1500.00, 2.00, 5.00, 0.00, '', '2026-07-20 12:34:39', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(66, NULL, 1, 500.00, 7.00, 3500.00, 'efectivo_usd', 10.00, 1500.00, 0.00, 7.00, 0.00, '', '2026-07-23 14:27:44', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(67, NULL, 1, 500.00, 7.00, 3500.00, 'efectivo_bs', 7.00, 0.00, 1.00, 7.00, 0.00, '', '2026-07-23 14:30:25', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(69, 1, 1, 500.00, 5.00, 2500.00, 'credito', 5.00, 0.00, 0.00, 0.00, 0.00, '', '2026-07-23 14:40:43', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(70, 1, 1, 500.00, 50.00, 25000.00, 'credito', 50.00, 0.00, 0.00, 0.00, 0.00, '', '2026-07-24 09:28:29', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(71, NULL, 1, 500.00, 90.00, 45000.00, 'efectivo_usd', 100.00, 0.00, 0.00, 90.00, 0.00, '', '2026-07-24 09:29:24', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(72, NULL, 1, 500.00, 7.00, 3500.00, 'efectivo_usd', 10.00, 1500.00, 0.00, 7.00, 0.00, '', '2026-07-27 08:39:38', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'pago_movil', 0.00),
(73, NULL, 1, 500.00, 5.00, 2500.00, 'efectivo_usd', 10.00, 0.00, 5.00, 5.00, 0.00, '', '2026-07-27 08:41:45', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(74, NULL, 1, 500.00, 30.00, 15000.00, 'efectivo_bs', 30.00, 0.00, 10.00, 30.00, 0.00, '', '2026-07-27 08:45:25', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(75, 1, 1, 500.00, 10.00, 5000.00, 'credito_mixto', 10.00, 0.00, 0.00, 3.80, 0.00, 'Paga mas tarde', '2026-07-27 08:50:16', '123', NULL, 2.00, 100.00, 100.00, 200.00, 0.00, 1.00, 'zelle', 0.00, '', 0.00),
(77, NULL, 1, 800.00, 25.00, 20000.00, 'efectivo_usd', 50.00, 4000.00, 20.00, 25.00, 0.00, '', '2026-07-29 10:59:57', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(78, 14, 1, 800.00, 50.00, 40000.00, 'credito_mixto', 50.00, 0.00, 0.00, 16.25, 27.00, 'Paga en 15 dias en divisa', '2026-07-29 11:06:15', '4567', 'img/evidencias/evidencia_ticket_78_1785337575.jpg', 10.00, 0.00, 5000.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(79, 1, 1, 800.00, 10.00, 8000.00, 'credito_mixto', 10.00, 0.00, 0.00, 2.00, 8.00, '', '2026-08-08 17:23:13', '', 'img/evidencias/evidencia_ticket_79_1786224193.jpg', 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(80, NULL, 1, 800.00, 5.00, 4000.00, 'efectivo_usd', 10.00, 4000.00, 0.00, 5.00, 0.00, '', '2026-08-08 17:29:27', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'efectivo_bs', 0.00),
(81, 1, 1, 800.00, 1.00, 800.00, 'credito', 1.00, 0.00, 0.00, 0.00, 1.00, '', '2026-08-13 10:34:47', '', 'img/evidencias/evidencia_ticket_81_1786631687.jpg', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00),
(82, NULL, 1, 800.00, 10.00, 8000.00, 'efectivo_usd', 20.00, 8000.00, 0.00, 10.00, 0.00, '', '2026-08-15 17:08:26', '', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 'pago_movil', 0.00),
(83, 1, 1, 800.00, 15.00, 12000.00, 'credito_mixto', 15.00, 0.00, 0.00, 2.00, 13.01, '', '2026-08-15 17:29:14', '', 'img/evidencias/evidencia_ticket_83_1786829354.jpg', 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, '', 0.00);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `abonos_proveedores`
--
ALTER TABLE `abonos_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `factura_id` (`factura_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `combos`
--
ALTER TABLE `combos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `combo_detalles`
--
ALTER TABLE `combo_detalles`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `facturas_proveedores`
--
ALTER TABLE `facturas_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor_id` (`proveedor_id`);

--
-- Indices de la tabla `familias`
--
ALTER TABLE `familias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `historico_abonos`
--
ALTER TABLE `historico_abonos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ingresos_caja`
--
ALTER TABLE `ingresos_caja`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_barras` (`codigo_barras`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `abonos_proveedores`
--
ALTER TABLE `abonos_proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `combos`
--
ALTER TABLE `combos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `combo_detalles`
--
ALTER TABLE `combo_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT de la tabla `facturas_proveedores`
--
ALTER TABLE `facturas_proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `familias`
--
ALTER TABLE `familias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `gastos`
--
ALTER TABLE `gastos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `historico_abonos`
--
ALTER TABLE `historico_abonos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT de la tabla `ingresos_caja`
--
ALTER TABLE `ingresos_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `abonos_proveedores`
--
ALTER TABLE `abonos_proveedores`
  ADD CONSTRAINT `abonos_proveedores_ibfk_1` FOREIGN KEY (`factura_id`) REFERENCES `facturas_proveedores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD CONSTRAINT `movimientos_inventario_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  ADD CONSTRAINT `movimientos_inventario_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

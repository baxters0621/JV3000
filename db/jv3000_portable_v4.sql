-- JV3000 C.A. - Base de Datos Portable v4 (Instalación limpia)
-- Estructura completa + datos de sistema (roles, tipos, configuración, usuarios).
-- Sin datos demo: categorías, proveedores, clientes, productos y movimientos se inician vacíos.
-- Usuarios iniciales: Administrador / Admin123* (cambiar tras el primer inicio).

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

START TRANSACTION;

-- --------------------------------------------------------
-- 1. Tablas Independientes / Base
-- --------------------------------------------------------

-- Tabla: auditoria
DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE `auditoria` (
  `id_auditoria` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(50) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` text DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_auditoria`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_fecha` (`fecha_hora`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: categorias
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `nivel` int(11) DEFAULT 0,
  `ruta` varchar(500) DEFAULT NULL,
  `sku_prefix` varchar(20) DEFAULT NULL,
  `atributos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`atributos`)),
  `stock_minimo` int(11) DEFAULT 5,
  `stock_maximo` int(11) DEFAULT 100,
  `alerta_reorden` tinyint(1) DEFAULT 0,
  `clasificacion_abc` char(1) DEFAULT NULL,
  `cuenta_compra` varchar(20) DEFAULT NULL,
  `cuenta_venta` varchar(20) DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) DEFAULT 0.00,
  `tipo_manejo` varchar(20) DEFAULT 'normal',
  `ubicacion_defecto` varchar(50) DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: configuracion
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizado` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `configuracion` (`id_config`, `clave`, `valor`, `descripcion`, `fecha_actualizado`) VALUES
(1, 'iva_porcentaje', '16', 'Porcentaje de IVA aplicado a las ventas', '2026-07-14 19:18:02'),
(2, 'empresa_nombre', 'JV3000 C.A.', 'Nombre de la empresa', '2026-07-14 19:18:02'),
(3, 'empresa_rif', 'J-50287309-0', 'RIF de la empresa', '2026-07-14 21:18:43'),
(4, 'empresa_telefono', '+58 0414-4014690', 'Teléfono de la empresa', '2026-08-06 01:46:15'),
(5, 'empresa_direccion', 'Calle Guzman Blanco, Edif. El Surtidor Local 2, Valencia, Edo. Carabobo', 'Dirección de la empresa', '2026-08-06 01:46:15'),
(6, 'empresa_email', 'jv3000ca@gmail.com', 'Correo de la empresa', '2026-07-14 21:17:48');

-- Tabla: login_intentos
DROP TABLE IF EXISTS `login_intentos`;
CREATE TABLE `login_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `intentos` int(11) DEFAULT 0,
  `ultimo_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: proveedores
DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL AUTO_INCREMENT,
  `rif` varchar(20) NOT NULL,
  `nombre_empresa` varchar(150) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `lead_time` int(11) DEFAULT NULL,
  `limite_credito` decimal(12,2) DEFAULT NULL,
  `plazo_pago` int(11) DEFAULT NULL,
  `dias_credito` int(11) DEFAULT 0,
  `condiciones_pago` enum('Contado','Credito') DEFAULT 'Contado',
  `moneda` varchar(10) DEFAULT 'USD',
  `status` enum('Activo','Inactivo') DEFAULT 'Activo',
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  PRIMARY KEY (`id_proveedor`),
  UNIQUE KEY `idx_rif` (`rif`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: roles
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `idx_nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`id_rol`, `nombre_rol`, `descripcion`, `created_at`) VALUES
(1, 'Administrador', 'Acceso total al sistema', CURRENT_TIMESTAMP),
(2, 'Operador de Carga', 'Gestión de compras, productos, proveedores y categorías', CURRENT_TIMESTAMP),
(3, 'Operador de Ventas', 'Gestión de ventas, reportes y estadísticas', CURRENT_TIMESTAMP);

-- Tabla: sku_contadores
DROP TABLE IF EXISTS `sku_contadores`;
CREATE TABLE `sku_contadores` (
  `sku_prefix` varchar(20) NOT NULL,
  `ultimo_numero` int(11) DEFAULT 0,
  PRIMARY KEY (`sku_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sku_contadores` (`sku_prefix`, `ultimo_numero`) VALUES
('CTRL', 0),
('FAC', 0),
('NDE', 0),
('PROD', 0);

-- Tabla: tipos_movimientos
DROP TABLE IF EXISTS `tipos_movimientos`;
CREATE TABLE `tipos_movimientos` (
  `id_tipo_mov` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo_movimiento` enum('Entrada','Salida') NOT NULL,
  PRIMARY KEY (`id_tipo_mov`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tipos_movimientos` (`id_tipo_mov`, `nombre`, `tipo_movimiento`) VALUES
(1, 'Venta', 'Salida'),
(2, 'Mermas', 'Salida'),
(3, 'Regalias', 'Salida'),
(4, 'Daños', 'Salida'),
(5, 'Devoluciones', 'Entrada'),
(6, 'Ajuste de Inventario', 'Entrada'),
(7, 'Compra', 'Entrada');

-- --------------------------------------------------------
-- 2. Tablas Nivel 2 (Dependen de Usuarios o Categorías/Proveedores)
-- --------------------------------------------------------

-- Tabla: usuarios
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_rol` int(11) DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `aprobado` tinyint(1) DEFAULT 0 COMMENT '0=Pendiente, 1=Aprobado',
  `pregunta_seguridad` varchar(200) DEFAULT NULL,
  `respuesta_seguridad` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `idx_user` (`usuario`),
  UNIQUE KEY `idx_email` (`correo`),
  KEY `fk_rol` (`id_rol`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` (`id_usuario`, `usuario`, `correo`, `password`, `id_rol`, `status`, `aprobado`, `pregunta_seguridad`, `respuesta_seguridad`) VALUES
(1, 'Administrador', 'admin@jv3000.com', '$2y$10$rBAWGt9S7uWap0xNOF6UxO7.Urg.gPSgRBe6TuVwfLKv9PXXv9aDe', 1, 'Activo', 1, 'Nombre de tu mascota', '$2y$10$m4gAG5wq1mWddoZsLFZF7u587virmloOs3BNhwkBRA0qpTLpkRnBG'),
(2, 'Operador', 'operador@jv3000.com', '$2y$10$54WnFBypdQamS9JuST.fleETcDCwsW1Trk./FLhtAYpvdoMiGl6yi', 2, 'Activo', 1, 'Nombre de tu mascota', '$2y$10$X6.tFFcCnrL21m9Ji3QBreO1870X7MIid7nGpykNQQPgwSwJNqKoy'),
(3, 'Operador_Ventas', 'ventas123@gmail.com', '$2y$10$6u/bDddDy7Tc2KM/IkGG5OMX/M0W7eic..rWyCkHWiPPiWZqnKb8W', 3, 'Activo', 1, 'Nombre de tu mascota', '$2y$10$vdHIKGFfG1JTFUqH1kBWz.BojzTZ3RIJ5/1xtNXfvQLeGgZIqErE2');

-- Tabla: clientes
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `documento` varchar(20) DEFAULT NULL COMMENT 'RIF o cédula',
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `status` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `idx_cli_documento` (`documento`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: productos
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `precio_costo` decimal(10,2) DEFAULT 0.00,
  `stock_actual` int(11) DEFAULT 0,
  `stock_minimo` int(11) DEFAULT 5,
  `stock_maximo` int(11) NOT NULL DEFAULT 0,
  `fecha_vencimiento` date DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `id_categoria` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `idx_sku` (`sku`),
  KEY `fk_prod_cat` (`id_categoria`),
  KEY `idx_prod_status` (`status`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 3. Transacciones y Movimientos del Sistema
-- --------------------------------------------------------

-- Tabla: compras
DROP TABLE IF EXISTS `compras`;
CREATE TABLE `compras` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `nro_factura` varchar(50) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_compra` timestamp NOT NULL DEFAULT current_timestamp(),
  `nro_control` varchar(20) DEFAULT NULL,
  `condiciones_pago` enum('Contado','Credito') DEFAULT 'Contado',
  `dias_plazo` int(11) DEFAULT 0,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `iva` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) DEFAULT 0.00,
  `status` enum('Activa','Anulada') NOT NULL DEFAULT 'Activa',
  `tipo_entrada` varchar(50) DEFAULT 'Compra a proveedor',
  `observaciones` text DEFAULT NULL,
  `status_pago` enum('Pendiente','Pagada') DEFAULT 'Pendiente',
  `monto_pago` decimal(12,2) DEFAULT 0.00,
  `fecha_pago` datetime DEFAULT NULL,
  `metodo_pago` varchar(30) DEFAULT NULL,
  `estado_recepcion` enum('Pendiente','Parcial','Completa') DEFAULT 'Pendiente',
  PRIMARY KEY (`id_compra`),
  UNIQUE KEY `uq_comp_prov_factura` (`id_proveedor`, `nro_factura`),
  KEY `fk_comp_prov` (`id_proveedor`),
  KEY `fk_comp_user` (`id_usuario`),
  KEY `idx_comp_status` (`status`),
  KEY `idx_comp_fecha` (`fecha_compra`),
  CONSTRAINT `fk_comp_prov` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  CONSTRAINT `fk_comp_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: salidas
DROP TABLE IF EXISTS `salidas`;
CREATE TABLE `salidas` (
  `id_salida` int(11) NOT NULL AUTO_INCREMENT,
  `nro_factura_manual` varchar(20) DEFAULT NULL,
  `nro_control` varchar(20) DEFAULT NULL,
  `cliente` varchar(150) DEFAULT 'Venta General',
  `rif_cliente` varchar(20) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_tipo_mov` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_salida` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Activa','Anulada') NOT NULL DEFAULT 'Activa',
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_salida`),
  KEY `fk_sal_tipo` (`id_tipo_mov`),
  KEY `fk_sal_user` (`id_usuario`),
  KEY `fk_sal_cliente` (`id_cliente`),
  KEY `idx_sal_status` (`status`),
  KEY `idx_sal_fecha` (`fecha_salida`),
  KEY `idx_sal_fecha_status_tipo` (`fecha_salida`, `status`, `id_tipo_mov`),
  CONSTRAINT `fk_sal_tipo` FOREIGN KEY (`id_tipo_mov`) REFERENCES `tipos_movimientos` (`id_tipo_mov`),
  CONSTRAINT `fk_sal_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_sal_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: movimientos
DROP TABLE IF EXISTS `movimientos`;
CREATE TABLE `movimientos` (
  `id_movimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_referencia` int(11) NOT NULL,
  `tipo_referencia` enum('compra','venta') NOT NULL,
  `tipo` enum('Entrada','Salida') NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Activo','Anulado') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_movimiento`),
  KEY `idx_ref` (`tipo_referencia`, `id_referencia`),
  KEY `fk_mov_usuario` (`id_usuario`),
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: lotes
DROP TABLE IF EXISTS `lotes`;
CREATE TABLE `lotes` (
  `id_lote` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_compra` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `cantidad_restante` int(11) NOT NULL DEFAULT 0,
  `precio_costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_ingreso` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_lote`),
  KEY `fk_lot_prod` (`id_producto`),
  KEY `fk_lot_compra` (`id_compra`),
  KEY `idx_lot_venc` (`fecha_vencimiento`),
  CONSTRAINT `fk_lot_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE,
  CONSTRAINT `fk_lot_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Tablas Detalle (Dependencias Cruzadas)
-- --------------------------------------------------------

-- Tabla: detalle_compras
DROP TABLE IF EXISTS `detalle_compras`;
CREATE TABLE `detalle_compras` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_costo` decimal(10,2) NOT NULL,
  `cantidad_recibida` int(11) NOT NULL DEFAULT 0,
  `fecha_vencimiento` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detcomp_compra` (`id_compra`),
  KEY `fk_detcomp_producto` (`id_producto`),
  CONSTRAINT `fk_detcomp_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE,
  CONSTRAINT `fk_detcomp_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: detalle_salidas
DROP TABLE IF EXISTS `detalle_salidas`;
CREATE TABLE `detalle_salidas` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_salida` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_lote` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detsal_salida` (`id_salida`),
  KEY `fk_detsal_producto` (`id_producto`),
  KEY `fk_detsal_lote` (`id_lote`),
  CONSTRAINT `fk_detsal_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `fk_detsal_salida` FOREIGN KEY (`id_salida`) REFERENCES `salidas` (`id_salida`) ON DELETE CASCADE,
  CONSTRAINT `fk_detsal_lote` FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla: detalle_movimientos
DROP TABLE IF EXISTS `detalle_movimientos`;
CREATE TABLE `detalle_movimientos` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_movimiento` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detmov_movimiento` (`id_movimiento`),
  KEY `fk_detmov_producto` (`id_producto`),
  CONSTRAINT `fk_detmov_movimiento` FOREIGN KEY (`id_movimiento`) REFERENCES `movimientos` (`id_movimiento`) ON DELETE CASCADE,
  CONSTRAINT `fk_detmov_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

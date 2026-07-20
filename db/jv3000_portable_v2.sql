-- ===========================================
-- JV3000 - VERSION 2.0 (NUCLEAR)
-- Drop completo + creacion desde cero
-- ===========================================

DROP DATABASE IF EXISTS `jv3000_db`;
CREATE DATABASE `jv3000_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `jv3000_db`;

-- ===========================================
-- NIVEL 1: Tablas Maestras
-- ===========================================

CREATE TABLE `configuracion` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizado` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `configuracion` (`clave`,`valor`,`descripcion`) VALUES
('iva_porcentaje','16','Porcentaje de IVA aplicado a las ventas'),
('empresa_nombre','JV3000 C.A.','Nombre de la empresa'),
('empresa_rif','J-50287309-0','RIF de la empresa'),
('empresa_telefono','+58 0414-4014690','Teléfono de la empresa'),
('empresa_direccion','Calle Guzman Blanco, Edif. El Surtidor Local 2, Valencia, Edo. Carabobo','Dirección de la empresa'),
('empresa_email','jv3000ca@gmail.com','Correo de la empresa');


CREATE TABLE `login_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `intentos` int(11) DEFAULT 0,
  `ultimo_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `sku_contadores` (
  `sku_prefix` varchar(20) NOT NULL,
  `ultimo_numero` int(11) DEFAULT 0,
  PRIMARY KEY (`sku_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sku_contadores` (`sku_prefix`,`ultimo_numero`) VALUES
('CAT','0'),
('FAC','0'),
('PROD','0');


CREATE TABLE `tipos_movimientos` (
  `id_tipo_mov` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo_movimiento` enum('Entrada','Salida') NOT NULL,
  PRIMARY KEY (`id_tipo_mov`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tipos_movimientos` (`nombre`,`tipo_movimiento`) VALUES
('Venta','Salida'),
('Mermas','Salida'),
('Regalias','Salida'),
('Daños','Salida'),
('Devoluciones','Entrada'),
('Ajuste de Inventario','Entrada'),
('Compra','Entrada');


CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('Administrador','Operador de Carga','Operador de Ventas') NOT NULL DEFAULT 'Operador de Ventas',
  `status` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `aprobado` tinyint(1) DEFAULT 0 COMMENT '0=Pendiente, 1=Aprobado',
  `pregunta_seguridad` varchar(200) DEFAULT NULL,
  `respuesta_seguridad` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `idx_user` (`usuario`),
  UNIQUE KEY `idx_email` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` (`usuario`,`correo`,`password`,`rol`,`status`,`aprobado`) VALUES
('Administrador','admin@jv3000.com','$2y$10$s.9VU8M6.Y9DhwkLU4FiZOWpjfEHySGer/fz8b8Li06go5epEcKky','Administrador','Activo','1'),
('Operador','operador@jv3000.com','$2y$10$ayDmHHDAOg161C8lLhnJWu4u83IEbeEd36ixCAWiDrCa5cilcZ/wy','Operador de Carga','Activo','1');


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Los proveedores se agregan desde el módulo de Proveedores


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
  `clasificacion_abc` char(1) DEFAULT NULL,
  `cuenta_compra` varchar(20) DEFAULT NULL,
  `cuenta_venta` varchar(20) DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) DEFAULT 0.00,
  `tipo_manejo` varchar(20) DEFAULT 'normal',
  `ubicacion_defecto` varchar(50) DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Las categorías se agregan desde el módulo de Categorías


-- ===========================================
-- NIVEL 2: Tablas con Dependencias
-- ===========================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `precio_costo` decimal(10,2) DEFAULT 0.00,
  `stock_actual` int(11) DEFAULT 0,
  `stock_minimo` int(11) DEFAULT 5,
  `fecha_vencimiento` date DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `id_categoria` int(11) NOT NULL,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `idx_sku` (`sku`),
  KEY `fk_prod_cat` (`id_categoria`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Los productos se agregan desde el módulo de Productos


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
  PRIMARY KEY (`id_compra`),
  UNIQUE KEY `uq_nro_control` (`nro_control`),
  KEY `fk_comp_prov` (`id_proveedor`),
  KEY `fk_comp_user` (`id_usuario`),
  CONSTRAINT `fk_comp_prov` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  CONSTRAINT `fk_comp_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Las compras se registran desde el módulo de Compras


CREATE TABLE `salidas` (
  `id_salida` int(11) NOT NULL AUTO_INCREMENT,
  `nro_factura_manual` varchar(20) DEFAULT NULL,
  `nro_control` varchar(20) DEFAULT NULL,
  `cliente` varchar(150) DEFAULT 'Venta General',
  `rif_cliente` varchar(20) DEFAULT NULL,
  `id_tipo_mov` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_salida` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Activa','Anulada') NOT NULL DEFAULT 'Activa',
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_salida`),
  KEY `fk_sal_tipo` (`id_tipo_mov`),
  KEY `fk_sal_user` (`id_usuario`),
  CONSTRAINT `fk_sal_tipo` FOREIGN KEY (`id_tipo_mov`) REFERENCES `tipos_movimientos` (`id_tipo_mov`),
  CONSTRAINT `fk_sal_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Las salidas se registran desde el módulo de Salidas

-- ===========================================
-- TABLAS DETALLE (RELACIÓN 1:N)
-- ===========================================

CREATE TABLE `detalle_compras` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_costo` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detcomp_compra` (`id_compra`),
  KEY `fk_detcomp_producto` (`id_producto`),
  CONSTRAINT `fk_detcomp_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE,
  CONSTRAINT `fk_detcomp_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_salidas` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_salida` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detsal_salida` (`id_salida`),
  KEY `fk_detsal_producto` (`id_producto`),
  CONSTRAINT `fk_detsal_salida` FOREIGN KEY (`id_salida`) REFERENCES `salidas` (`id_salida`) ON DELETE CASCADE,
  CONSTRAINT `fk_detsal_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================
-- MOVIMIENTOS DE INVENTARIO
-- ===========================================

CREATE TABLE `movimientos` (
  `id_movimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_referencia` int(11) NOT NULL,
  `tipo_referencia` enum('compra','venta') NOT NULL,
  `tipo` enum('Entrada','Salida') NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Activo','Anulado') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_movimiento`),
  KEY `idx_ref` (`tipo_referencia`,`id_referencia`),
  KEY `fk_mov_usuario` (`id_usuario`),
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================
-- INDICES ADICIONALES PARA RENDIMIENTO
-- ===========================================

ALTER TABLE `productos` ADD INDEX `idx_prod_status` (`status`);

ALTER TABLE `compras` ADD INDEX `idx_comp_status` (`status`);
ALTER TABLE `compras` ADD INDEX `idx_comp_fecha` (`fecha_compra`);

ALTER TABLE `salidas` ADD INDEX `idx_sal_status` (`status`);
ALTER TABLE `salidas` ADD INDEX `idx_sal_fecha` (`fecha_salida`);
ALTER TABLE `salidas` ADD INDEX `idx_sal_fecha_status_tipo` (`fecha_salida`, `status`, `id_tipo_mov`);

-- === FIN VERSION 3.0 ===

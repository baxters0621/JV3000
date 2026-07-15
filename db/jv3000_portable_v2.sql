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
('CAT','6'),
('FAC','8'),
('PROD','15');


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

INSERT INTO `proveedores` (`rif`,`nombre_empresa`,`contacto`,`lead_time`,`limite_credito`,`dias_credito`,`condiciones_pago`,`moneda`,`status`,`telefono`,`email`,`direccion`) VALUES
('J-40012345-6','TOTAL OIL & GAS VENEZUELA','Luis','5','5000.00','30','Credito','USD','Activo','(0412) 555-0101','compras@totaloil.com.ve','Av. Principal de la Industria'),
('J-40067890-1','MOBIL LUBRICANTES C.A.','Carlos','5','5000.00','30','Credito','USD','Activo','(0412) 555-0202','ventas@mobil.com.ve','Calle 3, Edif. Mobil'),
('J-40123456-7','CASTROL VENEZOLANA S.A.','Maria','5','5000.00','30','Credito','USD','Activo','(0412) 555-0303','pedidos@castrol.com.ve','Zona Ind. Los Cortijos'),
('J-40987654-3','REPUESTOS Y FILTROS DEL SUR','Jose','5','5000.00','30','Credito','USD','Activo','(0414) 555-0404','ventas@filtrossur.com','Av. Fuerzas Armadas'),
('J-40543210-9','QUIMICOS INDUSTRIALES C.A.','Ana','5','5000.00','30','Credito','USD','Activo','(0412) 555-0505','info@quimicosca.com','Urb. Industrial La Trinidad');


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

INSERT INTO `categorias` (`codigo`,`nombre`) VALUES
('CAT-001','ACEITES DE MOTOR'),
('CAT-002','LUBRICANTES INDUSTRIALES'),
('CAT-003','GRASAS'),
('CAT-004','FILTROS'),
('CAT-005','ADITIVOS'),
('CAT-006','REPUESTOS');


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

INSERT INTO `productos` (`sku`,`nombre_producto`,`precio_venta`,`precio_costo`,`stock_actual`,`stock_minimo`,`id_categoria`) VALUES
('PROD-001','ACEITE MOTOR 20W50 TOTAL','12.00','8.50','68','10','1'),
('PROD-002','ACEITE MOTOR 10W40 MOBIL','13.50','9.00','57','10','1'),
('PROD-003','ACEITE MOTOR 15W40 CASTROL','13.00','8.75','50','10','1'),
('PROD-004','ACEITE TRANSMISION ATF','18.00','12.00','40','10','1'),
('PROD-005','ACEITE HIDRAULICO ISO 68','11.50','7.50','45','10','2'),
('PROD-006','ACEITE HIDRAULICO ISO 46','11.00','7.00','30','10','2'),
('PROD-007','GRASA MULTIPROPOSITO 1KG','7.50','4.50','85','10','3'),
('PROD-008','GRASA LITIO EP2 1KG','8.00','5.00','45','10','3'),
('PROD-009','FILTRO ACEITE TO-6731','5.50','3.00','105','10','4'),
('PROD-010','FILTRO ACEITE TO-7317','6.00','3.50','70','10','4'),
('PROD-011','FILTRO COMBUSTIBLE FC-100','7.00','4.00','55','10','4'),
('PROD-012','ADITIVO LIMPIA INYECTORES','10.00','6.00','120','10','5'),
('PROD-013','ADITIVO ESTABILIZADOR','9.50','5.50','65','10','5'),
('PROD-014','BUJIA NGK STANDARD','4.00','2.00','140','10','6'),
('PROD-015','CABLE BUJIA SET','14.00','8.00','40','10','6');


CREATE TABLE `compras` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `nro_factura` varchar(50) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_costo` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `tipo_entrada` varchar(50) DEFAULT 'Compra a proveedor',
  `id_usuario` int(11) NOT NULL,
  `fecha_compra` timestamp NOT NULL DEFAULT current_timestamp(),
  `observaciones` text DEFAULT NULL,
  `nro_control` varchar(20) DEFAULT NULL,
  `condiciones_pago` enum('Contado','Credito') DEFAULT 'Contado',
  `dias_plazo` int(11) DEFAULT 0,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `iva` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) DEFAULT 0.00,
  `status` enum('Activa','Anulada') NOT NULL DEFAULT 'Activa',
  PRIMARY KEY (`id_compra`),
  UNIQUE KEY `uq_nro_control` (`nro_control`),
  KEY `fk_comp_prov` (`id_proveedor`),
  KEY `fk_comp_user` (`id_usuario`),
  CONSTRAINT `fk_comp_prov` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  CONSTRAINT `fk_comp_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `compras` (`nro_factura`,`id_proveedor`,`id_producto`,`cantidad`,`precio_costo`,`tipo_entrada`,`id_usuario`,`fecha_compra`,`nro_control`,`condiciones_pago`,`dias_plazo`,`total`) VALUES
('FAC-001','1','1','30','8.50','Compra a proveedor','1','2026-06-01 00:00:00','01-00000001','Credito','30','255.00'),
('FAC-001','1','3','20','8.75','Compra a proveedor','1','2026-06-01 00:00:00','01-00000002','Credito','30','175.00'),
('FAC-002','2','2','25','9.00','Compra a proveedor','1','2026-06-03 00:00:00','01-00000003','Credito','30','225.00'),
('FAC-002','2','5','15','7.50','Compra a proveedor','1','2026-06-03 00:00:00','01-00000004','Credito','30','112.50'),
('FAC-003','1','7','40','4.50','Compra a proveedor','1','2026-06-05 00:00:00','01-00000005','Credito','30','180.00'),
('FAC-003','1','9','50','3.00','Compra a proveedor','1','2026-06-05 00:00:00','01-00000006','Credito','30','150.00'),
('FAC-004','4','12','30','6.00','Compra a proveedor','1','2026-06-08 00:00:00','01-00000007','Credito','30','180.00'),
('FAC-004','4','14','40','2.00','Compra a proveedor','1','2026-06-08 00:00:00','01-00000008','Credito','30','80.00'),
('FAC-005','3','4','15','12.00','Compra a proveedor','1','2026-06-10 00:00:00','01-00000009','Credito','30','180.00'),
('FAC-005','3','6','10','7.00','Compra a proveedor','1','2026-06-10 00:00:00','01-00000010','Credito','30','70.00');


CREATE TABLE `salidas` (
  `id_salida` int(11) NOT NULL AUTO_INCREMENT,
  `nro_factura_manual` varchar(20) DEFAULT NULL,
  `nro_control` varchar(20) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `cliente` varchar(150) DEFAULT 'Venta General',
  `rif_cliente` varchar(20) DEFAULT NULL,
  `id_tipo_mov` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_salida` timestamp NOT NULL DEFAULT current_timestamp(),
  `observaciones` text DEFAULT NULL,
  `status` enum('Activa','Anulada') NOT NULL DEFAULT 'Activa',
  PRIMARY KEY (`id_salida`),
  KEY `fk_sal_prod` (`id_producto`),
  KEY `fk_sal_tipo` (`id_tipo_mov`),
  KEY `fk_sal_user` (`id_usuario`),
  CONSTRAINT `fk_sal_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `fk_sal_tipo` FOREIGN KEY (`id_tipo_mov`) REFERENCES `tipos_movimientos` (`id_tipo_mov`),
  CONSTRAINT `fk_sal_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `salidas` (`nro_factura_manual`,`nro_control`,`id_producto`,`cantidad`,`precio_venta`,`cliente`,`rif_cliente`,`id_tipo_mov`,`id_usuario`,`fecha_salida`,`observaciones`) VALUES
('FAC-001','02-35058787','1','10','12.00','CLIENTE ABC C.A.','J-12345678-0','1','1','2026-06-12 00:00:00','Venta de prueba'),
('FAC-002','02-63505166','2','8','13.50','TALLER EL MOTOR','J-87654321-0','1','1','2026-06-12 00:00:00','Venta de prueba'),
('FAC-003','02-54897416','3','5','13.00','TRANSPORTE CARIBE','J-11223344-5','1','1','2026-06-13 00:00:00','Venta de prueba'),
('FAC-004','02-58019959','7','15','7.50','LUBRICENTRO SANTA FE','J-55667788-9','1','1','2026-06-13 00:00:00','Venta de prueba'),
('FAC-005','02-64370291','9','25','5.50','CONCESIONARIO AUTOMUNDO','J-99887766-1','1','1','2026-06-14 00:00:00','Venta de prueba'),
('FAC-006','02-24136937','1','2','12.00','CLIENTE FIEL','','3','1','2026-06-14 00:00:00','Regalía');

-- ===========================================
-- INDICES ADICIONALES PARA RENDIMIENTO
-- ===========================================

ALTER TABLE `productos` ADD INDEX `idx_prod_status` (`status`);

ALTER TABLE `compras` ADD INDEX `idx_comp_status` (`status`);
ALTER TABLE `compras` ADD INDEX `idx_comp_fecha` (`fecha_compra`);
ALTER TABLE `compras` ADD INDEX `idx_comp_producto` (`id_producto`);

ALTER TABLE `salidas` ADD INDEX `idx_sal_status` (`status`);
ALTER TABLE `salidas` ADD INDEX `idx_sal_fecha` (`fecha_salida`);
ALTER TABLE `salidas` ADD INDEX `idx_sal_fecha_status_tipo` (`fecha_salida`, `status`, `id_tipo_mov`);

-- === FIN VERSION 2.0 ===

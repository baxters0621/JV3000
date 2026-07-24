-- JV3000 C.A. - Base de Datos Portable v3
-- Generado el 2026-07-24 04:35:49

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;
SET time_zone = '+00:00';

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
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `auditoria` (`id_auditoria`,`id_usuario`,`usuario_nombre`,`accion`,`detalle`,`fecha_hora`) VALUES
('76','1','Administrador','editar','Categoría modificada','2026-07-21 00:44:43'),
('77','1','Administrador','editar','Categoría modificada','2026-07-21 00:44:56'),
('78','1','Administrador','editar','Categoría modificada','2026-07-21 00:45:04'),
('79','1','Administrador','logout','Cierre de sesión','2026-07-21 01:27:34'),
('80','1','Administrador','login','Inicio de sesión','2026-07-21 22:15:31'),
('81','1','Administrador','logout','Cierre de sesión','2026-07-21 22:16:00'),
('82','1','Administrador','login','Inicio de sesión','2026-07-21 22:40:52'),
('83','1','Administrador','login','Inicio de sesión','2026-07-22 08:37:35'),
('84','1','Administrador','editar','Categoría modificada','2026-07-22 08:56:13'),
('85','1','Administrador','editar','Categoría modificada','2026-07-22 08:56:18'),
('86','1','Administrador','editar','Categoría modificada','2026-07-22 08:56:23'),
('87','1','Administrador','editar','Categoría modificada','2026-07-22 08:56:28'),
('88','1','Administrador','toggle_status','Cambio de estado','2026-07-22 09:32:55'),
('89','1','Administrador','toggle_status','Cambio de estado','2026-07-22 09:32:59'),
('90','1','Administrador','logout','Cierre de sesión','2026-07-22 09:33:06');

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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categorias` (`id_categoria`,`codigo`,`nombre`,`descripcion`,`parent_id`,`nivel`,`ruta`,`sku_prefix`,`atributos`,`stock_minimo`,`stock_maximo`,`alerta_reorden`,`clasificacion_abc`,`cuenta_compra`,`cuenta_venta`,`iva_porcentaje`,`tipo_manejo`,`ubicacion_defecto`,`status`) VALUES
('1','LUB','Lubricantes y Fluidos',NULL,NULL,'0','LUB','LUB',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('2','BAT','Baterías y Energía',NULL,NULL,'0','BAT','BAT',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('3','LED','Iluminación',NULL,NULL,'0','LED','LED',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('4','FIL','Filtros',NULL,NULL,'0','FIL','FIL',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('5','FRE','Frenos',NULL,NULL,'0','FRE','FRE',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('6','ELE','Eléctrico y Encendido',NULL,NULL,'0','ELE','ELE',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('7','SUS','Suspensión y Dirección',NULL,NULL,'0','SUS','SUS',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('8','MOT','Motor y Refrigeración',NULL,NULL,'0','MOT','MOT',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('9','LUB-ACE','ACEITES DE MOTOR','','1','1','LUB/LUB-ACE','LUB-ACE',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('10','LUB-TRA','Lubricantes de Transmisión',NULL,'1','1','LUB/LUB-TRA','LUB-TRA',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('11','LUB-REF','Refrigerantes y Anticongelantes',NULL,'1','1','LUB/LUB-REF','LUB-REF',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('12','BAT-AUT','Baterías Automotrices',NULL,'2','1','BAT/BAT-AUT','BAT-AUT',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('13','BAT-CAR','Cargadores y Accesorios',NULL,'2','1','BAT/BAT-CAR','BAT-CAR',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('14','LED-DEL','Faros Delanteros',NULL,'3','1','LED/LED-DEL','LED-DEL',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('15','LED-TRA','Luces Traseras',NULL,'3','1','LED/LED-TRA','LED-TRA',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('16','LED-INT','Iluminación Interior',NULL,'3','1','LED/LED-INT','LED-INT',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('17','LED-BAR','Barras y Tiras LED',NULL,'3','1','LED/LED-BAR','LED-BAR',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('18','FIL-ACE','Filtros de Aceite',NULL,'4','1','FIL/FIL-ACE','FIL-ACE',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('19','FIL-AIR','Filtros de Aire',NULL,'4','1','FIL/FIL-AIR','FIL-AIR',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('20','FIL-COM','Filtros de Combustible',NULL,'4','1','FIL/FIL-COM','FIL-COM',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('21','FIL-CAB','Filtros de Cabina',NULL,'4','1','FIL/FIL-CAB','FIL-CAB',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('22','FRE-PAS','Pastillas de Freno',NULL,'5','1','FRE/FRE-PAS','FRE-PAS',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('23','FRE-DIS','Discos de Freno',NULL,'5','1','FRE/FRE-DIS','FRE-DIS',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('24','FRE-LIQ','Líquido de Freno',NULL,'5','1','FRE/FRE-LIQ','FRE-LIQ',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('25','FRE-CIL','Bombines y Cilindros',NULL,'5','1','FRE/FRE-CIL','FRE-CIL',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('26','ELE-BUJ','Bujías',NULL,'6','1','ELE/ELE-BUJ','ELE-BUJ',NULL,'5','100','0','A',NULL,NULL,'0.00','normal',NULL,'Activo'),
('27','ELE-CAB','Cables y Distribuidores',NULL,'6','1','ELE/ELE-CAB','ELE-CAB',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('28','ELE-FUS','Fusibles y Relés',NULL,'6','1','ELE/ELE-FUS','ELE-FUS',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('29','ELE-ALT','ALTERNADORES Y ARRANQUES','','6','1','ELE/ELE-ALT','ELE-ALT',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('30','SUS-AMO','AMORTIGUADORES','','7','1','SUS/SUS-AMO','SUS-AMO',NULL,'5','100','0','B',NULL,NULL,'0.00','normal',NULL,'Activo'),
('31','SUS-BRA','Brazos y Rótulas',NULL,'7','1','SUS/SUS-BRA','SUS-BRA',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('32','SUS-BAR','BARRAS ESTABILIZADORAS','','7','1','SUS/SUS-BAR','SUS-BAR',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('33','MOT-COR','Correas y Tensores',NULL,'8','1','MOT/MOT-COR','MOT-COR',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('34','MOT-TER','Termostatos y Sensores',NULL,'8','1','MOT/MOT-TER','MOT-TER',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo'),
('35','MOT-BOM','Bombas de Agua y Aceite',NULL,'8','1','MOT/MOT-BOM','MOT-BOM',NULL,'5','100','0','C',NULL,NULL,'0.00','normal',NULL,'Activo');

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
  KEY `idx_comp_status` (`status`),
  KEY `idx_comp_fecha` (`fecha_compra`),
  CONSTRAINT `fk_comp_prov` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  CONSTRAINT `fk_comp_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `compras` (`id_compra`,`nro_factura`,`id_proveedor`,`id_usuario`,`fecha_compra`,`nro_control`,`condiciones_pago`,`dias_plazo`,`subtotal`,`iva`,`total`,`status`,`tipo_entrada`) VALUES
('1','FAC-LUB-001','2','1','2026-07-22 08:30:00','01-00000001','Credito','30','1116.00','178.56','1294.56','Activa','Compra a proveedor'),
('2','FAC-FRE-001','3','1','2026-07-21 10:00:00','02-00000002','Credito','30','1983.00','317.28','2300.28','Activa','Compra a proveedor'),
('3','FAC-ELE-001','4','1','2026-07-17 14:30:00','03-00000003','Credito','30','3084.60','493.54','3578.14','Activa','Compra a proveedor');

CREATE TABLE `configuracion` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizado` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `configuracion` (`id_config`,`clave`,`valor`,`descripcion`,`fecha_actualizado`) VALUES
('1','iva_porcentaje','16','Porcentaje de IVA aplicado a las ventas','2026-07-14 19:18:02'),
('2','empresa_nombre','JV3000 C.A.','Nombre de la empresa','2026-07-14 19:18:02'),
('3','empresa_rif','J-50287309-0','RIF de la empresa','2026-07-14 21:18:43'),
('4','empresa_telefono','+58 0414-4014690','Teléfono de la empresa','2026-07-14 21:17:48'),
('5','empresa_direccion','Calle Guzman Blanco, Edif. El Surtidor Local 2, Valencia, Edo. Carabobo','Dirección de la empresa','2026-07-14 21:17:48'),
('6','empresa_email','jv3000ca@gmail.com','Correo de la empresa','2026-07-14 21:17:48');

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `detalle_compras` (`id_detalle`,`id_compra`,`id_producto`,`cantidad`,`precio_costo`,`fecha_vencimiento`,`observaciones`) VALUES
('1','1','1','30','5.10',NULL,NULL),
('2','1','2','25','4.68',NULL,NULL),
('3','1','3','20','16.80',NULL,NULL),
('4','1','6','15','6.60',NULL,NULL),
('5','1','14','30','5.70',NULL,NULL),
('6','1','18','20','12.00',NULL,NULL),
('7','2','23','15','33.00',NULL,NULL),
('8','2','26','10','57.00',NULL,NULL),
('9','2','28','20','6.30',NULL,NULL),
('10','2','39','30','10.80',NULL,NULL),
('11','2','40','30','7.20',NULL,NULL),
('13','3','11','10','81.00',NULL,NULL),
('14','3','12','8','105.00',NULL,NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `detalle_movimientos` (`id_detalle`,`id_movimiento`,`id_producto`,`cantidad`,`precio_unitario`) VALUES
('1','1','1','30','5.10'),
('2','1','2','25','4.68'),
('3','1','3','20','16.80'),
('4','1','6','15','6.60'),
('5','1','14','30','5.70'),
('6','1','18','20','12.00'),
('7','2','23','15','33.00'),
('8','2','26','10','57.00'),
('9','2','28','20','6.30'),
('10','2','39','30','10.80'),
('11','2','40','30','7.20'),
('13','3','11','10','81.00'),
('14','3','12','8','105.00'),
('19','4','1','2','8.50'),
('20','4','14','1','9.50'),
('21','4','23','1','55.00'),
('22','4','39','4','18.00'),
('23','5','3','4','28.00'),
('24','5','17','2','62.00'),
('25','5','26','1','95.00'),
('26','5','12','1','175.00'),
('27','6','2','1','7.80'),
('28','6','15','1','10.00'),
('31','8','19','1','13.20');

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
  CONSTRAINT `fk_detsal_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `fk_detsal_salida` FOREIGN KEY (`id_salida`) REFERENCES `salidas` (`id_salida`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `detalle_salidas` (`id_detalle`,`id_salida`,`id_producto`,`cantidad`,`precio_venta`,`observaciones`) VALUES
('1','1','1','2','8.50',NULL),
('2','1','14','1','9.50',NULL),
('3','1','23','1','55.00',NULL),
('4','1','39','4','18.00',NULL),
('5','2','3','4','28.00',NULL),
('6','2','17','2','62.00',NULL),
('7','2','26','1','95.00',NULL),
('8','2','12','1','175.00',NULL);

CREATE TABLE `login_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `intentos` int(11) DEFAULT 0,
  `ultimo_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `movimientos` (`id_movimiento`,`id_referencia`,`tipo_referencia`,`tipo`,`id_usuario`,`fecha_movimiento`,`status`) VALUES
('1','1','compra','Entrada','1','2026-07-21 23:33:04','Activo'),
('2','2','compra','Entrada','1','2026-07-21 23:33:05','Activo'),
('3','3','compra','Entrada','1','2026-07-21 23:33:05','Activo'),
('4','1','venta','Salida','1','2026-07-21 23:33:06','Activo'),
('5','2','venta','Salida','1','2026-07-21 23:33:07','Activo'),
('6','3','venta','Salida','1','2026-07-21 23:33:07','Anulado'),
('8','5','venta','Salida','1','2026-07-21 23:33:08','Anulado');

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
  `id_proveedor` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `idx_sku` (`sku`),
  KEY `fk_prod_cat` (`id_categoria`),
  KEY `idx_prod_status` (`status`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `productos` (`id_producto`,`sku`,`nombre_producto`,`precio_venta`,`precio_costo`,`stock_actual`,`stock_minimo`,`fecha_vencimiento`,`status`,`id_categoria`,`id_proveedor`) VALUES
('1','LUB-ACE-0001','Aceite Mobil Super 2000 20W-50 1L','8.50','5.10','48','10','2026-01-15','Activo','9','2'),
('2','LUB-ACE-0002','Aceite Castrol GTX 20W-50 1L','7.80','4.68','45','10','2026-08-10','Activo','9','2'),
('3','LUB-ACE-0003','Aceite Shell Helix HX3 20W-50 4L','28.00','16.80','26','8','2027-03-15','Activo','9','2'),
('4','LUB-ACE-0004','Aceite Havoline Pro-DS 15W-40 1L','9.50','5.70','35','10','2027-06-10','Activo','9','2'),
('5','LUB-ACE-0005','Aceite Liqui Moly 5W-30 4L','52.00','31.20','12','5','2028-01-20','Activo','9','2'),
('6','LUB-TRA-0001','Lubricante ATF Dexron III 1L','11.00','6.60','25','8','2026-04-20','Activo','10','2'),
('7','LUB-TRA-0002','Lubricante 90GL-5 1L','10.50','6.30','20','8','2029-11-30','Activo','10','2'),
('9','LUB-REF-0001','Refrigerante Prestone Verde 1L','7.50','4.50','30','10','2026-07-28','Activo','11','2'),
('10','LUB-REF-0002','Refrigerante Havoline Rojo 1L','8.00','4.80','28','10','2028-07-05','Activo','11','2'),
('11','BAT-AUT-0001','Batería Duncan 34R 550CCA','135.00','81.00','15','5','2026-07-26','Activo','12','4'),
('12','BAT-AUT-0002','Batería Bosch S4 60Ah','175.00','105.00','11','5','2026-08-18','Activo','12','4'),
('13','BAT-AUT-0003','Batería Optima YellowTop D31T','385.00','231.00','6','3','2029-02-14','Activo','12','4'),
('14','FIL-ACE-0001','Filtro Aceite Fram PH7317','9.50','5.70','39','10','2025-11-10','Activo','18','1'),
('15','FIL-ACE-0002','Filtro Aceite Bosch 3330','10.00','6.00','35','10','2026-08-15','Activo','18','1'),
('16','FIL-ACE-0003','Filtro Aceite Purolator L14610','8.00','4.80','30','10','2030-09-18','Activo','18','1'),
('17','FIL-AIR-0001','Filtro Aire K&N 33-2456','62.00','37.20','8','5','2026-07-25','Activo','19','1'),
('18','FIL-AIR-0002','Filtro Aire AC-Delco A1512C','20.00','12.00','25','8','2030-03-08','Activo','19','1'),
('19','FIL-AIR-0003','Filtro Aire Fram CA10113','22.00','13.20','22','8','2031-07-01','Activo','19','1'),
('20','FIL-COM-0001','Filtro Combustible WIX 33480','16.00','9.60','20','5','2026-08-05','Activo','20','1'),
('21','FIL-COM-0002','Filtro Combustible Bosch 0450905051','24.00','14.40','18','5','2029-08-14','Activo','20','1'),
('22','FIL-CAB-0001','Filtro Cabina Fram CF10850','28.00','16.80','15','5','2030-12-25','Activo','21','1'),
('23','FRE-PAS-0001','Pastillas Freno Cerámicas Bosch','55.00','33.00','19','5','2026-08-12','Activo','22','3'),
('24','FRE-PAS-0002','Pastillas Freno Orgánicas Ferodo','45.00','27.00','18','5','2033-07-30','Activo','22','3'),
('25','FRE-PAS-0003','Pastillas Freno Semi-metálicas Jurid','52.00','31.20','15','5','2032-09-15','Activo','22','3'),
('26','FRE-DIS-0001','Disco Freno Delantero Brembo','95.00','57.00','11','5','2035-04-10','Activo','23','3'),
('27','FRE-DIS-0002','Disco Freno Trasero ACDelco','72.00','43.20','14','5','2034-11-05','Activo','23','3'),
('28','FRE-LIQ-0001','Líquido Freno DOT 4 Prestone 1L','10.50','6.30','30','10','2026-03-05','Activo','24','3'),
('29','FRE-LIQ-0002','Líquido Freno DOT 5.1 Motul 500ml','18.00','10.80','12','5','2028-03-22','Activo','24','3'),
('39','ELE-BUJ-0001','Bujía NGK Iridium BKR6EIX-11','18.00','10.80','36','10','2030-05-22','Activo','26','4'),
('40','ELE-BUJ-0002','Bujía Bosch Super 4 FR7DC+','12.00','7.20','45','10','2031-01-10','Activo','26','4');

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `proveedores` (`id_proveedor`,`rif`,`nombre_empresa`,`contacto`,`lead_time`,`limite_credito`,`plazo_pago`,`dias_credito`,`condiciones_pago`,`moneda`,`status`,`telefono`,`email`,`direccion`) VALUES
('1','J-40123456-7','Repuestos El Ávila C.A.','Juan Contreras','3','15000.00','30','30','Credito','USD','Activo','+58 241-812.3456','ventas@elavila.com','Av. Las Industrias, Zona Industrial La Guacamaya, Valencia'),
('2','J-40567890-1','Importadora Lubritech C.A.','María Flores','2','25000.00','45','45','Credito','USD','Activo','+58 241-871.2345','maria@lubritech.com','Av. Principal de Guaparo, Centro Empresarial Carabobo, Valencia'),
('3','J-40987654-3','Frenos y Rodamientos Valencia C.A.','Pedro Rojas','4','12000.00','30','30','Credito','USD','Activo','+58 241-823.4567','pedro@frenosvalencia.com','Calle 100, Zona Industrial El Trigal, Valencia'),
('4','J-40345678-9','Eléctricos Automotrices del Centro C.A.','Carmen Suárez','3','20000.00','30','30','Credito','USD','Activo','+58 241-842.5678','carmen@electricoscentro.com','Av. Bolívar, Centro Comercial Los Sauces, Valencia'),
('5','J-40789012-5','Iluminación y Accesorios C.A.','Luis Mendoza','2','18000.00','30','30','Credito','USD','Activo','+58 241-855.6789','luis@iluminacionca.com','Calle Ricaurte, Edif. Luxor, Valencia'),
('6','J-40234567-8','Motor Parts Central C.A.','Ana Guerrero','3','30000.00','45','45','Credito','USD','Activo','+58 241-866.7890','ana@motorpartscentral.com','Zona Industrial Municipal Sur, Galpón 7, Valencia');

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `idx_nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`id_rol`,`nombre_rol`,`descripcion`,`created_at`) VALUES
('1','Administrador','Acceso total al sistema','2026-07-23 21:54:53'),
('2','Operador de Carga','Gestiu00f3n de compras, productos, proveedores y categoru00edas','2026-07-23 21:54:53'),
('3','Operador de Ventas','Gestiu00f3n de ventas, reportes y estadu00edsticas','2026-07-23 21:54:53');

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
  KEY `idx_sal_status` (`status`),
  KEY `idx_sal_fecha` (`fecha_salida`),
  KEY `idx_sal_fecha_status_tipo` (`fecha_salida`,`status`,`id_tipo_mov`),
  CONSTRAINT `fk_sal_tipo` FOREIGN KEY (`id_tipo_mov`) REFERENCES `tipos_movimientos` (`id_tipo_mov`),
  CONSTRAINT `fk_sal_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `salidas` (`id_salida`,`nro_factura_manual`,`nro_control`,`cliente`,`rif_cliente`,`id_tipo_mov`,`id_usuario`,`fecha_salida`,`status`,`observaciones`) VALUES
('1','NDE-000001','00-00000001','TALLER MECÁNICO EL TURBO C.A.','J-41234567-8','1','1','2026-07-20 00:00:00','Activa',''),
('2','NDE-000002','00-00000002','TRANSPORTE RODRÍGUEZ','J-42345678-9','1','1','2026-07-21 00:00:00','Activa','');

CREATE TABLE `sku_contadores` (
  `sku_prefix` varchar(20) NOT NULL,
  `ultimo_numero` int(11) DEFAULT 0,
  PRIMARY KEY (`sku_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sku_contadores` (`sku_prefix`,`ultimo_numero`) VALUES
('CTRL','5'),
('FAC','5'),
('PROD','53');

CREATE TABLE `tipos_movimientos` (
  `id_tipo_mov` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo_movimiento` enum('Entrada','Salida') NOT NULL,
  PRIMARY KEY (`id_tipo_mov`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tipos_movimientos` (`id_tipo_mov`,`nombre`,`tipo_movimiento`) VALUES
('1','Venta','Salida'),
('2','Mermas','Salida'),
('3','Regalias','Salida'),
('4','Daños','Salida'),
('5','Devoluciones','Entrada'),
('6','Ajuste de Inventario','Entrada'),
('7','Compra','Entrada');

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` (`id_usuario`,`usuario`,`correo`,`password`,`id_rol`,`status`,`aprobado`,`pregunta_seguridad`,`respuesta_seguridad`) VALUES
('1','Administrador','admin@jv3000.com','$2y$10$s.9VU8M6.Y9DhwkLU4FiZOWpjfEHySGer/fz8b8Li06go5epEcKky','1','Activo','1','Nombre de tu mascota','$2y$10$m4gAG5wq1mWddoZsLFZF7u587virmloOs3BNhwkBRA0qpTLpkRnBG'),
('2','Operador','operador@jv3000.com','$2y$10$54WnFBypdQamS9JuST.fleETcDCwsW1Trk./FLhtAYpvdoMiGl6yi','2','Activo','1','Nombre de tu mascota','$2y$10$X6.tFFcCnrL21m9Ji3QBreO1870X7MIid7nGpykNQQPgwSwJNqKoy'),
('3','Operador_Ventas','ventas123@gmail.com','$2y$10$6u/bDddDy7Tc2KM/IkGG5OMX/M0W7eic..rWyCkHWiPPiWZqnKb8W','3','Activo','1','Nombre de tu mascota','$2y$10$vdHIKGFfG1JTFUqH1kBWz.BojzTZ3RIJ5/1xtNXfvQLeGgZIqErE2');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

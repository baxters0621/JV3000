-- JV3000 C.A. - Base de Datos Portable v5 (Instalación limpia)
-- Estructura completa + datos de sistema (roles, tipos, configuración, usuarios, contadores).
-- Sin datos demo: categorías, proveedores, clientes, productos, catálogo de costos y movimientos se inician vacíos.
-- Novedades v5: tabla catalogo_costos (entidad asociativa Proveedor-Producto);
-- sin sistema de crédito (limite_credito/dias_credito/condiciones_pago eliminados);
-- productos.id_proveedor eliminado (la relación vive SOLO en el catálogo).
-- Usuarios iniciales: Administrador / Admin123* (cambiar tras el primer inicio).

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

START TRANSACTION;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria` (
  `id_auditoria` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(50) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` text DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_origen` varchar(45) DEFAULT NULL,
  `ruta` varchar(255) DEFAULT NULL,
  `metodo` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_auditoria`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_fecha` (`fecha_hora`)
) ENGINE=InnoDB AUTO_INCREMENT=249 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catalogo_costos` (
  `id_catalogo` int(11) NOT NULL AUTO_INCREMENT,
  `id_proveedor` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `codigo_proveedor` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_catalogo`),
  UNIQUE KEY `uk_prov_prod` (`id_proveedor`,`id_producto`),
  KEY `fk_cat_prod` (`id_producto`),
  CONSTRAINT `fk_cat_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_prov` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `documento` varchar(20) DEFAULT NULL COMMENT 'RIF o c??dula',
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `status` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `idx_cli_documento` (`documento`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compras` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `nro_factura` varchar(50) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_compra` timestamp NOT NULL DEFAULT current_timestamp(),
  `nro_control` varchar(20) DEFAULT NULL,
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
  `detalle_pago` longtext DEFAULT NULL CHECK (json_valid(`detalle_pago`)),
  `estado_recepcion` enum('Pendiente','Parcial','Completa') DEFAULT 'Pendiente',
  PRIMARY KEY (`id_compra`),
  UNIQUE KEY `uq_comp_prov_factura` (`id_proveedor`,`nro_factura`),
  KEY `fk_comp_prov` (`id_proveedor`),
  KEY `fk_comp_user` (`id_usuario`),
  KEY `idx_comp_status` (`status`),
  KEY `idx_comp_fecha` (`fecha_compra`),
  CONSTRAINT `fk_comp_prov` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  CONSTRAINT `fk_comp_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizado` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_compras` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_costo` decimal(10,2) NOT NULL,
  `cantidad_recibida` int(11) NOT NULL DEFAULT 0,
  `fecha_vencimiento` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detcomp_compra` (`id_compra`),
  KEY `fk_detcomp_producto` (`id_producto`),
  CONSTRAINT `fk_detcomp_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE,
  CONSTRAINT `fk_detcomp_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  CONSTRAINT `fk_detsal_lote` FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`) ON DELETE SET NULL,
  CONSTRAINT `fk_detsal_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `fk_detsal_salida` FOREIGN KEY (`id_salida`) REFERENCES `salidas` (`id_salida`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_solicitud_compra` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_solicitud` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad_solicitada` int(11) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_dsc_solicitud` (`id_solicitud`),
  KEY `fk_dsc_producto` (`id_producto`),
  CONSTRAINT `fk_dsc_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `fk_dsc_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes_compra` (`id_solicitud`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `intentos` int(11) DEFAULT 0,
  `ultimo_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ip_unique` (`ip_address`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lotes` (
  `id_lote` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_compra` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `cantidad_restante` int(11) NOT NULL DEFAULT 0,
  `precio_costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_vencimiento` date NOT NULL,
  `fecha_ingreso` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_lote`),
  KEY `fk_lot_prod` (`id_producto`),
  KEY `fk_lot_compra` (`id_compra`),
  KEY `idx_lot_venc` (`fecha_vencimiento`),
  CONSTRAINT `fk_lot_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE SET NULL,
  CONSTRAINT `fk_lot_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos` (
  `id_movimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_referencia` int(11) NOT NULL,
  `tipo_referencia` enum('compra','venta') NOT NULL,
  `tipo` enum('Entrada','Salida') NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Activo','Anulado') NOT NULL DEFAULT 'Activo',
  `documento_recepcion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_movimiento`),
  KEY `idx_ref` (`tipo_referencia`,`id_referencia`),
  KEY `fk_mov_usuario` (`id_usuario`),
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `idx_sku` (`sku`),
  KEY `fk_prod_cat` (`id_categoria`),
  KEY `idx_prod_status` (`status`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL AUTO_INCREMENT,
  `rif` varchar(20) NOT NULL,
  `nombre_empresa` varchar(150) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `lead_time` int(11) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT 'USD',
  `status` enum('Activo','Inactivo') DEFAULT 'Activo',
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  PRIMARY KEY (`id_proveedor`),
  UNIQUE KEY `idx_rif` (`rif`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `idx_nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `idx_sal_status` (`status`),
  KEY `idx_sal_fecha` (`fecha_salida`),
  KEY `idx_sal_fecha_status_tipo` (`fecha_salida`,`status`,`id_tipo_mov`),
  KEY `fk_sal_cliente` (`id_cliente`),
  CONSTRAINT `fk_sal_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL,
  CONSTRAINT `fk_sal_tipo` FOREIGN KEY (`id_tipo_mov`) REFERENCES `tipos_movimientos` (`id_tipo_mov`),
  CONSTRAINT `fk_sal_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sku_contadores` (
  `sku_prefix` varchar(20) NOT NULL,
  `ultimo_numero` int(11) DEFAULT 0,
  PRIMARY KEY (`sku_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitudes_compra` (
  `id_solicitud` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario_solicitante` int(11) NOT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `motivo` varchar(150) DEFAULT NULL,
  `estado` enum('Pendiente','Atendida','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `id_compra` int(11) DEFAULT NULL,
  `fecha_atendida` datetime DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `fk_sol_user` (`id_usuario_solicitante`),
  KEY `fk_sol_compra` (`id_compra`),
  KEY `idx_sol_estado` (`estado`),
  CONSTRAINT `fk_sol_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE SET NULL,
  CONSTRAINT `fk_sol_user` FOREIGN KEY (`id_usuario_solicitante`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_movimientos` (
  `id_tipo_mov` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo_movimiento` enum('Entrada','Salida') NOT NULL,
  PRIMARY KEY (`id_tipo_mov`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(20) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_check` varchar(64) DEFAULT NULL COMMENT 'SHA-256 de password en minúsculas para detección de duplicados',
  `id_rol` int(11) DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `aprobado` tinyint(1) DEFAULT 0 COMMENT '0=Pendiente, 1=Aprobado',
  `pregunta_seguridad` varchar(200) DEFAULT NULL,
  `respuesta_seguridad` varchar(255) DEFAULT NULL,
  `pin_emergencia` varchar(60) DEFAULT NULL COMMENT 'bcrypt hash de PIN 6 digitos de emergencia',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `idx_user` (`usuario`),
  UNIQUE KEY `idx_email` (`correo`),
  KEY `fk_rol` (`id_rol`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- --------------------------------------------------------
-- Datos de sistema
-- --------------------------------------------------------

INSERT INTO `roles` VALUES (1,'Administrador','Acceso total al sistema','2026-07-23 21:54:53'),(2,'Operador de Carga','Gesti??n de compras, productos, proveedores y categor??as','2026-07-23 21:54:53'),(3,'Operador de Ventas','Gesti??n de ventas, reportes y estad??sticas','2026-07-23 21:54:53');
INSERT INTO `tipos_movimientos` VALUES (1,'Venta','Salida'),(2,'Mermas','Salida'),(3,'Regalias','Salida'),(4,'Da??os','Salida'),(5,'Devoluciones','Entrada'),(6,'Ajuste de Inventario','Entrada'),(7,'Compra','Entrada');
INSERT INTO `configuracion` VALUES (1,'iva_porcentaje','16','Porcentaje de IVA aplicado a las ventas','2026-07-14 19:18:02'),(2,'empresa_nombre','JV3000 C.A.','Nombre de la empresa','2026-07-14 19:18:02'),(3,'empresa_rif','J-50287309-0','RIF de la empresa','2026-07-14 21:18:43'),(4,'empresa_telefono','+58 0414-4014690','Tel??fono de la empresa','2026-08-06 01:46:15'),(5,'empresa_direccion','Naguanagua, Edo. Carabobo','Direcci??n de la empresa','2026-08-08 22:41:40'),(6,'empresa_email','jv3000ca@gmail.com','Correo de la empresa','2026-07-14 21:17:48'),(7,'documentos_normalizados','1','Migracion de formato de documento fiscal aplicada (v1)','2026-08-06 23:31:23');
INSERT INTO `usuarios` (`id_usuario`, `usuario`, `correo`, `password`, `password_check`, `id_rol`, `status`, `aprobado`, `pregunta_seguridad`, `respuesta_seguridad`, `pin_emergencia`) VALUES (1,'Administrador','admin@jv3000.com','$2y$10$rBAWGt9S7uWap0xNOF6UxO7.Urg.gPSgRBe6TuVwfLKv9PXXv9aDe','0208788aa2035cd5be6697efbd285df1afa881c8fd25e4bd5bbb247c29c58454',1,'Activo',1,'Nombre de tu mascota','$2y$10$m4gAG5wq1mWddoZsLFZF7u587virmloOs3BNhwkBRA0qpTLpkRnBG',NULL),(2,'Operador','operador@jv3000.com','$2y$10$17w0GaA5NJy2ieJhkJEIUeQwi43V.VV.Q7yFfis1iwh2567fQz5wy','215734bd5c5e147ffeb1d2f533141b964de8de2e1e41fabb17fe8c537220aec6',2,'Inactivo',0,'Nombre de tu mascota','$2y$10$X6.tFFcCnrL21m9Ji3QBreO1870X7MIid7nGpykNQQPgwSwJNqKoy',NULL),(3,'Operador_Ventas','ventas123@gmail.com','$2y$10$2s3JyEMFmHGUHvPqKMwqF.IRXwfsC18a/vK9P9tm5Heh2NDLCMs0S','0b1bb2dfcfcf243e527b16be4d68627b71f40d36245070c82550ff42a12f61f5',3,'Activo',1,'Nombre de tu mascota','$2y$10$vdHIKGFfG1JTFUqH1kBWz.BojzTZ3RIJ5/1xtNXfvQLeGgZIqErE2',NULL);
INSERT INTO `sku_contadores` VALUES ('CAT',0),('CTRL',0),('FAC',0),('NDE',0),('PROD',0);


COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
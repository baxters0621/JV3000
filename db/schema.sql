-- JV3000 C.A. - Esquema de base de datos
-- Ejecutar como root: mysql -u root < db/schema.sql

CREATE DATABASE IF NOT EXISTS jv3000_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE jv3000_db;

-- ============================================================
-- USUARIOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
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

-- ============================================================
-- AUDITORÍA
-- ============================================================
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id_auditoria` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(50) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` text DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_auditoria`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_fecha` (`fecha_hora`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- CATEGORÍAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `categorias` (
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
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `idx_categoria_nombre` (`nombre`),
  UNIQUE KEY `idx_categoria_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PROVEEDORES
-- ============================================================
CREATE TABLE IF NOT EXISTS `proveedores` (
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
  UNIQUE KEY `idx_rif` (`rif`),
  UNIQUE KEY `idx_proveedor_nombre` (`nombre_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PRODUCTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `productos` (
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
  UNIQUE KEY `idx_producto_nombre` (`nombre_producto`),
  KEY `fk_prod_cat` (`id_categoria`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TIPOS DE MOVIMIENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `tipos_movimientos` (
  `id_tipo_mov` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo_movimiento` enum('Entrada','Salida') NOT NULL,
  PRIMARY KEY (`id_tipo_mov`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- SALIDAS (Ventas)
-- ============================================================
CREATE TABLE IF NOT EXISTS `salidas` (
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
  `status` varchar(20) DEFAULT 'Activa',
  PRIMARY KEY (`id_salida`),
  KEY `fk_sal_prod` (`id_producto`),
  KEY `fk_sal_user` (`id_usuario`),
  KEY `idx_salidas_fecha` (`fecha_salida`),
  KEY `idx_salidas_tipo_fecha` (`id_tipo_mov`,`fecha_salida`),
  CONSTRAINT `fk_sal_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `fk_sal_tipo` FOREIGN KEY (`id_tipo_mov`) REFERENCES `tipos_movimientos` (`id_tipo_mov`),
  CONSTRAINT `fk_sal_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- COMPRAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `compras` (
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
  `status` varchar(20) DEFAULT 'Activa',
  `nro_control` varchar(20) DEFAULT NULL,
  `condiciones_pago` enum('Contado','Credito') DEFAULT 'Contado',
  `dias_plazo` int(11) DEFAULT 0,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `iva` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id_compra`),
  UNIQUE KEY `uq_nro_control` (`nro_control`),
  KEY `fk_comp_prov` (`id_proveedor`),
  KEY `fk_comp_user` (`id_usuario`),
  KEY `fk_comp_prod` (`id_producto`),
  KEY `idx_compras_fecha` (`fecha_compra`),
  CONSTRAINT `fk_comp_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `fk_comp_prov` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  CONSTRAINT `fk_comp_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- DETALLE COMPRAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `detalle_compras` (
  `id_detalle_compra` int(11) NOT NULL AUTO_INCREMENT,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  PRIMARY KEY (`id_detalle_compra`),
  KEY `fk_detc_comp` (`id_compra`),
  KEY `fk_detc_prod` (`id_producto`),
  CONSTRAINT `fk_detc_comp` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE,
  CONSTRAINT `fk_detc_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- MOVIMIENTOS / DETALLE MOVIMIENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `movimientos` (
  `numero_movimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_tipo_mov` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`numero_movimiento`),
  KEY `fk_mov_tipo` (`id_tipo_mov`),
  KEY `fk_mov_user` (`id_usuario`),
  CONSTRAINT `fk_mov_tipo` FOREIGN KEY (`id_tipo_mov`) REFERENCES `tipos_movimientos` (`id_tipo_mov`),
  CONSTRAINT `fk_mov_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `detalle_movimientos` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `numero_movimiento` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_restante` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detm_mov` (`numero_movimiento`),
  CONSTRAINT `fk_detm_mov` FOREIGN KEY (`numero_movimiento`) REFERENCES `movimientos` (`numero_movimiento`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- ÓRDENES DE COMPRA
-- ============================================================
CREATE TABLE IF NOT EXISTS `ordenes_compra` (
  `id_oc` int(11) NOT NULL AUTO_INCREMENT,
  `numero_oc` varchar(20) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_proveedor` int(11) DEFAULT NULL,
  `estado` enum('Pendiente','Aprobada','Recibida','Cancelada') DEFAULT 'Pendiente',
  `total` decimal(12,2) DEFAULT 0.00,
  `id_usuario` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_oc`),
  UNIQUE KEY `numero_oc` (`numero_oc`),
  KEY `id_proveedor` (`id_proveedor`),
  KEY `fk_oc_user` (`id_usuario`),
  CONSTRAINT `fk_oc_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `ordenes_compra_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `detalle_orden_compra` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_oc` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id_detalle`),
  KEY `id_oc` (`id_oc`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_orden_compra_ibfk_1` FOREIGN KEY (`id_oc`) REFERENCES `ordenes_compra` (`id_oc`) ON DELETE CASCADE,
  CONSTRAINT `detalle_orden_compra_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONFIGURACIÓN GENERAL
-- ============================================================
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizado` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- SKU CONTADORES
-- ============================================================
CREATE TABLE IF NOT EXISTS `sku_contadores` (
  `sku_prefix` varchar(20) NOT NULL,
  `ultimo_numero` int(11) DEFAULT 0,
  PRIMARY KEY (`sku_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- LOGIN INTENTOS (rate limiting)
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `intentos` int(11) DEFAULT 0,
  `ultimo_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

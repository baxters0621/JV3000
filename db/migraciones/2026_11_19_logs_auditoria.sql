-- ==========================================
-- JV3000 C.A. — Migración: módulo de Auditoría
-- ==========================================
-- Crea logs_auditoria y copia los eventos históricos de auditoria.
-- Es idempotente: puede ejecutarse más de una vez sin duplicar filas.
-- La tabla auditoria se conserva como archivo de solo lectura.

CREATE TABLE IF NOT EXISTS `logs_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(100) NOT NULL DEFAULT 'Sistema',
  `accion` varchar(50) NOT NULL,
  `modulo` varchar(50) NOT NULL DEFAULT 'Sistema',
  `descripcion` text DEFAULT NULL,
  `ip_origen` varchar(45) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_logs_fecha` (`fecha_hora`),
  KEY `idx_logs_usuario` (`id_usuario`),
  KEY `idx_logs_modulo` (`modulo`),
  KEY `idx_logs_accion` (`accion`),
  CONSTRAINT `fk_logs_auditoria_usuario`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

START TRANSACTION;

INSERT INTO `logs_auditoria`
  (`id`, `id_usuario`, `usuario_nombre`, `accion`, `modulo`, `descripcion`, `ip_origen`, `fecha_hora`)
SELECT
  a.`id_auditoria`,
  CASE
    WHEN a.`id_usuario` IS NULL OR a.`id_usuario` = 0 OR u.`id_usuario` IS NULL THEN NULL
    ELSE a.`id_usuario`
  END,
  COALESCE(NULLIF(TRIM(a.`usuario_nombre`), ''), 'Sistema'),
  UPPER(TRIM(a.`accion`)),
  CASE
    WHEN LOWER(COALESCE(a.`ruta`, '')) LIKE '%backup%' THEN 'Backup'
    WHEN LOWER(COALESCE(a.`ruta`, '')) LIKE '%login%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%usuarios%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%perfil%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%recuperar%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%resetear%' THEN 'Seguridad'
    WHEN LOWER(COALESCE(a.`ruta`, '')) LIKE '%compras%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%proveedores%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%solicitudes%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%pago%' THEN 'Compras'
    WHEN LOWER(COALESCE(a.`ruta`, '')) LIKE '%salidas%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%clientes%' THEN 'Ventas'
    WHEN LOWER(COALESCE(a.`ruta`, '')) LIKE '%productos%'
      OR LOWER(COALESCE(a.`ruta`, '')) LIKE '%categorias%' THEN 'Inventario'
    WHEN LOWER(a.`accion`) IN ('login', 'logout') THEN 'Seguridad'
    WHEN LOWER(COALESCE(a.`detalle`, '')) LIKE '%venta%'
      OR LOWER(COALESCE(a.`detalle`, '')) LIKE '%salida%' THEN 'Ventas'
    WHEN LOWER(COALESCE(a.`detalle`, '')) LIKE '%compra%'
      OR LOWER(COALESCE(a.`detalle`, '')) LIKE '%proveedor%'
      OR LOWER(COALESCE(a.`detalle`, '')) LIKE '%tasa bcv%' THEN 'Compras'
    WHEN LOWER(COALESCE(a.`detalle`, '')) LIKE '%producto%'
      OR LOWER(COALESCE(a.`detalle`, '')) LIKE '%stock%'
      OR LOWER(COALESCE(a.`detalle`, '')) LIKE '%categor%' THEN 'Inventario'
    ELSE 'Sistema'
  END,
  COALESCE(NULLIF(TRIM(a.`detalle`), ''), 'Evento migrado desde auditoría'),
  NULLIF(TRIM(a.`ip_origen`), ''),
  a.`fecha_hora`
FROM `auditoria` a
LEFT JOIN `usuarios` u ON u.`id_usuario` = a.`id_usuario`
LEFT JOIN `logs_auditoria` l ON l.`id` = a.`id_auditoria`
WHERE l.`id` IS NULL;

COMMIT;

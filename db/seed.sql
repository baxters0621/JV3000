-- JV3000 C.A. - Datos iniciales
-- Ejecutar DESPUÉS de schema.sql

USE jv3000_db;

-- ============================================================
-- CONFIGURACIÓN DE LA EMPRESA
-- ============================================================
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('iva_porcentaje', '16', 'Porcentaje de IVA aplicado a las ventas'),
('empresa_nombre', 'JV3000 C.A.', 'Nombre de la empresa'),
('empresa_rif', 'J-502873090', 'RIF de la empresa'),
('empresa_telefono', '+58 04144014690', 'Teléfono de la empresa'),
('empresa_direccion', 'Valencia. Av Bolivar', 'Dirección de la empresa'),
('imprenta_nombre', 'IMPRENTA AUTORIZADA', 'Nombre del impresor autorizado'),
('imprenta_rif', 'J-00000000-0', 'RIF del impresor'),
('imprenta_providencia', '00000', 'N° Providencia SENIAT del talonario');

-- ============================================================
-- TIPOS DE MOVIMIENTOS
-- ============================================================
INSERT INTO tipos_movimientos (nombre, tipo_movimiento) VALUES
('Venta', 'Salida'),
('Mermas', 'Salida'),
('Regalias', 'Salida'),
('Daños', 'Salida'),
('Devoluciones', 'Entrada'),
('Ajuste de Inventario', 'Entrada'),
('Compra', 'Entrada');

-- ============================================================
-- SKU CONTADORES INICIALES
-- ============================================================
INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES
('CAT', 0),
('FAC', 0),
('PROD', 0);

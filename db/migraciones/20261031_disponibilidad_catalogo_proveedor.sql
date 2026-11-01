ALTER TABLE catalogo_costos
    ADD COLUMN stock_disponible INT UNSIGNED NOT NULL DEFAULT 0 AFTER costo;

ALTER TABLE compras
    ADD COLUMN stock_proveedor_reservado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado_recepcion;

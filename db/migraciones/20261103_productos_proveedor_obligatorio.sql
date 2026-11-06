ALTER TABLE productos
    ADD COLUMN id_proveedor INT(11) NULL DEFAULT NULL AFTER id_categoria,
    ADD KEY fk_prod_prov (id_proveedor),
    ADD CONSTRAINT fk_prod_prov FOREIGN KEY (id_proveedor) REFERENCES proveedores (id_proveedor) ON DELETE SET NULL;

UPDATE productos p
JOIN (
    SELECT id_producto, MIN(id_catalogo) AS id_catalogo
    FROM catalogo_costos
    GROUP BY id_producto
) c ON c.id_producto = p.id_producto
JOIN catalogo_costos cc ON cc.id_catalogo = c.id_catalogo
SET p.id_proveedor = cc.id_proveedor
WHERE p.id_proveedor IS NULL;

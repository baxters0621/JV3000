-- ==========================================
-- JV3000 C.A. - Migración: unicidad del número de control de compras
-- ==========================================
-- Objetivo:
--   Evitar que dos compras compartan el mismo `compras.nro_control`.
--
-- Seguridad:
--   - No borra ni modifica datos.
--   - Si existen duplicados, muestra las filas afectadas y aborta con SIGNAL.
--   - Si no existen, crea la restricción UNIQUE.
--   - Es idempotente: si la restricción ya existe, no hace nada.
--
-- Uso:
--   mysql -u root jv3000_db < db/migraciones/2026_11_23_compras_nro_control_unico.sql
--
-- Nota: los valores NULL no se consideran duplicados; UNIQUE permite varios
-- NULL, pero la aplicación exige un número de control no vacío al registrar.

DROP PROCEDURE IF EXISTS `jv_migrar_compras_control_unico`;

DELIMITER //
CREATE PROCEDURE `jv_migrar_compras_control_unico`()
BEGIN
    DECLARE v_existe INT DEFAULT 0;
    DECLARE v_duplicados INT DEFAULT 0;

    SELECT COUNT(*) INTO v_existe
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'compras'
      AND INDEX_NAME = 'uq_compras_nro_control'
      AND NON_UNIQUE = 0;

    IF v_existe > 0 THEN
        SELECT 'La restriccion uq_compras_nro_control ya existe. No se realizaron cambios.' AS resultado;
    ELSE
        SELECT COUNT(*) INTO v_duplicados
        FROM (
            SELECT nro_control
            FROM compras
            WHERE nro_control IS NOT NULL
            GROUP BY nro_control
            HAVING COUNT(*) > 1
        ) AS d;

        IF v_duplicados > 0 THEN
            SELECT nro_control, COUNT(*) AS cantidad
            FROM compras
            WHERE nro_control IS NOT NULL
            GROUP BY nro_control
            HAVING COUNT(*) > 1
            ORDER BY nro_control;

            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'No se pudo crear uq_compras_nro_control: hay controles duplicados. Corrija los datos manualmente.';
        ELSE
            ALTER TABLE compras
                ADD CONSTRAINT uq_compras_nro_control UNIQUE (nro_control);

            SELECT 'Restriccion uq_compras_nro_control creada correctamente.' AS resultado;
        END IF;
    END IF;
END//
DELIMITER ;

CALL jv_migrar_compras_control_unico();

DROP PROCEDURE IF EXISTS `jv_migrar_compras_control_unico`;

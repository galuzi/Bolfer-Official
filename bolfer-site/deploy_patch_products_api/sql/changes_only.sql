-- Alteracoes incrementais do banco
-- Projeto: Bolfer Official
-- Data: 2026-03-22
--
-- Campo novo para compra minima por produto.
-- Execute este arquivo no banco ja existente antes de publicar a funcionalidade.

SET @has_minimum_quantity := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'minimum_quantity'
);

SET @sql := IF(
  @has_minimum_quantity = 0,
  'ALTER TABLE products ADD COLUMN minimum_quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER stock',
  'SELECT ''Campo minimum_quantity ja existia.'' AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE products
SET minimum_quantity = 1
WHERE minimum_quantity IS NULL OR minimum_quantity < 1;

SELECT 'Campo minimum_quantity aplicado com sucesso.' AS status;

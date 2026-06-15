-- Alteracoes incrementais do banco
-- Projeto: Bolfer Official
-- Data: 2026-03-29

SET @has_orders_user_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'user_id'
);

SET @sql := IF(
  @has_orders_user_id = 0,
  'ALTER TABLE orders ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER product_id',
  'SELECT ''Campo user_id ja existia em orders.'' AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_orders_user_status_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'idx_orders_user_status'
);

SET @sql := IF(
  @has_orders_user_status_index = 0,
  'ALTER TABLE orders ADD INDEX idx_orders_user_status (user_id, status)',
  'SELECT ''Indice idx_orders_user_status ja existia.'' AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Campo user_id em orders aplicado com sucesso.' AS status;
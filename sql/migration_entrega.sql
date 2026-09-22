-- =========================================================
-- VISZ Closet — Migração: pedidos com ENTREGA
-- Rode este arquivo UMA VEZ se você já tinha o banco instalado
-- antes desta atualização. Em instalações novas, o schema.sql
-- já vem com estes campos.
-- =========================================================

ALTER TABLE orders
  ADD COLUMN delivery_recipient  VARCHAR(150) NULL AFTER user_id,
  ADD COLUMN delivery_phone      VARCHAR(30)  NULL AFTER delivery_recipient,
  ADD COLUMN delivery_zip        VARCHAR(15)  NULL AFTER delivery_phone,
  ADD COLUMN delivery_address    VARCHAR(255) NULL AFTER delivery_zip,
  ADD COLUMN delivery_number     VARCHAR(20)  NULL AFTER delivery_address,
  ADD COLUMN delivery_complement VARCHAR(100) NULL AFTER delivery_number,
  ADD COLUMN delivery_district   VARCHAR(100) NULL AFTER delivery_complement,
  ADD COLUMN delivery_city       VARCHAR(100) NULL AFTER delivery_district,
  ADD COLUMN delivery_state      VARCHAR(2)   NULL AFTER delivery_city,
  ADD COLUMN delivery_notes      TEXT         NULL AFTER delivery_state,
  ADD COLUMN delivery_fee        DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER delivery_notes;

ALTER TABLE settings
  ADD COLUMN delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN delivery_free_above DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN delivery_info VARCHAR(255) NOT NULL DEFAULT 'Entregamos em toda a região metropolitana do Recife.';

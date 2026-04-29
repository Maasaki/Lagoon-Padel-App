-- PayZen / Lyra : statut de paiement sur les réservations
-- Exécuter sur la base existante (une seule fois).

ALTER TABLE reservations
  ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'paid' COMMENT 'pending, paid, failed, cancelled' AFTER price,
  ADD COLUMN payment_expires_at DATETIME NULL DEFAULT NULL AFTER payment_status,
  ADD COLUMN payzen_order_id VARCHAR(80) NULL DEFAULT NULL AFTER payment_expires_at,
  ADD UNIQUE KEY uq_reservations_payzen_order (payzen_order_id);

-- Réservations déjà en base = considérées payées
UPDATE reservations SET payment_expires_at = NULL WHERE payment_status = 'paid';

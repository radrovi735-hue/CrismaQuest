-- Registra automaticamente toda entrada positiva de moedas/Lúmens no extrato CrismaQuest.
-- Gastos do Correio são registrados explicitamente pelo serviço social com descrição detalhada.

DROP TRIGGER IF EXISTS cq_lumen_positive_ledger;

CREATE TRIGGER cq_lumen_positive_ledger
AFTER UPDATE ON ct_studenti
FOR EACH ROW
INSERT INTO cq_lumen_ledger (user_id, delta, balance_after, reason_type, reason_ref, description)
SELECT NEW.fk_utente, NEW.monete - OLD.monete, NEW.monete, 'reward', NULL, 'Lúmens recebidos'
WHERE NEW.monete > OLD.monete;

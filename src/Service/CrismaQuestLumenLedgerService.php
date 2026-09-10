<?php

namespace App\Service;

use PDO;
use Throwable;

/**
 * Reconcilia o extrato de Lúmens no nível da aplicação, sem depender de
 * privilégios especiais de MySQL (como CREATE TRIGGER), o que mantém o app
 * compatível com hospedagens compartilhadas gratuitas.
 */
final class CrismaQuestLumenLedgerService
{
    public function reconcileCurrentStudent(): void
    {
        try {
            $permission = new PermissionService();
            if ($permission->checkPermissionsStudent() !== PermissionService::STATUS_OK) return;

            $userId = (int)($permission->getCurrentUserId() ?? 0);
            $classId = (int)($permission->getCurrentClassId() ?? 0);
            if ($userId <= 0 || $classId <= 0) return;

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT s.monete
                 FROM ct_studenti s
                 INNER JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
                 WHERE s.fk_utente=:u AND sc.fk_classe=:c
                 LIMIT 1'
            );
            $stmt->execute(['u'=>$userId,'c'=>$classId]);
            $current = $stmt->fetchColumn();
            if ($current === false) return;
            $current = (int)$current;

            $last = $pdo->prepare('SELECT balance_after FROM cq_lumen_ledger WHERE user_id=:u ORDER BY id DESC LIMIT 1');
            $last->execute(['u'=>$userId]);
            $previous = $last->fetchColumn();

            if ($previous === false) {
                if ($current !== 0) {
                    $this->insert($pdo, $userId, $current, $current, 'sync', 'Saldo inicial de Lúmens');
                }
                return;
            }

            $previous = (int)$previous;
            if ($previous === $current) return;

            $delta = $current - $previous;
            $description = $delta > 0 ? 'Lúmens recebidos' : 'Ajuste de Lúmens';
            $this->insert($pdo, $userId, $delta, $current, 'sync', $description);
        } catch (Throwable) {
            // O extrato nunca deve impedir o acesso ao app.
        }
    }

    private function insert(PDO $pdo, int $userId, int $delta, int $balance, string $type, string $description): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO cq_lumen_ledger
                (user_id,delta,balance_after,reason_type,reason_ref,description)
             VALUES (:u,:d,:b,:t,NULL,:txt)'
        );
        $stmt->execute(['u'=>$userId,'d'=>$delta,'b'=>$balance,'t'=>$type,'txt'=>$description]);
    }
}

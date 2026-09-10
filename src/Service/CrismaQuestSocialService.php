<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class CrismaQuestSocialService
{
    private const NOTE_LIMIT_PER_DAY = 5;
    private const NOTE_KEYS = [
        'boa_missao' => 'Boa missão esta semana! 🙏',
        'rezando' => 'Estou rezando por você. 🕊️',
        'parabens' => 'Parabéns pela etapa da Jornada! ✨',
        'espirito_santo' => 'Que o Espírito Santo te acompanhe. 🔥',
        'comunidade' => 'Que bom caminhar com você nesta comunidade. 🤝',
    ];

    public function getStudentPageData(): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) {
            return $ctx;
        }

        $pdo = Database::getConnection();
        $userId = $ctx['userId'];
        $classId = $ctx['classId'];

        $pdo->prepare('UPDATE cq_peer_notes SET read_at = COALESCE(read_at, NOW()) WHERE recipient_user_id = :u')->execute(['u' => $userId]);
        $pdo->prepare('UPDATE cq_gifts SET opened_at = COALESCE(opened_at, NOW()) WHERE recipient_user_id = :u')->execute(['u' => $userId]);

        return $ctx + [
            'messageOptions' => self::NOTE_KEYS,
            'classmates' => $this->classmates($classId, $userId),
            'catalog' => $this->giftCatalog(),
            'receivedNotes' => $this->notes($classId, $userId, true),
            'sentNotes' => $this->notes($classId, $userId, false),
            'receivedGifts' => $this->gifts($classId, $userId, true),
            'sentGifts' => $this->gifts($classId, $userId, false),
            'cards' => $this->userCards($userId),
            'pendingTrades' => $this->pendingTrades($classId, $userId),
            'sentTrades' => $this->sentTrades($classId, $userId),
            'cosmetics' => $this->ownedCosmetics($userId),
            'ledger' => $this->ledger($userId),
        ];
    }

    public function sendNote(array $input): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $ctx;

        $recipient = (int) ($input['recipient_user_id'] ?? 0);
        $key = (string) ($input['message_key'] ?? '');
        $custom = trim((string) ($input['custom_text'] ?? ''));
        if (!isset(self::NOTE_KEYS[$key])) return $this->error('Escolha uma mensagem válida.');
        if (mb_strlen($custom) > 120) return $this->error('O bilhete pode ter no máximo 120 caracteres.');
        if (!$this->isClassmate($ctx['classId'], $ctx['userId'], $recipient)) return $this->error('Destinatário inválido.');

        $today = (new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza')))->format('Y-m-d');
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM cq_peer_notes WHERE sender_user_id = :u AND DATE(created_at) = :d');
        $stmt->execute(['u' => $ctx['userId'], 'd' => $today]);
        if ((int) $stmt->fetchColumn() >= self::NOTE_LIMIT_PER_DAY) return $this->error('Você atingiu o limite de 5 bilhetes de hoje.');

        $stmt = Database::getConnection()->prepare('INSERT INTO cq_peer_notes (class_id,sender_user_id,recipient_user_id,message_key,custom_text) VALUES (:c,:s,:r,:k,:t)');
        $stmt->execute(['c' => $ctx['classId'], 's' => $ctx['userId'], 'r' => $recipient, 'k' => $key, 't' => $custom === '' ? null : $custom]);
        return $this->success('Bilhete enviado pelo Correio da Jornada.');
    }

    public function sendGift(array $input): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $ctx;
        $recipient = (int) ($input['recipient_user_id'] ?? 0);
        $giftId = (int) ($input['gift_catalog_id'] ?? 0);
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 120) return $this->error('A dedicatória pode ter no máximo 120 caracteres.');
        if (!$this->isClassmate($ctx['classId'], $ctx['userId'], $recipient)) return $this->error('Destinatário inválido.');

        $pdo = Database::getConnection();
        $gift = $pdo->prepare('SELECT * FROM cq_gift_catalog WHERE id = :id AND active = 1 LIMIT 1');
        $gift->execute(['id' => $giftId]);
        $item = $gift->fetch(PDO::FETCH_ASSOC);
        if (!$item) return $this->error('Presente indisponível.');
        $cost = (int) $item['cost_lumens'];

        try {
            $pdo->beginTransaction();
            $balanceStmt = $pdo->prepare('SELECT monete FROM ct_studenti WHERE id_studente = :id FOR UPDATE');
            $balanceStmt->execute(['id' => $ctx['studentId']]);
            $balance = (int) $balanceStmt->fetchColumn();
            if ($balance < $cost) throw new \RuntimeException('Lúmens insuficientes.');

            $newBalance = $balance - $cost;
            $pdo->prepare('UPDATE ct_studenti SET monete = :b WHERE id_studente = :id')->execute(['b' => $newBalance, 'id' => $ctx['studentId']]);
            $stmt = $pdo->prepare('INSERT INTO cq_gifts (class_id,sender_user_id,recipient_user_id,gift_catalog_id,cost_lumens,note) VALUES (:c,:s,:r,:g,:cost,:n)');
            $stmt->execute(['c' => $ctx['classId'], 's' => $ctx['userId'], 'r' => $recipient, 'g' => $giftId, 'cost' => $cost, 'n' => $note === '' ? null : $note]);
            $giftRecordId = (int) $pdo->lastInsertId();

            if (!empty($item['cosmetic_slot'])) {
                $pdo->prepare('INSERT INTO cq_user_cosmetics (user_id,gift_catalog_id,cosmetic_slot,equipped) VALUES (:u,:g,:slot,0) ON DUPLICATE KEY UPDATE obtained_at=obtained_at')
                    ->execute(['u' => $recipient, 'g' => $giftId, 'slot' => $item['cosmetic_slot']]);
            }
            $pdo->prepare('INSERT INTO cq_lumen_ledger (user_id,delta,balance_after,reason_type,reason_ref,description) VALUES (:u,:d,:b,"gift",:ref,:txt)')
                ->execute(['u' => $ctx['userId'], 'd' => -$cost, 'b' => $newBalance, 'ref' => (string) $giftRecordId, 'txt' => 'Presente: ' . $item['name']]);
            $pdo->commit();
            return $this->success('Presente enviado com sucesso.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage() ?: 'Não foi possível enviar o presente.');
        }
    }

    public function sendCardGift(array $input): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $ctx;
        $recipient = (int) ($input['recipient_user_id'] ?? 0);
        $editionId = (int) ($input['card_edition_id'] ?? 0);
        $note = trim((string) ($input['note'] ?? ''));
        if (!$this->isClassmate($ctx['classId'], $ctx['userId'], $recipient)) return $this->error('Destinatário inválido.');
        if ($editionId <= 0) return $this->error('Escolha uma carta.');

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            if ($this->cardQuantity($pdo, $ctx['userId'], $editionId, true) < 2) throw new \RuntimeException('Somente cartas repetidas podem ser presenteadas.');
            $this->transferCard($pdo, $ctx['userId'], $recipient, $editionId);
            $pdo->prepare('INSERT INTO cq_gifts (class_id,sender_user_id,recipient_user_id,card_edition_id,cost_lumens,note) VALUES (:c,:s,:r,:e,0,:n)')
                ->execute(['c' => $ctx['classId'], 's' => $ctx['userId'], 'r' => $recipient, 'e' => $editionId, 'n' => $note === '' ? null : $note]);
            $pdo->commit();
            return $this->success('Carta repetida presenteada com sucesso.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage() ?: 'Não foi possível presentear a carta.');
        }
    }

    public function proposeTrade(array $input): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $ctx;
        $recipient = (int) ($input['recipient_user_id'] ?? 0);
        $offered = (int) ($input['offered_card_edition_id'] ?? 0);
        $requested = (int) ($input['requested_card_edition_id'] ?? 0);
        if (!$this->isClassmate($ctx['classId'], $ctx['userId'], $recipient)) return $this->error('Destinatário inválido.');
        if ($offered <= 0 || $requested <= 0 || $offered === $requested) return $this->error('Escolha duas cartas diferentes.');
        $pdo = Database::getConnection();
        if ($this->cardQuantity($pdo, $ctx['userId'], $offered) < 2) return $this->error('A carta oferecida precisa ser repetida.');
        if ($this->cardQuantity($pdo, $recipient, $requested) < 1) return $this->error('O colega não possui a carta solicitada.');
        $stmt = $pdo->prepare('INSERT INTO cq_trade_offers (class_id,offerer_user_id,recipient_user_id,offered_card_edition_id,requested_card_edition_id) VALUES (:c,:o,:r,:a,:b)');
        $stmt->execute(['c'=>$ctx['classId'],'o'=>$ctx['userId'],'r'=>$recipient,'a'=>$offered,'b'=>$requested]);
        return $this->success('Proposta de troca enviada.');
    }

    public function respondTrade(int $tradeId, bool $accept): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $ctx;
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM cq_trade_offers WHERE id=:id AND recipient_user_id=:u AND class_id=:c AND status="pending" FOR UPDATE');
            $stmt->execute(['id'=>$tradeId,'u'=>$ctx['userId'],'c'=>$ctx['classId']]);
            $trade = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trade) throw new \RuntimeException('Proposta não encontrada ou já respondida.');
            if (!$accept) {
                $pdo->prepare('UPDATE cq_trade_offers SET status="declined", responded_at=NOW() WHERE id=:id')->execute(['id'=>$tradeId]);
                $pdo->commit();
                return $this->success('Troca recusada.');
            }
            $offerer = (int) $trade['offerer_user_id'];
            $offered = (int) $trade['offered_card_edition_id'];
            $requested = (int) $trade['requested_card_edition_id'];
            if ($this->cardQuantity($pdo, $offerer, $offered, true) < 2) throw new \RuntimeException('A carta oferecida não está mais disponível como repetida.');
            if ($this->cardQuantity($pdo, $ctx['userId'], $requested, true) < 1) throw new \RuntimeException('Você não possui mais a carta solicitada.');
            $this->transferCard($pdo, $offerer, $ctx['userId'], $offered);
            $this->transferCard($pdo, $ctx['userId'], $offerer, $requested);
            $pdo->prepare('UPDATE cq_trade_offers SET status="accepted", responded_at=NOW() WHERE id=:id')->execute(['id'=>$tradeId]);
            $pdo->commit();
            return $this->success('Troca concluída. As cartas já foram transferidas.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage() ?: 'Não foi possível concluir a troca.');
        }
    }

    public function equipCosmetic(int $giftCatalogId): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $ctx;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT uc.cosmetic_slot FROM cq_user_cosmetics uc WHERE uc.user_id=:u AND uc.gift_catalog_id=:g LIMIT 1');
        $stmt->execute(['u'=>$ctx['userId'],'g'=>$giftCatalogId]);
        $slot = (string) ($stmt->fetchColumn() ?: '');
        if ($slot === '') return $this->error('Cosmético não pertence a este perfil.');
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE cq_user_cosmetics SET equipped=0 WHERE user_id=:u AND cosmetic_slot=:s')->execute(['u'=>$ctx['userId'],'s'=>$slot]);
            $pdo->prepare('UPDATE cq_user_cosmetics SET equipped=1 WHERE user_id=:u AND gift_catalog_id=:g')->execute(['u'=>$ctx['userId'],'g'=>$giftCatalogId]);
            $pdo->commit();
            return $this->success('Visual aplicado ao seu CrismaQuest.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error('Não foi possível aplicar o visual.');
        }
    }

    public function getUnreadCountSafe(): int
    {
        try {
            $ctx = $this->studentContext();
            if (!($ctx['ok'] ?? false)) return 0;
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM cq_peer_notes WHERE recipient_user_id=:u1 AND read_at IS NULL) + (SELECT COUNT(*) FROM cq_gifts WHERE recipient_user_id=:u2 AND opened_at IS NULL) + (SELECT COUNT(*) FROM cq_trade_offers WHERE recipient_user_id=:u3 AND status="pending")');
            $stmt->execute(['u1'=>$ctx['userId'],'u2'=>$ctx['userId'],'u3'=>$ctx['userId']]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) { return 0; }
    }

    public function getEquippedCosmeticClassesSafe(): array
    {
        try {
            $ctx = $this->studentContext();
            if (!($ctx['ok'] ?? false)) return [];
            $stmt = Database::getConnection()->prepare('SELECT gc.slug FROM cq_user_cosmetics uc JOIN cq_gift_catalog gc ON gc.id=uc.gift_catalog_id WHERE uc.user_id=:u AND uc.equipped=1');
            $stmt->execute(['u'=>$ctx['userId']]);
            return array_map(static fn($slug) => 'cq-cosmetic-' . preg_replace('/[^a-z0-9-]/','',(string)$slug), $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable) { return []; }
    }

    public function getTeacherAuditData(): array
    {
        $perm = new PermissionService();
        if ($perm->checkPermissionsTeacher() !== PermissionService::STATUS_OK) return ['ok'=>false];
        $classId = (int) $perm->getCurrentClassId();
        $pdo = Database::getConnection();
        $notes = $pdo->prepare('SELECT n.*, CONCAT(s.nome," ",s.cognome) sender_name, CONCAT(r.nome," ",r.cognome) recipient_name FROM cq_peer_notes n JOIN ct_utenti s ON s.id_utente=n.sender_user_id JOIN ct_utenti r ON r.id_utente=n.recipient_user_id WHERE n.class_id=:c ORDER BY n.created_at DESC LIMIT 100');
        $notes->execute(['c'=>$classId]);
        $gifts = $pdo->prepare('SELECT g.*, CONCAT(s.nome," ",s.cognome) sender_name, CONCAT(r.nome," ",r.cognome) recipient_name, gc.name gift_name, sc.name card_name FROM cq_gifts g JOIN ct_utenti s ON s.id_utente=g.sender_user_id JOIN ct_utenti r ON r.id_utente=g.recipient_user_id LEFT JOIN cq_gift_catalog gc ON gc.id=g.gift_catalog_id LEFT JOIN cq_card_editions ce ON ce.id=g.card_edition_id LEFT JOIN cq_saint_cards sc ON sc.id=ce.card_id WHERE g.class_id=:c ORDER BY g.created_at DESC LIMIT 100');
        $gifts->execute(['c'=>$classId]);
        $trades = $pdo->prepare('SELECT t.*, CONCAT(o.nome," ",o.cognome) offerer_name, CONCAT(r.nome," ",r.cognome) recipient_name, so.name offered_name, sr.name requested_name FROM cq_trade_offers t JOIN ct_utenti o ON o.id_utente=t.offerer_user_id JOIN ct_utenti r ON r.id_utente=t.recipient_user_id JOIN cq_card_editions eo ON eo.id=t.offered_card_edition_id JOIN cq_saint_cards so ON so.id=eo.card_id JOIN cq_card_editions er ON er.id=t.requested_card_edition_id JOIN cq_saint_cards sr ON sr.id=er.card_id WHERE t.class_id=:c ORDER BY t.created_at DESC LIMIT 100');
        $trades->execute(['c'=>$classId]);
        return ['ok'=>true,'notes'=>$notes->fetchAll(PDO::FETCH_ASSOC) ?: [],'gifts'=>$gifts->fetchAll(PDO::FETCH_ASSOC) ?: [],'trades'=>$trades->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    private function studentContext(): array
    {
        $perm = new PermissionService();
        $status = $perm->checkPermissionsStudent();
        if ($status !== PermissionService::STATUS_OK) return ['ok'=>false,'permissionStatus'=>$status];
        $userId = (int) $perm->getCurrentUserId();
        $classId = (int) $perm->getCurrentClassId();
        $stmt = Database::getConnection()->prepare('SELECT s.id_studente, s.monete, u.nome, u.cognome FROM ct_studenti s JOIN ct_utenti u ON u.id_utente=s.fk_utente JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente WHERE u.id_utente=:u AND sc.fk_classe=:c LIMIT 1');
        $stmt->execute(['u'=>$userId,'c'=>$classId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['ok'=>false,'permissionStatus'=>PermissionService::STATUS_NOT_CLASS_OWNER];
        return ['ok'=>true,'permissionStatus'=>PermissionService::STATUS_OK,'userId'=>$userId,'classId'=>$classId,'studentId'=>(int)$row['id_studente'],'balance'=>(int)$row['monete'],'student'=>$row];
    }

    private function classmates(int $classId, int $userId): array
    {
        $stmt = Database::getConnection()->prepare('SELECT u.id_utente, u.nome, u.cognome FROM ct_studenti_classi sc JOIN ct_studenti s ON s.id_studente=sc.fk_studente JOIN ct_utenti u ON u.id_utente=s.fk_utente WHERE sc.fk_classe=:c AND u.id_utente<>:u ORDER BY u.nome,u.cognome');
        $stmt->execute(['c'=>$classId,'u'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private function isClassmate(int $classId, int $sender, int $recipient): bool
    {
        if ($recipient <= 0 || $recipient === $sender) return false;
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM ct_studenti_classi sc JOIN ct_studenti s ON s.id_studente=sc.fk_studente WHERE sc.fk_classe=:c AND s.fk_utente=:u');
        $stmt->execute(['c'=>$classId,'u'=>$recipient]); return (int)$stmt->fetchColumn() > 0;
    }
    private function giftCatalog(): array { return Database::getConnection()->query('SELECT * FROM cq_gift_catalog WHERE active=1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    private function notes(int $classId, int $userId, bool $received): array
    {
        $field=$received?'recipient_user_id':'sender_user_id'; $other=$received?'sender_user_id':'recipient_user_id';
        $stmt=Database::getConnection()->prepare("SELECT n.*, CONCAT(u.nome,' ',u.cognome) other_name FROM cq_peer_notes n JOIN ct_utenti u ON u.id_utente=n.$other WHERE n.class_id=:c AND n.$field=:u ORDER BY n.created_at DESC LIMIT 30");
        $stmt->execute(['c'=>$classId,'u'=>$userId]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach($rows as &$r){$r['message_text']=self::NOTE_KEYS[$r['message_key']] ?? 'Mensagem da Jornada';} return $rows;
    }
    private function gifts(int $classId, int $userId, bool $received): array
    {
        $field=$received?'recipient_user_id':'sender_user_id'; $other=$received?'sender_user_id':'recipient_user_id';
        $stmt=Database::getConnection()->prepare("SELECT g.*, CONCAT(u.nome,' ',u.cognome) other_name, gc.name gift_name, gc.icon gift_icon, sc.name card_name FROM cq_gifts g JOIN ct_utenti u ON u.id_utente=g.$other LEFT JOIN cq_gift_catalog gc ON gc.id=g.gift_catalog_id LEFT JOIN cq_card_editions ce ON ce.id=g.card_edition_id LEFT JOIN cq_saint_cards sc ON sc.id=ce.card_id WHERE g.class_id=:c AND g.$field=:u ORDER BY g.created_at DESC LIMIT 30");
        $stmt->execute(['c'=>$classId,'u'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private function userCards(int $userId): array
    {
        $stmt=Database::getConnection()->prepare('SELECT uc.card_edition_id, uc.quantity, sc.name, ce.edition_type FROM cq_user_cards uc JOIN cq_card_editions ce ON ce.id=uc.card_edition_id JOIN cq_saint_cards sc ON sc.id=ce.card_id WHERE uc.user_id=:u AND uc.quantity>0 ORDER BY sc.name');
        $stmt->execute(['u'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private function pendingTrades(int $classId,int $userId): array
    {
        $stmt=Database::getConnection()->prepare('SELECT t.*, CONCAT(u.nome," ",u.cognome) offerer_name, so.name offered_name, sr.name requested_name FROM cq_trade_offers t JOIN ct_utenti u ON u.id_utente=t.offerer_user_id JOIN cq_card_editions eo ON eo.id=t.offered_card_edition_id JOIN cq_saint_cards so ON so.id=eo.card_id JOIN cq_card_editions er ON er.id=t.requested_card_edition_id JOIN cq_saint_cards sr ON sr.id=er.card_id WHERE t.class_id=:c AND t.recipient_user_id=:u AND t.status="pending" ORDER BY t.created_at DESC');
        $stmt->execute(['c'=>$classId,'u'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private function sentTrades(int $classId,int $userId): array
    {
        $stmt=Database::getConnection()->prepare('SELECT t.*, CONCAT(u.nome," ",u.cognome) recipient_name, so.name offered_name, sr.name requested_name FROM cq_trade_offers t JOIN ct_utenti u ON u.id_utente=t.recipient_user_id JOIN cq_card_editions eo ON eo.id=t.offered_card_edition_id JOIN cq_saint_cards so ON so.id=eo.card_id JOIN cq_card_editions er ON er.id=t.requested_card_edition_id JOIN cq_saint_cards sr ON sr.id=er.card_id WHERE t.class_id=:c AND t.offerer_user_id=:u ORDER BY t.created_at DESC LIMIT 30');
        $stmt->execute(['c'=>$classId,'u'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private function ownedCosmetics(int $userId): array
    {
        $stmt=Database::getConnection()->prepare('SELECT uc.*,gc.name,gc.slug,gc.icon FROM cq_user_cosmetics uc JOIN cq_gift_catalog gc ON gc.id=uc.gift_catalog_id WHERE uc.user_id=:u ORDER BY uc.cosmetic_slot,gc.name');
        $stmt->execute(['u'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private function ledger(int $userId): array
    {
        $stmt=Database::getConnection()->prepare('SELECT * FROM cq_lumen_ledger WHERE user_id=:u ORDER BY created_at DESC,id DESC LIMIT 30'); $stmt->execute(['u'=>$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private function cardQuantity(PDO $pdo,int $userId,int $editionId,bool $lock=false): int
    {
        $sql='SELECT quantity FROM cq_user_cards WHERE user_id=:u AND card_edition_id=:e'.($lock?' FOR UPDATE':''); $stmt=$pdo->prepare($sql);$stmt->execute(['u'=>$userId,'e'=>$editionId]);return (int)($stmt->fetchColumn() ?: 0);
    }
    private function transferCard(PDO $pdo,int $from,int $to,int $editionId): void
    {
        $qty=$this->cardQuantity($pdo,$from,$editionId,true); if($qty<1) throw new \RuntimeException('Carta indisponível.');
        if($qty===1){$pdo->prepare('DELETE FROM cq_user_cards WHERE user_id=:u AND card_edition_id=:e')->execute(['u'=>$from,'e'=>$editionId]);}
        else{$pdo->prepare('UPDATE cq_user_cards SET quantity=quantity-1 WHERE user_id=:u AND card_edition_id=:e')->execute(['u'=>$from,'e'=>$editionId]);}
        $pdo->prepare('INSERT INTO cq_user_cards (user_id,card_edition_id,quantity) VALUES (:u,:e,1) ON DUPLICATE KEY UPDATE quantity=quantity+1')->execute(['u'=>$to,'e'=>$editionId]);
    }
    private function success(string $message): array { return ['ok'=>true,'success'=>true,'message'=>$message]; }
    private function error(string $message): array { return ['ok'=>false,'success'=>false,'message'=>$message]; }
}

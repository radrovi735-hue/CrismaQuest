<?php

namespace App\Service;

use PDO;
use Throwable;

final class CrismaQuestNotificationService
{
    public function enqueue(
        PDO $pdo,
        int $userId,
        string $eventKey,
        string $type,
        string $title,
        string $message = '',
        array $payload = []
    ): bool {
        if ($userId <= 0) return false;

        try {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO cq_user_notifications
                    (user_id,event_key,notification_type,title,message,payload_json)
                 VALUES (:u,:k,:t,:title,:message,:payload)'
            );
            $stmt->execute([
                'u'=>$userId,
                'k'=>mb_substr($eventKey,0,190),
                't'=>mb_substr($type,0,40),
                'title'=>mb_substr($title,0,180),
                'message'=>mb_substr($message,0,500) ?: null,
                'payload'=>$payload === []
                    ? null
                    : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function notifyCard(
        PDO $pdo,
        int $userId,
        array $card,
        string $eventKey,
        bool $isNew,
        string $source = ''
    ): void {
        try {
            $card = CrismaQuestSaintCatalog::enrich($card);
        } catch (Throwable) {
        }

        $edition = (string)($card['edition_type'] ?? 'normal');
        $illuminated = $edition === 'illuminated';

        $title = $illuminated
            ? 'Carta iluminada!'
            : ($isNew ? 'Nova carta!' : 'Carta repetida!');

        $message = $source !== ''
            ? $source
            : ($illuminated
                ? 'Uma testemunha do seu álbum recebeu uma edição iluminada.'
                : ($isNew
                    ? 'Uma nova testemunha entrou no seu Álbum dos Santos.'
                    : 'Esta carta já estava no seu álbum e agora pode ajudar em presentes e trocas.'));

        $this->enqueue($pdo,$userId,$eventKey,'card',$title,$message,[
            'name'=>(string)($card['name'] ?? 'Carta do Álbum'),
            'slug'=>(string)($card['slug'] ?? ''),
            'card_number'=>(int)($card['card_number'] ?? 0),
            'edition_type'=>$edition,
            'is_new'=>$isNew,
            'image'=>(string)($card['image_path'] ?? ''),
            'fallback'=>(string)($card['fallback_image_path'] ?? ''),
            'url'=>'/studenti/classe/dashboard?view=album',
        ]);

        $this->notifyCollectionMilestones($pdo,$userId);
    }

    public function notifyCardEdition(
        PDO $pdo,
        int $userId,
        int $editionId,
        string $eventKey,
        string $source = ''
    ): void {
        try {
            $stmt = $pdo->prepare(
                'SELECT ce.edition_type,sc.card_number,sc.slug,sc.name,sc.image_path,
                        COALESCE(uc.quantity,0) quantity
                 FROM cq_card_editions ce
                 JOIN cq_saint_cards sc ON sc.id=ce.card_id
                 LEFT JOIN cq_user_cards uc
                    ON uc.user_id=:u AND uc.card_edition_id=ce.id
                 WHERE ce.id=:e LIMIT 1'
            );
            $stmt->execute(['u'=>$userId,'e'=>$editionId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$card) return;

            $this->notifyCard(
                $pdo,
                $userId,
                $card,
                $eventKey,
                (int)$card['quantity'] === 1,
                $source
            );
        } catch (Throwable) {
        }
    }

    public function notifyBadge(PDO $pdo, int $userId, string $slug): void
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT name,description,icon
                 FROM cq_badge_catalog
                 WHERE slug=:slug AND active=1 LIMIT 1'
            );
            $stmt->execute(['slug'=>$slug]);
            $badge = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$badge) return;

            $this->enqueue(
                $pdo,
                $userId,
                'badge:' . $slug,
                'badge',
                'Nova conquista!',
                (string)$badge['description'],
                [
                    'name'=>(string)$badge['name'],
                    'slug'=>$slug,
                    'icon'=>(string)$badge['icon'],
                    'url'=>'/studenti/missoes',
                ]
            );
        } catch (Throwable) {
        }
    }

    public function notifyCollectionMilestones(PDO $pdo, int $userId): void
    {
        try {
            $collected = $this->collectedSaints($pdo,$userId);

            if ($collected >= 30) {
                $this->enqueue(
                    $pdo,$userId,'album-milestone:30','milestone',
                    'Troca de repetidas liberada!',
                    'Com 30 santos no álbum, você pode trocar 5 cópias repetidas por 1 carta nova, uma vez por semana.',
                    ['icon'=>'fa-arrows-rotate','url'=>'/studenti/classe/dashboard?view=album']
                );
            }

            if ($collected >= 35) {
                $this->enqueue(
                    $pdo,$userId,'album-milestone:35','milestone',
                    'Proteção do Álbum ativada!',
                    'Faltam poucas testemunhas. A partir de agora, os dois espaços de cada Pacote da Jornada priorizam cartas que ainda faltam.',
                    ['icon'=>'fa-shield-heart','url'=>'/studenti/classe/dashboard?view=album']
                );
            }

            if ($collected >= 40) {
                $this->enqueue(
                    $pdo,$userId,'album-complete:40','album_complete',
                    'Álbum completo: 40/40!',
                    'Você reuniu todas as testemunhas do Álbum dos Santos. As próximas recompensas protegidas passam a buscar edições iluminadas.',
                    ['icon'=>'fa-trophy','url'=>'/studenti/classe/dashboard?view=album']
                );
            }
        } catch (Throwable) {
        }
    }

    public function pending(int $userId, int $limit = 12): array
    {
        if ($userId <= 0) return [];
        $limit = max(1,min(20,$limit));

        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT id,notification_type,title,message,payload_json,created_at
                 FROM cq_user_notifications
                 WHERE user_id=:u AND seen_at IS NULL
                 ORDER BY created_at ASC,id ASC
                 LIMIT ' . $limit
            );
            $stmt->execute(['u'=>$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $payload = json_decode((string)($row['payload_json'] ?? ''),true);
                $row['payload'] = is_array($payload) ? $payload : [];
                unset($row['payload_json']);
            }
            unset($row);
            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    public function markSeen(int $userId, int $notificationId): bool
    {
        if ($userId <= 0 || $notificationId <= 0) return false;
        try {
            $stmt = Database::getConnection()->prepare(
                'UPDATE cq_user_notifications
                 SET seen_at=COALESCE(seen_at,NOW())
                 WHERE id=:id AND user_id=:u'
            );
            $stmt->execute(['id'=>$notificationId,'u'=>$userId]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function collectedSaints(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT ce.card_id)
             FROM cq_user_cards uc
             JOIN cq_card_editions ce ON ce.id=uc.card_edition_id
             JOIN cq_saint_cards sc ON sc.id=ce.card_id
             WHERE uc.user_id=:u AND uc.quantity>0 AND sc.active=1'
        );
        $stmt->execute(['u'=>$userId]);
        return (int)$stmt->fetchColumn();
    }
}

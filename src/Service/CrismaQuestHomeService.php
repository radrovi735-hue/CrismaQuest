<?php

namespace App\Service;

use PDO;
use Throwable;

final class CrismaQuestHomeService
{
    public function getData(int $classId, int $studentId, int $userId): array
    {
        return [
            'mission' => CrismaQuestGameAccess::enabled()
                ? (new CrismaQuestGameService())->getNextMissionForHome($userId)
                : $this->nextMission($classId, $studentId),
            'meeting' => $this->nextMeeting($classId),
            'featuredCard' => $this->featuredCard($userId),
        ];
    }

    private function nextMission(int $classId, int $studentId): ?array
    {
        if ($classId <= 0 || $studentId <= 0) return null;

        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT q.id_quest, q.nome_quest, c.id_capitolo, c.nome_capitolo AS chapter_title,
                        e.id_esercizio, e.nome_capitolo AS title,
                        cq.progressivo AS chapter_order, eq.progressivo AS exercise_order
                 FROM ct_classi_quest clq
                 INNER JOIN ct_quest q ON q.id_quest = clq.fk_quest
                 INNER JOIN ct_capitoli_quest cq ON cq.fk_quest = q.id_quest
                 INNER JOIN ct_capitoli c ON c.id_capitolo = cq.fk_capitolo
                 INNER JOIN ct_esercizi_quest eq ON eq.fk_capitolo = c.id_capitolo
                 INNER JOIN ct_esercizi e ON e.id_esercizio = eq.fk_esercizio
                 INNER JOIN ct_classi_esercizi_attivi cea
                         ON cea.fk_esercizio = e.id_esercizio
                        AND cea.fk_classe = clq.fk_classe
                        AND cea.attivo = 1
                 LEFT JOIN ct_consegne_studenti cs
                        ON cs.fk_esercizio = e.id_esercizio
                       AND cs.fk_studente = :student
                 WHERE clq.fk_classe = :class
                   AND cs.id_consegna IS NULL
                 ORDER BY q.id_quest, cq.progressivo, eq.progressivo
                 LIMIT 1'
            );
            $stmt->execute(['student' => $studentId, 'class' => $classId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;

            $row['url'] = '/studenti/quest/' . (int)$row['id_quest']
                . '/capitoli/' . (int)$row['id_capitolo']
                . '/esercizi/' . (int)$row['id_esercizio'];
            return $row;
        } catch (Throwable) {
            return null;
        }
    }

    private function nextMeeting(int $classId): ?array
    {
        if ($classId <= 0) return null;

        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT id, title, meeting_at, theme, chapter_key, presence_xp
                 FROM cq_meetings
                 WHERE class_id = :class
                   AND meeting_at >= NOW()
                   AND status = 'scheduled'
                 ORDER BY meeting_at ASC
                 LIMIT 1"
            );
            $stmt->execute(['class' => $classId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function featuredCard(int $userId): ?array
    {
        if ($userId <= 0) return null;

        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT sc.name, sc.short_bio, sc.short_teaching, sc.image_path,
                        ce.edition_type, uc.quantity
                 FROM cq_user_cards uc
                 JOIN cq_card_editions ce ON ce.id = uc.card_edition_id
                 JOIN cq_saint_cards sc ON sc.id = ce.card_id
                 WHERE uc.user_id = :user
                   AND uc.quantity > 0
                 ORDER BY uc.first_obtained_at DESC, sc.card_number ASC
                 LIMIT 1'
            );
            $stmt->execute(['user' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}

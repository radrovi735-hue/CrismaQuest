<?php

namespace App\Service;

use PDO;
use Throwable;

final class CrismaQuestJourneyService
{
    public const TOTAL_STEPS = 22;

    public function getSeasonData(?int $studentId = null): array
    {
        $completed = $studentId ? $this->countCompletedMissions($studentId) : 0;
        $completed = max(0, min(self::TOTAL_STEPS, $completed));

        $chapters = [
            [
                'number'=>1,'title'=>'O Chamado','subtitle'=>'Deus fala e nós respondemos',
                'steps'=>[
                    'O desejo de Deus','Deus se revela','A Palavra que ilumina','Creio: a resposta da fé'
                ],
            ],
            [
                'number'=>2,'title'=>'Quem é Jesus?','subtitle'=>'O centro da nossa fé',
                'steps'=>[
                    'O Pai e a criação','O Verbo se fez carne','Jesus anuncia o Reino','Paixão e Ressurreição','O Espírito Santo e a Trindade'
                ],
            ],
            [
                'number'=>3,'title'=>'A Igreja','subtitle'=>'Um povo reunido e enviado',
                'steps'=>[
                    'Povo de Deus','Comunhão dos Santos','Maria na caminhada cristã'
                ],
            ],
            [
                'number'=>4,'title'=>'Os Sacramentos','subtitle'=>'Sinais da graça no caminho',
                'steps'=>[
                    'Iniciação cristã','Eucaristia: fonte e ápice','Reconciliação e cura','Vocação: Ordem e Matrimônio'
                ],
            ],
            [
                'number'=>5,'title'=>'Vida em Cristo','subtitle'=>'Liberdade, verdade e caridade',
                'steps'=>[
                    'Dignidade e liberdade','Amar a Deus','Amar o próximo','Verdade, justiça e pureza de coração'
                ],
            ],
            [
                'number'=>6,'title'=>'Oração e Missão','subtitle'=>'Com o Espírito, enviados',
                'steps'=>[
                    'Aprender a rezar','Pai-Nosso e vida litúrgica'
                ],
            ],
        ];

        $cursor = 0;
        foreach ($chapters as &$chapter) {
            $chapterStart = $cursor;
            $chapterEnd = $cursor + count($chapter['steps']);
            if ($completed >= $chapterEnd) {
                $chapter['state'] = 'done';
            } elseif ($completed >= $chapterStart && $completed < $chapterEnd) {
                $chapter['state'] = 'current';
            } else {
                $chapter['state'] = 'locked';
            }
            $chapter['completedSteps'] = max(0, min(count($chapter['steps']), $completed - $chapterStart));
            $chapter['totalSteps'] = count($chapter['steps']);
            $cursor = $chapterEnd;
        }
        unset($chapter);

        $currentChapter = null;
        foreach ($chapters as $chapter) {
            if ($chapter['state'] === 'current') {
                $currentChapter = $chapter;
                break;
            }
        }
        if ($currentChapter === null) {
            $currentChapter = $chapters[array_key_last($chapters)];
        }

        return [
            'chapters'=>$chapters,
            'completedSteps'=>$completed,
            'totalSteps'=>self::TOTAL_STEPS,
            'progressPercent'=>(int)floor(($completed/self::TOTAL_STEPS)*100),
            'currentChapter'=>$currentChapter,
        ];
    }

    private function countCompletedMissions(int $studentId): int
    {
        if ($studentId <= 0) return 0;
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT COUNT(*) FROM (
                   SELECT m.step_no
                   FROM cq_missions m
                   LEFT JOIN cq_mission_completions mc
                     ON mc.mission_id=m.id
                    AND mc.user_id=(SELECT fk_utente FROM ct_studenti WHERE id_studente=:student_id LIMIT 1)
                   WHERE m.step_no IS NOT NULL AND m.active=1
                   GROUP BY m.step_no
                   HAVING COUNT(m.id)=SUM(CASE WHEN mc.id IS NULL THEN 0 ELSE 1 END)
                 ) completed_steps'
            );
            $stmt->execute(['student_id'=>$studentId]);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }
}

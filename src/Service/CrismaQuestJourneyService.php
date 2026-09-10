<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class CrismaQuestJourneyService
{
    public const TOTAL_STEPS = 22;

    public function getSeasonData(?int $studentId = null): array
    {
        $titles = ['O Chamado','Quem é Jesus?','A Igreja','Os Sacramentos','Vida em Cristo','Oração e Missão'];
        $subtitles = ['Deus fala e nós respondemos','O centro da nossa fé','Um povo reunido e enviado','Sinais da graça no caminho','Liberdade, verdade e caridade','Com o Espírito, enviados'];
        $chapters = [];
        foreach ($titles as $i => $title) {
            $chapters[$i + 1] = ['number'=>$i+1,'title'=>$title,'subtitle'=>$subtitles[$i],'steps'=>[],'items'=>[],'completedSteps'=>0,'totalSteps'=>0,'state'=>'locked'];
        }
        $today = (new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza')))->format('Y-m-d');
        $steps = $this->steps($studentId);
        $completed = 0;
        foreach ($steps as &$step) {
            $done = (int)($step['mission_count'] ?? 0) > 0 && (int)$step['completed_count'] >= (int)$step['mission_count'];
            $step['state'] = $done ? 'done' : (($step['opens_at'] <= $today && $step['closes_at'] >= $today && (int)$step['active'] === 1) ? 'current' : 'locked');
            $step['url'] = '/studenti/missoes?etapa=' . (int)$step['step_no'];
            $chapter = (int)$step['chapter_no'];
            if (!isset($chapters[$chapter])) continue;
            $chapters[$chapter]['steps'][] = $step['title'];
            $chapters[$chapter]['items'][] = $step;
            $chapters[$chapter]['totalSteps']++;
            if ($done) { $completed++; $chapters[$chapter]['completedSteps']++; }
        }
        unset($step);
        $current = null;
        foreach ($chapters as &$chapter) {
            $chapter['state'] = $chapter['totalSteps'] > 0 && $chapter['completedSteps'] === $chapter['totalSteps'] ? 'done' : (in_array('current', array_column($chapter['items'], 'state'), true) ? 'current' : 'locked');
            if ($current === null && $chapter['state'] === 'current') $current = $chapter;
        }
        unset($chapter);
        return ['chapters'=>array_values($chapters),'steps'=>$steps,'completedSteps'=>$completed,'totalSteps'=>self::TOTAL_STEPS,'progressPercent'=>(int)floor($completed/self::TOTAL_STEPS*100),'currentChapter'=>$current ?? $chapters[1]];
    }

    private function steps(?int $studentId): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT js.*, COUNT(m.id) mission_count, COUNT(mc.id) completed_count
                 FROM cq_journey_steps js
                 LEFT JOIN cq_missions m ON m.step_no=js.step_no AND m.active=1
                 LEFT JOIN cq_mission_completions mc ON mc.mission_id=m.id
                   AND mc.user_id=(SELECT fk_utente FROM ct_studenti WHERE id_studente=? LIMIT 1)
                 GROUP BY js.id,js.step_no,js.chapter_no,js.title,js.subtitle,js.opens_at,js.closes_at,js.active
                 ORDER BY js.step_no'
            );
            $stmt->execute([$studentId ?? 0]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) === self::TOTAL_STEPS) return $rows;
        } catch (Throwable) {
            // Before setup, show the same canonical catalogue without DB writes.
        }
        $sql = file_get_contents(dirname(__DIR__,2) . '/sql/crismaquest/005_gameplay_seed.sql') ?: '';
        preg_match_all("/INSERT INTO cq_journey_steps .*? VALUES \\((\\d+),(\\d+),'([^']*)','([^']*)','([^']*)','([^']*)',1\\)/", $sql, $matches, PREG_SET_ORDER);
        return array_map(static fn(array $m): array => ['step_no'=>(int)$m[1],'chapter_no'=>(int)$m[2],'title'=>$m[3],'subtitle'=>$m[4],'opens_at'=>$m[5],'closes_at'=>$m[6],'active'=>1,'mission_count'=>0,'completed_count'=>0], $matches);
    }
}

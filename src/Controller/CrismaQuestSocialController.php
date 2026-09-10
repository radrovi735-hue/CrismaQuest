<?php

namespace App\Controller;

use App\Core\View;
use App\Service\CrismaQuestLumenLedgerService;
use App\Service\CrismaQuestSocialService;
use App\Service\Flash;
use App\Service\PermissionService;

final class CrismaQuestSocialController
{
    public function index(): void
    {
        (new CrismaQuestLumenLedgerService())->reconcileCurrentStudent();
        $data = (new CrismaQuestSocialService())->getStudentPageData();
        if (($data['permissionStatus'] ?? null) !== PermissionService::STATUS_OK) {
            header('Location: /loginStud'); exit;
        }
        View::render('studenti/crismaquestSocial', $data + [
            'title' => 'Correio da Jornada',
            'pageStyles' => ['/css/crismaquest-social.css'],
            'pageScripts' => [],
        ], 'mainStudLayout');
    }

    public function sendNote(): void { $this->run(fn($s) => $s->sendNote($_POST)); }
    public function sendGift(): void { $this->run(fn($s) => $s->sendGift($_POST)); }
    public function sendCardGift(): void { $this->run(fn($s) => $s->sendCardGift($_POST)); }
    public function proposeTrade(): void { $this->run(fn($s) => $s->proposeTrade($_POST)); }
    public function acceptTrade(string $id): void { $this->run(fn($s) => $s->respondTrade((int)$id, true)); }
    public function declineTrade(string $id): void { $this->run(fn($s) => $s->respondTrade((int)$id, false)); }
    public function equipCosmetic(string $id): void { $this->run(fn($s) => $s->equipCosmetic((int)$id)); }

    public function teacherAudit(): void
    {
        $data = (new CrismaQuestSocialService())->getTeacherAuditData();
        if (!($data['ok'] ?? false)) { header('Location: /docenti/dashboard'); exit; }
        View::render('docenti/crismaquestSocialAudit', $data + [
            'title'=>'Correio da Jornada — supervisão',
            'pageStyles'=>['/css/crismaquest-teacher.css','/css/crismaquest-social.css'],
            'pageScripts'=>[],
            'useMathJax'=>false,
        ], 'mainDocLayout');
    }

    private function run(callable $action): void
    {
        $result = $action(new CrismaQuestSocialService());
        Flash::add(($result['success'] ?? false) ? 'success' : 'danger', (string)($result['message'] ?? 'Operação não concluída.'));
        header('Location: /studenti/correio'); exit;
    }
}

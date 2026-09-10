<?php

namespace App\Controller;

use App\Core\View;
use App\Service\CrismaQuestGameService;
use App\Service\CrismaQuestGameAccess;
use App\Service\Flash;
use App\Service\PermissionService;

final class CrismaQuestGameController
{
    public function __construct()
    {
        $teacher = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/docenti/');
        $permission = new PermissionService();
        $status = $teacher ? $permission->checkPermissionsTeacher() : $permission->checkPermissionsStudent();
        if ($status !== PermissionService::STATUS_OK) {
            header('Location: ' . ($teacher ? '/loginDoc' : '/loginStud'));
            exit;
        }
        if (!CrismaQuestGameAccess::enabled()) {
            Flash::add('info', 'As missões estão sendo preparadas pela catequese.');
            header('Location: ' . ($teacher ? '/docenti/jogo/setup' : '/studenti/classe/dashboard'));
            exit;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            && !CrismaQuestGameAccess::validToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Sua sessão precisa ser atualizada. Recarregue a página e tente novamente.';
            exit;
        }
    }

    public function studentIndex(): void
    {
        $step = filter_var($_GET['etapa'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>22]]) ?: null;
        $data = (new CrismaQuestGameService())->getStudentPageData($step);
        if (($data['permissionStatus'] ?? null) !== PermissionService::STATUS_OK) {
            header('Location: /loginStud');
            exit;
        }

        View::render('studenti/crismaquestGame', $data + [
            'title'=>'Missões',
            'pageStyles'=>['/css/crismaquest-game.css?v=20260910d'],
            'pageScripts'=>[],
        ], 'mainStudLayout');
    }

    public function completeMission(string $id): void
    {
        $result = (new CrismaQuestGameService())->completeMission((int)$id, $_POST);
        $this->studentRedirect($result, '/studenti/missoes#missao-' . (int)$id);
    }

    public function completeSpark(): void
    {
        $result = (new CrismaQuestGameService())->completeSpark((int)($_POST['spark_id'] ?? 0));
        $this->studentRedirect($result, '/studenti/missoes');
    }

    public function claimChest(string $id): void
    {
        $result = (new CrismaQuestGameService())->claimChest((int)$id);
        $this->studentRedirect($result, '/studenti/missoes#baus');
    }

    public function sendIntercession(): void
    {
        $result = (new CrismaQuestGameService())->sendIntercession((int)($_POST['recipient_user_id'] ?? 0));
        $this->studentRedirect($result, '/studenti/missoes#chama');
    }

    public function useIntercession(string $id): void
    {
        $result = (new CrismaQuestGameService())->useIntercession((int)$id);
        $this->studentRedirect($result, '/studenti/missoes#chama');
    }

    public function useRosary(): void
    {
        $result = (new CrismaQuestGameService())->useRosary();
        $this->studentRedirect($result, '/studenti/missoes#chama');
    }

    public function teacherIndex(): void
    {
        $data = (new CrismaQuestGameService())->getTeacherPageData();
        if (($data['permissionStatus'] ?? null) !== PermissionService::STATUS_OK) {
            header('Location: /docenti/dashboard');
            exit;
        }

        View::render('docenti/crismaquestGame', $data + [
            'title'=>'Jogo CrismaQuest',
            'pageStyles'=>['/css/crismaquest-game.css?v=20260910d','/css/crismaquest-teacher.css'],
            'pageScripts'=>['/js/crismaquest-game-admin.js?v=20260910d'],
            'useMathJax'=>false,
        ], 'mainDocLayout');
    }

    public function teacherPreview(): void
    {
        View::render('studenti/crismaquestGame', (new CrismaQuestGameService())->getTeacherPreviewData() + [
            'title'=>'Prévia das missões',
            'pageStyles'=>['/css/crismaquest-app.css','/css/crismaquest-game.css?v=20260910d'],
            'useMathJax'=>false,
        ], 'mainDocLayout');
    }

    public function teacherJourney(): void
    {
        View::render('studenti/journey', [
            'preview'=>true,
            'crismaquestJourney'=>(new \App\Service\CrismaQuestJourneyService())->getSeasonData(),
            'pageStyles'=>['/css/crismaquest-app.css'],
            'useMathJax'=>false,
        ], 'mainDocLayout');
    }

    public function toggleMission(string $id): void
    {
        $result = (new CrismaQuestGameService())->toggleMission((int)$id);
        $this->teacherRedirect($result);
    }

    public function duplicateMission(string $id): void
    {
        $result = (new CrismaQuestGameService())->duplicateMission((int)$id);
        $this->teacherRedirect($result);
    }

    public function createMission(): void
    {
        $result = (new CrismaQuestGameService())->createMission($_POST);
        $this->teacherRedirect($result);
    }

    public function updateMission(string $id): void
    {
        $result = (new CrismaQuestGameService())->updateMission((int)$id, $_POST);
        $this->teacherRedirect($result);
    }

    public function pauseClass(): void
    {
        $result = (new CrismaQuestGameService())->pauseClass(
            (string)($_POST['start_date'] ?? ''),
            (string)($_POST['end_date'] ?? ''),
            (string)($_POST['reason'] ?? '')
        );
        $this->teacherRedirect($result);
    }

    public function pauseStudent(): void
    {
        $result = (new CrismaQuestGameService())->pauseStudent(
            (int)($_POST['user_id'] ?? 0),
            (string)($_POST['start_date'] ?? ''),
            (string)($_POST['end_date'] ?? ''),
            (string)($_POST['reason'] ?? '')
        );
        $this->teacherRedirect($result);
    }

    private function studentRedirect(array $result, string $url): void
    {
        Flash::add(($result['success'] ?? false) ? 'success' : 'danger', (string)($result['message'] ?? 'Operação não concluída.'));
        header('Location: ' . $url);
        exit;
    }

    private function teacherRedirect(array $result): void
    {
        Flash::add(($result['success'] ?? false) ? 'success' : 'danger', (string)($result['message'] ?? 'Operação não concluída.'));
        header('Location: /docenti/jogo');
        exit;
    }
}

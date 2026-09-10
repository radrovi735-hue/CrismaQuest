<?php

namespace App\Controller;

use App\Core\View;
use App\Service\CrismaQuestStreakService;
use App\Service\Flash;
use App\Service\PermissionService;
use App\Service\Session;
use App\Service\StudentQuestService;

class StudentQuestController
{
    public function index(): void
    {
        $pageData = (new StudentQuestService())->getQuestIndexPageData();
        $this->guard($pageData['permissionStatus'] ?? PermissionService::STATUS_NOT_LOGGED);

        View::render('studenti/quest/index', array_merge($pageData, [
            'title' => 'Missões',
            'pageStyles' => ['/css/crismaquest-app.css'],
            'pageScripts' => ['/js/studenti/quest.js'],
            'useMathJax' => false,
        ]), 'mainStudLayout');
    }

    public function map(string $questId): void
    {
        $pageData = (new StudentQuestService())->getQuestMapPageData((int) $questId);
        $this->guard($pageData['permissionStatus'] ?? PermissionService::STATUS_NOT_LOGGED);
        if (($pageData['quest'] ?? null) === null) {
            Flash::add('danger', 'Quest não encontrada ou não disponível para a turma selecionada.');
            header('Location: /studenti/quest'); exit;
        }
        View::render('studenti/quest/map', array_merge($pageData, [
            'title' => 'Missões',
            'pageStyles' => ['/css/crismaquest-app.css','/css/quest-legacy.css'],
            'pageScripts' => ['/js/studenti/quest-map.js'],
            'useMathJax' => false,
        ]), 'mainStudLayout');
    }

    public function problemDeliveries(string $questId): void
    {
        $pageData = (new StudentQuestService())->getProblemDeliveriesPageData((int) $questId);
        $this->guard($pageData['permissionStatus'] ?? PermissionService::STATUS_NOT_LOGGED);
        if (($pageData['quest'] ?? null) === null) {
            Flash::add('danger', 'Quest não encontrada ou não disponível para a turma selecionada.');
            header('Location: /studenti/quest'); exit;
        }
        View::render('studenti/quest/problem-deliveries', array_merge($pageData, [
            'title' => 'Pendências',
            'pageStyles' => ['/css/crismaquest-app.css','/css/quest-legacy.css'],
            'pageScripts' => ['/js/studenti/quest-problem-deliveries.js'],
            'useMathJax' => false,
        ]), 'mainStudLayout');
    }

    public function chapterDetail(string $questId, string $chapterId): void
    {
        $pageData = (new StudentQuestService())->getChapterDetailPageData((int) $questId, (int) $chapterId);
        $this->guard($pageData['permissionStatus'] ?? PermissionService::STATUS_NOT_LOGGED);
        if (($pageData['quest'] ?? null) === null || ($pageData['chapter'] ?? null) === null) {
            Flash::add('danger', 'Capítulo não encontrado.');
            header('Location: /studenti/quest/' . (int) $questId . '/piantina'); exit;
        }
        View::render('studenti/quest/chapter-detail', array_merge($pageData, [
            'title' => 'Capítulo',
            'pageStyles' => ['/css/crismaquest-app.css','/css/quest-legacy.css'],
            'pageScripts' => ['/js/studenti/quest-chapter-detail.js'],
            'useMathJax' => false,
        ]), 'mainStudLayout');
    }

    public function exerciseDetail(string $questId, string $chapterId, string $exerciseId): void
    {
        $pageData = (new StudentQuestService())->getExerciseDetailPageData((int) $questId, (int) $chapterId, (int) $exerciseId);
        $this->guard($pageData['permissionStatus'] ?? PermissionService::STATUS_NOT_LOGGED);
        if (($pageData['quest'] ?? null) === null || ($pageData['chapter'] ?? null) === null || ($pageData['exercise'] ?? null) === null) {
            Flash::add('danger', 'Missão não encontrada.');
            header('Location: /studenti/quest/' . (int) $questId . '/capitoli/' . (int) $chapterId); exit;
        }
        if (!($pageData['accessAllowed'] ?? false)) {
            Flash::add('danger', 'Esta etapa ainda não está liberada. Continue a Jornada para desbloqueá-la.');
            header('Location: /studenti/quest/' . (int) $questId . '/capitoli/' . (int) $chapterId); exit;
        }
        View::render('studenti/quest/exercise-detail', array_merge($pageData, [
            'title' => 'Missão',
            'pageStyles' => ['/css/crismaquest-app.css','/css/quest-legacy.css','/css/esercizi.css','https://unpkg.com/dropzone@5.9.3/dist/min/dropzone.min.css'],
            'pageScripts' => ['/assets/tinymce/tinymce.min.js','https://unpkg.com/dropzone@5/dist/min/dropzone.min.js','/js/studenti/quest-exercise-detail.js'],
            'useMathJax' => true,
        ]), 'mainStudLayout');
    }

    public function submitExercise(string $questId, string $chapterId, string $exerciseId): void
    {
        $result = (new StudentQuestService())->submitExercise((int)$questId, (int)$chapterId, (int)$exerciseId, $_POST, $_FILES);

        if (($result['success'] ?? false) !== true) {
            Flash::add('danger', (string) ($result['message'] ?? 'Não foi possível concluir a missão.'));
        } else {
            $streak = (new CrismaQuestStreakService())->qualifyActivity('quest_exercise', (int)$exerciseId);
            $days = (int)($streak['current'] ?? 0);
            Flash::add('success', 'Missão concluída. Sua Chama está acesa há ' . $days . ($days === 1 ? ' dia.' : ' dias.'));
        }

        $redirectUrl = '/studenti/quest/' . (int)$questId . '/capitoli/' . (int)$chapterId . '/esercizi/' . (int)$exerciseId;
        if (($result['success'] ?? false) === true && !empty($result['forziere_vinto'])) {
            $redirectUrl .= '?' . http_build_query(['forziere_vinto' => (string)$result['forziere_vinto']]);
        }
        if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success'=>(bool)($result['success'] ?? false),'redirectUrl'=>$redirectUrl]); exit;
        }
        header('Location: ' . $redirectUrl); exit;
    }

    public function deleteDeliveredFile(string $questId, string $chapterId, string $exerciseId): void
    {
        $result = (new StudentQuestService())->deleteDeliveredFile((int)$questId, (int)$chapterId, (int)$exerciseId, (string)($_POST['file_name'] ?? ''));
        Flash::add(($result['success'] ?? false) ? 'success' : 'danger', (string)($result['message'] ?? 'Operação concluída.'));
        header('Location: /studenti/quest/' . (int)$questId . '/capitoli/' . (int)$chapterId . '/esercizi/' . (int)$exerciseId); exit;
    }

    private function guard(int $permissionStatus): void
    {
        if ($permissionStatus === PermissionService::STATUS_NO_CLASS) {
            Flash::add('danger', 'permission.noclass'); header('Location: /studenti/dashboard'); exit;
        }
        if ($permissionStatus === PermissionService::STATUS_NOT_CLASS_OWNER) {
            Session::set('class', null); Flash::add('danger', 'permission.notyourclass'); header('Location: /studenti/dashboard'); exit;
        }
        if ($permissionStatus === PermissionService::STATUS_NOT_STUDENT) {
            Flash::add('danger', 'permission.nostudent'); header('Location: /loginStud'); exit;
        }
        if ($permissionStatus === PermissionService::STATUS_NOT_LOGGED) {
            header('Location: /loginStud'); exit;
        }
    }
}

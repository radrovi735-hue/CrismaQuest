<?php

namespace App\Controller;

use App\Core\View;
use App\Service\Flash;
use App\Service\PermissionService;
use App\Service\Session;
use App\Service\StudentDashboardService;

class StudentDashboardController
{
    public function index(): void
    {
        $service = new StudentDashboardService();
        $data = $service->getSelectionPageData();
        $permissionStatus = $data['permissionStatus'] ?? PermissionService::STATUS_NOT_LOGGED;

        if ($permissionStatus === PermissionService::STATUS_NOT_LOGGED) {
            header('Location: /loginStud'); exit;
        }
        if ($permissionStatus === PermissionService::STATUS_NOT_STUDENT) {
            Flash::add('danger', 'permission.nostudent');
            header('Location: /loginStud'); exit;
        }

        View::render('studenti/dashboard', array_merge($data, [
            'title' => 'student.dashboard.title',
            'disableStudentTopbarData' => true,
            'pageStyles' => ['/css/headers.css','/css/classes.css'],
            'useMathJax' => false,
        ]), 'mainStudLayout');
    }

    public function selectClass(string $classId): void
    {
        $selected = (new StudentDashboardService())->selectClass((int) $classId);
        if (!$selected) {
            Flash::add('danger', 'student.classes.select.error');
            header('Location: /studenti/dashboard'); exit;
        }
        header('Location: /studenti/classe/dashboard'); exit;
    }

    public function showClassDashboard(): void
    {
        $service = new StudentDashboardService();
        $data = $service->getClassDashboardData();
        $this->guardClassPage($data);

        $requestedView = (string) ($_GET['view'] ?? 'home');
        $view = match ($requestedView) {
            'journey' => 'studenti/journey',
            'album' => 'studenti/album',
            default => 'studenti/classDashboard',
        };
        $title = match ($requestedView) {
            'journey' => 'Jornada',
            'album' => 'Álbum dos Santos',
            default => 'CrismaQuest',
        };

        View::render($view, array_merge($data, [
            'title' => $title,
            'pageStyles' => ['/css/crismaquest-app.css'],
            'useMathJax' => false,
        ]), 'mainStudLayout');
    }

    public function showClassmates(): void
    {
        $service = new StudentDashboardService();
        $data = $service->getClassmatesPageData();
        $this->guardClassPage($data);

        View::render('studenti/classmates', array_merge($data, [
            'title' => 'student.classmates.title',
            'pageStyles' => ['/css/headers.css','/css/classes.css','/css/classmates.css'],
            'useMathJax' => false,
        ]), 'mainStudLayout');
    }

    public function chooseCharacter(): void
    {
        $characterId = (int) ($_POST['character_id'] ?? 0);
        $chosen = $characterId > 0 && (new StudentDashboardService())->chooseCharacter($characterId);
        Flash::add($chosen ? 'success' : 'danger', $chosen
            ? 'Personagem selecionado corretamente.'
            : 'student.dashboard.character.select.error');
        header('Location: /studenti/classe/dashboard'); exit;
    }

    public function activateTeamPower(): void
    {
        $result = (new StudentDashboardService())->activateTeamPower();
        Flash::add(($result['success'] ?? false) ? 'success' : 'danger', $result['message'] ?? 'Operação concluída.');
        header('Location: /studenti/classe/dashboard'); exit;
    }

    private function guardClassPage(array $data): void
    {
        $permissionStatus = $data['permissionStatus'] ?? PermissionService::STATUS_NOT_LOGGED;
        if ($permissionStatus === PermissionService::STATUS_NOT_LOGGED) {
            header('Location: /loginStud'); exit;
        }
        if ($permissionStatus === PermissionService::STATUS_NOT_STUDENT) {
            Flash::add('danger', 'permission.nostudent');
            header('Location: /loginStud'); exit;
        }
        if ($permissionStatus === PermissionService::STATUS_NO_CLASS) {
            Flash::add('danger', 'permission.noclass');
            header('Location: /studenti/dashboard'); exit;
        }
        if ($permissionStatus === PermissionService::STATUS_NOT_CLASS_OWNER) {
            Session::set('class', null);
            Flash::add('danger', 'permission.notyourclass');
            header('Location: /studenti/dashboard'); exit;
        }
    }
}

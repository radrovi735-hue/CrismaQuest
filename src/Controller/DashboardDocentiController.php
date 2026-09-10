<?php

namespace App\Controller;

use App\Core\View;
use App\Service\CrismaQuestAttendanceService;
use App\Service\DashboardDocentiService;
use App\Service\Flash;
use App\Service\PermissionService;
use App\Service\Session;

class DashboardDocentiController
{
    public function showDashboardDocenti(): void
    {
        $dashboardData = (new DashboardDocentiService())->getDashboardData();

        if (($dashboardData['permissionStatus'] ?? null) === PermissionService::STATUS_NO_CLASS) {
            header('Location: /docenti/classi'); exit;
        }
        if (($dashboardData['permissionStatus'] ?? null) === PermissionService::STATUS_NOT_CLASS_OWNER) {
            Session::set('class', null);
            Flash::add('danger', 'teacher.classes.select.error');
            header('Location: /docenti/classi'); exit;
        }

        $requestedView = (string)($_GET['view'] ?? 'home');
        if ($requestedView === 'attendance') {
            $attendance = (new CrismaQuestAttendanceService())->getPageData($dashboardData['students'] ?? []);
            View::render('docenti/attendance', array_merge($dashboardData, $attendance, [
                'title'=>'Presença',
                'pageStyles'=>['/css/crismaquest-teacher.css'],
                'pageScripts'=>[],
                'useMathJax'=>false,
            ]), 'mainDocLayout');
            return;
        }

        View::render('docenti/dashboardDocenti', array_merge($dashboardData, [
            'title' => 'Painel do Catequista',
            'pageStyles' => ['/css/crismaquest-teacher.css'],
            'pageScripts' => [],
            'pageModals' => [],
            'useMathJax' => false,
        ]), 'mainDocLayout');
    }

    public function removeHeart(): void
    {
        $studentId=(int)($_POST['id_studente']??0); $motivation=(string)($_POST['motivazione']??'');
        $this->json((new DashboardDocentiService())->removeHeart($studentId,$motivation));
    }

    public function removeHeartBulk(): void
    {
        $ids=$_POST['ids']??[]; if(!is_array($ids)){$ids=[];}
        $this->json((new DashboardDocentiService())->removeHeartBulk($ids,(string)($_POST['msg']??'')));
    }

    public function instantDeath(): void
    {
        $this->json((new DashboardDocentiService())->instantDeath((int)($_POST['id_studente']??0)));
    }

    public function rewardBulk(): void
    {
        if ((string)($_POST['mode'] ?? '') === 'attendance') {
            $this->json((new CrismaQuestAttendanceService())->saveBulk($_POST));
            return;
        }

        $ids=$_POST['ids']??[]; if(!is_array($ids)){$ids=[];}
        $this->json((new DashboardDocentiService())->rewardBulk(
            $ids,
            (int)($_POST['xp']??0),
            (int)($_POST['monete']??0),
            (string)($_POST['motivazione']??'')
        ));
    }

    public function assignDeathPunishment(): void
    {
        $this->json((new DashboardDocentiService())->assignDeathPunishment($_POST,$_FILES['file']??[]));
    }

    private function json(array $payload,int $statusCode=200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}

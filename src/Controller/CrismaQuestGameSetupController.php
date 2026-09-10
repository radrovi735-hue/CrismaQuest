<?php

namespace App\Controller;

use App\Core\View;
use App\Service\CrismaQuestGameSetupService;
use App\Service\PermissionService;
use Throwable;

final class CrismaQuestGameSetupController
{
    public function index(): void
    {
        $permission = new PermissionService();
        if ($permission->checkPermissionsTeacher() !== PermissionService::STATUS_OK) {
            header('Location: /loginDoc');
            exit;
        }

        $service = new CrismaQuestGameSetupService();
        View::render('docenti/crismaquestGameSetup', [
            'title'=>'Instalar Jogo CrismaQuest',
            'status'=>$service->status(),
            'lastStep'=>CrismaQuestGameSetupService::LAST_STEP,
            'pageStyles'=>['/css/crismaquest-game.css'],
            'pageScripts'=>[],
            'useMathJax'=>false,
        ], 'mainDocLayout');
    }

    public function run(string $step): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $permission = new PermissionService();
        if ($permission->checkPermissionsTeacher() !== PermissionService::STATUS_OK) {
            http_response_code(403);
            echo json_encode(['ok'=>false,'message'=>'Acesso restrito aos catequistas.']);
            return;
        }

        try {
            $result = (new CrismaQuestGameSetupService())->runStep((int)$step);
            echo json_encode(['ok'=>true] + $result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}

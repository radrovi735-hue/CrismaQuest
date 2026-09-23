<?php

namespace App\Controller;

use App\Service\CrismaQuestGameAccess;
use App\Service\CrismaQuestNotificationService;
use App\Service\PermissionService;

final class CrismaQuestNotificationController
{
    public function pending(): void
    {
        $permission = new PermissionService();
        if ($permission->checkPermissionsStudent() !== PermissionService::STATUS_OK) {
            $this->json(['ok'=>false,'notifications'=>[]],401);
            return;
        }

        $userId = (int)($permission->getCurrentUserId() ?? 0);
        $this->json([
            'ok'=>true,
            'csrf_token'=>CrismaQuestGameAccess::token(),
            'notifications'=>(new CrismaQuestNotificationService())->pending($userId),
        ]);
    }

    public function seen(string $id): void
    {
        $permission = new PermissionService();
        if ($permission->checkPermissionsStudent() !== PermissionService::STATUS_OK) {
            $this->json(['ok'=>false],401);
            return;
        }

        if (!CrismaQuestGameAccess::validToken($_POST['csrf_token'] ?? null)) {
            $this->json(['ok'=>false],403);
            return;
        }

        $userId = (int)($permission->getCurrentUserId() ?? 0);
        $ok = (new CrismaQuestNotificationService())->markSeen($userId,(int)$id);
        $this->json(['ok'=>$ok]);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}

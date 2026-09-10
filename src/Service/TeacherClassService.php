<?php

namespace App\Service;

use PDO;

// Serviço de seleção e criação de turmas na área dos catequistas.
class TeacherClassService
{
    private const ALLOWED_ICONS = [
        'fa-dove',
        'fa-cross',
        'fa-book-bible',
        'fa-fire-flame-curved',
        'fa-church',
        'fa-compass',
        'fa-people-group',
        'fa-seedling',
        'fa-star',
        'fa-landmark',
    ];

    public function getSelectionPageData(): array
    {
        $permissionService = new PermissionService();
        $permissionStatus = $permissionService->checkTeacherAreaAccess();
        $userId = $permissionService->getCurrentUserId();

        return [
            'permissionStatus' => $permissionStatus,
            'classes' => ($permissionStatus === PermissionService::STATUS_OK && $userId !== null)
                ? $this->getTeacherClasses($userId)
                : [],
            'schoolYears' => ($permissionStatus === PermissionService::STATUS_OK)
                ? $this->getSchoolYears()
                : [],
            'availableIcons' => self::ALLOWED_ICONS,
            'selectedClassId' => $permissionService->getCurrentClassId(),
        ];
    }

    public function getTeacherClasses(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.id_classe, c.nome_classe, c.colore, c.icona, a.anno_scolastico
             FROM ct_utenti_classi uc
             INNER JOIN ct_classi c ON c.id_classe = uc.fk_classe
             INNER JOIN ct_anni_scolastici a ON a.id_anno = c.fk_anno_scolastico
             WHERE uc.fk_utente = :id_utente
               AND c.eliminata = 0
             ORDER BY a.anno_scolastico DESC, c.nome_classe ASC'
        );
        $stmt->execute(['id_utente' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateClass(int $classId, string $name, string $icon, string $color): bool
    {
        $permissionService = new PermissionService();
        $userId = $permissionService->getCurrentUserId();
        if ($userId === null || !$permissionService->isTeacherOrAdmin($userId)) return false;

        $class = $this->findTeacherClass($userId, $classId);
        if ($class === null) return false;

        $name = trim($name);
        if ($name === '') return false;
        if (!in_array($icon, self::ALLOWED_ICONS, true)) $icon = 'fa-dove';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#6f1d2a';

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE ct_classi
             SET nome_classe = :nome_classe, icona = :icona, colore = :colore
             WHERE id_classe = :id_classe AND eliminata = 0'
        );
        $stmt->execute([
            'nome_classe' => $name,
            'icona' => $icon,
            'colore' => $color,
            'id_classe' => $classId,
        ]);

        if ($permissionService->getCurrentClassId() === $classId) {
            Session::set('class', [
                'id' => $classId,
                'name' => $name,
                'color' => $color,
                'icon' => $icon,
                'school_year' => $class['anno_scolastico'],
            ]);
        }
        return true;
    }

    public function getSchoolYears(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT id_anno, anno_scolastico FROM ct_anni_scolastici ORDER BY anno_scolastico DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function selectClass(int $classId): bool
    {
        $permissionService = new PermissionService();
        $userId = $permissionService->getCurrentUserId();
        if ($userId === null || !$permissionService->isTeacherOrAdmin($userId)) return false;

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.id_classe, c.nome_classe, c.colore, c.icona, a.anno_scolastico
             FROM ct_utenti_classi uc
             INNER JOIN ct_classi c ON c.id_classe = uc.fk_classe
             INNER JOIN ct_anni_scolastici a ON a.id_anno = c.fk_anno_scolastico
             WHERE uc.fk_utente = :id_utente
               AND uc.fk_classe = :id_classe
               AND c.eliminata = 0
             LIMIT 1'
        );
        $stmt->execute(['id_utente' => $userId, 'id_classe' => $classId]);

        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$class) {
            Session::set('class', null);
            return false;
        }

        Session::set('class', [
            'id' => (int) $class['id_classe'],
            'name' => $class['nome_classe'],
            'color' => $class['colore'],
            'icon' => $class['icona'],
            'school_year' => $class['anno_scolastico'],
        ]);
        return true;
    }

    public function createClass(string $name, int $schoolYearId, string $icon, string $color): bool
    {
        $permissionService = new PermissionService();
        $userId = $permissionService->getCurrentUserId();
        if ($userId === null || !$permissionService->isTeacherOrAdmin($userId)) return false;

        $name = trim($name);
        if ($name === '' || !$this->schoolYearExists($schoolYearId)) return false;
        if (!in_array($icon, self::ALLOWED_ICONS, true)) $icon = 'fa-dove';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#6f1d2a';

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO ct_classi (nome_classe, fk_anno_scolastico, icona, colore, eliminata)
             VALUES (:nome_classe, :fk_anno_scolastico, :icona, :colore, 0)'
        );
        $stmt->execute([
            'nome_classe' => $name,
            'fk_anno_scolastico' => $schoolYearId,
            'icona' => $icon,
            'colore' => $color,
        ]);

        $classId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO ct_utenti_classi (fk_utente, fk_classe) VALUES (:fk_utente, :fk_classe)');
        $stmt->execute(['fk_utente' => $userId, 'fk_classe' => $classId]);

        return $this->selectClass($classId);
    }

    private function schoolYearExists(int $schoolYearId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ct_anni_scolastici WHERE id_anno = :id_anno');
        $stmt->execute(['id_anno' => $schoolYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function findTeacherClass(int $userId, int $classId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.id_classe, c.nome_classe, c.colore, c.icona, a.anno_scolastico
             FROM ct_utenti_classi uc
             INNER JOIN ct_classi c ON c.id_classe = uc.fk_classe
             INNER JOIN ct_anni_scolastici a ON a.id_anno = c.fk_anno_scolastico
             WHERE uc.fk_utente = :id_utente
               AND uc.fk_classe = :id_classe
               AND c.eliminata = 0
             LIMIT 1'
        );
        $stmt->execute(['id_utente' => $userId, 'id_classe' => $classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($class) ? $class : null;
    }
}

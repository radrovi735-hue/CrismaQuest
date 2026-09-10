<?php

namespace App\Service;

use PDO;

class StudentProfileService
{
    public function getCurrentUserProfile(): ?array
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) return null;

        $stmt = Database::getConnection()->prepare(
            'SELECT id_utente, nome, cognome, username
             FROM ct_utenti
             WHERE id_utente = :id_utente
             LIMIT 1'
        );
        $stmt->execute(['id_utente'=>$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateCurrentUserProfile(array $payload): array
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) return $this->error('Faça login novamente para alterar sua senha.');

        $password = (string)($payload['password'] ?? '');
        $passwordConfirm = (string)($payload['password_confirm'] ?? '');
        if ($password === '') return $this->error('Informe a nova senha.');
        if (strlen($password) < 8) return $this->error('A nova senha precisa ter pelo menos 8 caracteres.');
        if ($password !== $passwordConfirm) return $this->error('As duas senhas não coincidem.');

        $stmt = Database::getConnection()->prepare(
            'UPDATE ct_utenti SET password=:password WHERE id_utente=:id_utente'
        );
        $stmt->execute([
            'password'=>password_hash($password, PASSWORD_DEFAULT),
            'id_utente'=>$userId,
        ]);

        return ['success'=>true,'message'=>'Senha alterada com sucesso.'];
    }

    private function getCurrentUserId(): ?int
    {
        $user = Session::get('user');
        $userId = isset($user['id']) ? (int)$user['id'] : 0;
        return $userId > 0 ? $userId : null;
    }

    private function error(string $message): array
    {
        return ['success'=>false,'message'=>$message];
    }
}

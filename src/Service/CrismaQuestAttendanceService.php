<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class CrismaQuestAttendanceService
{
    private const TIMEZONE = 'America/Fortaleza';

    public function getPageData(array $students): array
    {
        $classId = (new PermissionService())->getCurrentClassId();
        if ($classId === null) {
            return ['meeting'=>null,'attendance'=>[],'students'=>$students];
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT * FROM cq_meetings WHERE class_id = :class_id ORDER BY ABS(TIMESTAMPDIFF(SECOND, meeting_at, NOW())) ASC LIMIT 1'
            );
            $stmt->execute(['class_id'=>$classId]);
            $meeting = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $attendance = [];
            if ($meeting) {
                $a = $pdo->prepare('SELECT user_id, status, xp_granted, note FROM cq_attendance WHERE meeting_id = :meeting_id');
                $a->execute(['meeting_id'=>(int)$meeting['id']]);
                foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $attendance[(int)$row['user_id']] = $row;
                }
            }

            $mappedStudents = $this->attachUserIds($students);
            return ['meeting'=>$meeting,'attendance'=>$attendance,'students'=>$mappedStudents];
        } catch (Throwable) {
            return ['meeting'=>null,'attendance'=>[],'students'=>$this->attachUserIds($students)];
        }
    }

    public function saveBulk(array $payload): array
    {
        $permission = new PermissionService();
        $classId = $permission->getCurrentClassId();
        $teacherId = (int)(Session::get('user')['id'] ?? 0);
        if ($classId === null || $teacherId <= 0) {
            return ['status'=>'error','message'=>'Turma ou catequista não identificados.'];
        }

        $title = trim((string)($payload['meeting_title'] ?? 'Encontro da Crisma'));
        $date = trim((string)($payload['meeting_date'] ?? ''));
        $statusesRaw = $payload['statuses'] ?? [];
        if (is_string($statusesRaw)) {
            $decoded = json_decode($statusesRaw, true);
            $statusesRaw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($statusesRaw) || $statusesRaw === []) {
            return ['status'=>'error','message'=>'Marque ao menos um crismando.'];
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $meetingDate = $date !== '' ? DateTimeImmutable::createFromFormat('Y-m-d H:i', $date, $tz) : new DateTimeImmutable('now', $tz);
        if (!$meetingDate) {
            return ['status'=>'error','message'=>'Data do encontro inválida.'];
        }

        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            $lookup = $pdo->prepare(
                'SELECT id FROM cq_meetings WHERE class_id=:class_id AND DATE(meeting_at)=:meeting_day ORDER BY id DESC LIMIT 1'
            );
            $lookup->execute(['class_id'=>$classId,'meeting_day'=>$meetingDate->format('Y-m-d')]);
            $meetingId = (int)($lookup->fetchColumn() ?: 0);
            if ($meetingId <= 0) {
                $insert = $pdo->prepare(
                    'INSERT INTO cq_meetings (class_id,title,meeting_at,theme,presence_xp,status) VALUES (:class_id,:title,:meeting_at,:theme,50,"scheduled")'
                );
                $insert->execute([
                    'class_id'=>$classId,
                    'title'=>$title !== '' ? $title : 'Encontro da Crisma',
                    'meeting_at'=>$meetingDate->format('Y-m-d H:i:s'),
                    'theme'=>trim((string)($payload['meeting_theme'] ?? '')) ?: null,
                ]);
                $meetingId = (int)$pdo->lastInsertId();
            }

            foreach ($statusesRaw as $studentIdRaw => $statusRaw) {
                $studentId = (int)$studentIdRaw;
                $status = in_array($statusRaw, ['presente','ausente','justificada','atrasado'], true) ? $statusRaw : 'ausente';
                if ($studentId <= 0) { continue; }

                $map = $pdo->prepare('SELECT fk_utente FROM ct_studenti WHERE id_studente=:student_id LIMIT 1');
                $map->execute(['student_id'=>$studentId]);
                $userId = (int)($map->fetchColumn() ?: 0);
                if ($userId <= 0) { continue; }

                $existingStmt = $pdo->prepare('SELECT id,status,xp_granted FROM cq_attendance WHERE meeting_id=:meeting_id AND user_id=:user_id FOR UPDATE');
                $existingStmt->execute(['meeting_id'=>$meetingId,'user_id'=>$userId]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                $oldStatus = $existing['status'] ?? null;
                $oldXp = (int)($existing['xp_granted'] ?? 0);
                $newXp = $oldXp;

                if ($status === 'presente' && $oldXp === 0) {
                    $award = $pdo->prepare('UPDATE ct_studenti SET xp=xp+50 WHERE id_studente=:student_id');
                    $award->execute(['student_id'=>$studentId]);
                    $newXp = 50;
                } elseif ($status !== 'presente' && $oldXp > 0) {
                    $remove = $pdo->prepare('UPDATE ct_studenti SET xp=GREATEST(0,xp-:xp) WHERE id_studente=:student_id');
                    $remove->execute(['xp'=>$oldXp,'student_id'=>$studentId]);
                    $newXp = 0;
                }

                $upsert = $pdo->prepare(
                    'INSERT INTO cq_attendance (meeting_id,user_id,status,marked_by,xp_granted,marked_at)
                     VALUES (:meeting_id,:user_id,:status,:marked_by,:xp_granted,NOW())
                     ON DUPLICATE KEY UPDATE status=VALUES(status),marked_by=VALUES(marked_by),xp_granted=VALUES(xp_granted),marked_at=NOW()'
                );
                $upsert->execute(['meeting_id'=>$meetingId,'user_id'=>$userId,'status'=>$status,'marked_by'=>$teacherId,'xp_granted'=>$newXp]);

                $audit = $pdo->prepare(
                    'INSERT INTO cq_attendance_audit (meeting_id,user_id,old_status,new_status,xp_delta,changed_by,changed_at)
                     VALUES (:meeting_id,:user_id,:old_status,:new_status,:xp_delta,:changed_by,NOW())'
                );
                $audit->execute([
                    'meeting_id'=>$meetingId,'user_id'=>$userId,'old_status'=>$oldStatus,'new_status'=>$status,
                    'xp_delta'=>$newXp-$oldXp,'changed_by'=>$teacherId,
                ]);
            }

            $pdo->commit();
            return ['status'=>'success','message'=>'Presença salva com segurança. XP foi ajustado somente quando necessário.','meeting_id'=>$meetingId];
        } catch (Throwable $e) {
            try { $pdo = Database::getConnection(); if ($pdo->inTransaction()) { $pdo->rollBack(); } } catch (Throwable) {}
            return ['status'=>'error','message'=>'Não foi possível salvar a presença. Verifique se a migração do CrismaQuest foi aplicada.'];
        }
    }

    private function attachUserIds(array $students): array
    {
        if ($students === []) { return []; }
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT fk_utente FROM ct_studenti WHERE id_studente=:id LIMIT 1');
            foreach ($students as &$student) {
                $stmt->execute(['id'=>(int)($student['id'] ?? 0)]);
                $student['userId'] = (int)($stmt->fetchColumn() ?: 0);
            }
            unset($student);
        } catch (Throwable) {
            foreach ($students as &$student) { $student['userId'] = 0; }
            unset($student);
        }
        return $students;
    }
}

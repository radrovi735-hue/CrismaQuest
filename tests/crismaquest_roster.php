<?php
declare(strict_types=1);

require getenv('CQ_TEST_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';

use App\Service\CrismaQuestRosterService;
use App\Service\Database;

foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) $_ENV[$key] = (string)getenv($key);

function rosterCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "OK: {$message}\n";
}

$pdo = Database::getConnection();
CrismaQuestRosterService::ensureSeeded();

$classId = (int)$pdo->query(
    "SELECT id_classe FROM ct_classi
     WHERE eliminata=0
     ORDER BY CASE WHEN nome_classe LIKE 'Crisma%' THEN 0 ELSE 1 END,id_classe
     LIMIT 1"
)->fetchColumn();

$usernames = array_column(CrismaQuestRosterService::roster(), 'username');
$placeholders = implode(',', array_fill(0, count($usernames), '?'));

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT u.username)
     FROM ct_utenti u
     INNER JOIN ct_studenti s ON s.fk_utente=u.id_utente
     INNER JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
     INNER JOIN ct_utenti_tipi ut ON ut.fk_utente=u.id_utente AND ut.fk_tipo_utente=2
     WHERE sc.fk_classe=?
       AND u.username IN ($placeholders)"
);
$stmt->execute(array_merge([$classId], $usernames));
rosterCheck((int)$stmt->fetchColumn() === 28, '28 crismandos cadastrados e vinculados à turma');

$ana = $pdo->prepare("SELECT password FROM ct_utenti WHERE username='ana.rodrigues' LIMIT 1");
$ana->execute();
rosterCheck(password_verify('anarodrigues', (string)$ana->fetchColumn()), 'senha padrão baseada apenas no nome funciona');

$sofia = $pdo->prepare("SELECT password FROM ct_utenti WHERE username='sofia.sousa' LIMIT 1");
$sofia->execute();
rosterCheck(password_verify('sofiasousa', (string)$sofia->fetchColumn()), 'segunda senha padrão funciona');

$before = (int)$pdo->query('SELECT COUNT(*) FROM ct_studenti_classi')->fetchColumn();
CrismaQuestRosterService::ensureSeeded();
$after = (int)$pdo->query('SELECT COUNT(*) FROM ct_studenti_classi')->fetchColumn();
rosterCheck($before === $after, 'seed da turma é idempotente');

echo "PASS: Lista atual cadastrada como usuários do CrismaQuest\n";

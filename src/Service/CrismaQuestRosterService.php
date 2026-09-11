<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Cadastro inicial da turma canônica do CrismaQuest.
 *
 * Fonte: planilha "Lista atual" da Paróquia Nossa Senhora dos Remédios.
 * O seed é idempotente: depois que os 28 crismandos estão vinculados à turma,
 * não altera novamente senhas nem progresso.
 */
final class CrismaQuestRosterService
{
    private const EXPECTED_COUNT = 28;

    public static function ensureSeeded(): void
    {
        $pdo = Database::getConnection();
        $classId = self::resolveClassId($pdo);
        if ($classId <= 0) {
            throw new RuntimeException('Turma do CrismaQuest não encontrada para cadastro dos crismandos.');
        }

        $roster = self::roster();
        $usernames = array_column($roster, 'username');
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));

        $check = $pdo->prepare(
            "SELECT COUNT(DISTINCT u.username)
             FROM ct_utenti u
             INNER JOIN ct_studenti s ON s.fk_utente=u.id_utente
             INNER JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
             WHERE sc.fk_classe=?
               AND u.username IN ($placeholders)"
        );
        $check->execute(array_merge([$classId], $usernames));
        if ((int)$check->fetchColumn() === self::EXPECTED_COUNT) {
            return;
        }

        try {
            $pdo->beginTransaction();

            foreach ($roster as $account) {
                self::upsertStudent($pdo, $classId, $account);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function upsertStudent(PDO $pdo, int $classId, array $account): void
    {
        $find = $pdo->prepare(
            'SELECT id_utente,password
             FROM ct_utenti
             WHERE username=:username
             LIMIT 1'
        );
        $find->execute(['username'=>$account['username']]);
        $existing = $find->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($existing !== null) {
            $userId = (int)$existing['id_utente'];

            $forbidden = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM ct_utenti_tipi ut
                 WHERE ut.fk_utente=:u
                   AND ut.fk_tipo_utente IN (1,3)"
            );
            $forbidden->execute(['u'=>$userId]);
            if ((int)$forbidden->fetchColumn() > 0) {
                throw new RuntimeException('Username reservado por docente/administrador: '.$account['username']);
            }

            $update = $pdo->prepare(
                "UPDATE ct_utenti
                 SET nome=:nome,
                     cognome=:cognome,
                     email=:email,
                     password=:password,
                     validato=1,
                     fk_tipo_utente=2,
                     language='it'
                 WHERE id_utente=:id"
            );
            $update->execute([
                'nome'=>$account['nome'],
                'cognome'=>$account['cognome'],
                'email'=>$account['email'],
                'password'=>password_hash($account['password'], PASSWORD_DEFAULT),
                'id'=>$userId,
            ]);
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO ct_utenti
                    (nome,cognome,username,password,email,codice_conf,validato,fk_tipo_utente,ricevi_mail,sesso,language)
                 VALUES
                    (:nome,:cognome,:username,:password,:email,NULL,1,2,0,'M','it')"
            );
            $insert->execute([
                'nome'=>$account['nome'],
                'cognome'=>$account['cognome'],
                'username'=>$account['username'],
                'password'=>password_hash($account['password'], PASSWORD_DEFAULT),
                'email'=>$account['email'],
            ]);
            $userId = (int)$pdo->lastInsertId();
        }

        $role = $pdo->prepare(
            'SELECT COUNT(*) FROM ct_utenti_tipi
             WHERE fk_utente=:u AND fk_tipo_utente=2'
        );
        $role->execute(['u'=>$userId]);
        if ((int)$role->fetchColumn() === 0) {
            $pdo->prepare(
                'INSERT INTO ct_utenti_tipi (fk_utente,fk_tipo_utente)
                 VALUES (:u,2)'
            )->execute(['u'=>$userId]);
        }

        $student = $pdo->prepare(
            'SELECT id_studente FROM ct_studenti
             WHERE fk_utente=:u
             ORDER BY id_studente
             LIMIT 1'
        );
        $student->execute(['u'=>$userId]);
        $studentId = (int)($student->fetchColumn() ?: 0);
        if ($studentId <= 0) {
            $pdo->prepare(
                'INSERT INTO ct_studenti (fk_utente,l104)
                 VALUES (:u,0)'
            )->execute(['u'=>$userId]);
            $studentId = (int)$pdo->lastInsertId();
        }

        $linked = $pdo->prepare(
            'SELECT COUNT(*) FROM ct_studenti_classi
             WHERE fk_studente=:s AND fk_classe=:c'
        );
        $linked->execute(['s'=>$studentId,'c'=>$classId]);
        if ((int)$linked->fetchColumn() === 0) {
            $pdo->prepare(
                'INSERT INTO ct_studenti_classi (fk_studente,fk_classe)
                 VALUES (:s,:c)'
            )->execute(['s'=>$studentId,'c'=>$classId]);
        }
    }

    private static function resolveClassId(PDO $pdo): int
    {
        $stmt = $pdo->query(
            "SELECT id_classe
             FROM ct_classi
             WHERE eliminata=0
             ORDER BY
               CASE
                 WHEN nome_classe='Crisma 2026–2027' THEN 0
                 WHEN nome_classe LIKE 'Crisma%' THEN 1
                 ELSE 2
               END,
               id_classe
             LIMIT 1"
        );
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public static function roster(): array
    {
        return [
            ['nome'=>'Ana','cognome'=>'Gabriela Sousa Rodrigues','username'=>'ana.rodrigues','password'=>'anarodrigues','email'=>'ig3287062@gmail.com'],
            ['nome'=>'Christian','cognome'=>'Sales Oliveira','username'=>'christian.oliveira','password'=>'christianoliveira','email'=>'saleschristian629@gmail.com'],
            ['nome'=>'Daniel','cognome'=>'de Oliveira Quinderi Barreto','username'=>'daniel.barreto','password'=>'danielbarreto','email'=>'jeronimamontese@gmail.com'],
            ['nome'=>'Davi','cognome'=>'Silveira Andrade','username'=>'davi.andrade','password'=>'daviandrade','email'=>'iaradasilvabritom@gmail.com'],
            ['nome'=>'Davi','cognome'=>'Patriota Lobo','username'=>'davi.lobo','password'=>'davilobo','email'=>'prof.fabio.lobo@gmail.com'],
            ['nome'=>'Douglas','cognome'=>'Davison dos Santos de Sousa','username'=>'douglas.sousa','password'=>'douglassousa','email'=>'douglasdevisomsousadavison172@gmail.com'],
            ['nome'=>'Eloah','cognome'=>'Cabral Cunha Lima','username'=>'eloah.lima','password'=>'eloahlima','email'=>'eloahlima@crismaquest.local'],
            ['nome'=>'Fátima','cognome'=>'Sophia Teles Cardoso de Souza','username'=>'fatima.souza','password'=>'fatimasouza','email'=>'fatima.sophia0978@gmail.com'],
            ['nome'=>'Francisco','cognome'=>'Ariel Pereira Soares','username'=>'francisco.soares','password'=>'franciscosoares','email'=>'gp290318@gmail.com'],
            ['nome'=>'Gabriel','cognome'=>'Aguiar Cavalcante','username'=>'gabriel.cavalcante','password'=>'gabrielcavalcante','email'=>'crisaaguiarx@gmail.com'],
            ['nome'=>'Graça','cognome'=>'Anabelly Paiva de Souza','username'=>'graca.souza','password'=>'gracasouza','email'=>'gracasouza@crismaquest.local'],
            ['nome'=>'Herliana','cognome'=>'Marfisa Gonçalo de Sampaio','username'=>'herliana.sampaio','password'=>'herlianasampaio','email'=>'herlianasampaio@crismaquest.local'],
            ['nome'=>'Hernande','cognome'=>'Hilton Gonçalo de Sampaio','username'=>'hernande.sampaio','password'=>'hernandesampaio','email'=>'hernandesampaio@crismaquest.local'],
            ['nome'=>'Isis','cognome'=>'Aguiar Cavalcante','username'=>'isis.cavalcante','password'=>'isiscavalcante','email'=>'crisaaguiarx+isiscavalcante@gmail.com'],
            ['nome'=>'João','cognome'=>'Guilherme Oliveira de Sousa','username'=>'joao.sousa','password'=>'joaosousa','email'=>'joaosousa@crismaquest.local'],
            ['nome'=>'José','cognome'=>'Eduardo Figueiredo Aragão','username'=>'jose.aragao','password'=>'josearagao','email'=>'elhenei.valda.goncalves.figueiredo@gmail.com'],
            ['nome'=>'Lana','cognome'=>'Beatriz Cândido Teixeira','username'=>'lana.teixeira','password'=>'lanateixeira','email'=>'jualcantona.1211@gmail.com'],
            ['nome'=>'Luan','cognome'=>'Sampaio Alves de Andrade','username'=>'luan.andrade','password'=>'luanandrade','email'=>'assisandrade908@gmail.com'],
            ['nome'=>'Lucas','cognome'=>'Patriota Lobo','username'=>'lucas.lobo','password'=>'lucaslobo','email'=>'prof.fabio.lobo+lucaslobo@gmail.com'],
            ['nome'=>'Luísa','cognome'=>'Fernandes de Holanda','username'=>'luisa.holanda','password'=>'luisaholanda','email'=>'eugeniajoeyma@hotmail.com'],
            ['nome'=>'Tarso','cognome'=>'Gonçalves da Silva','username'=>'tarso.silva','password'=>'tarsosilva','email'=>'simone_183@hotmail.com'],
            ['nome'=>'Maria','cognome'=>'Alice da Silva Ié','username'=>'maria.ie','password'=>'mariaie','email'=>'iaradasilvabrito14@gmail.com'],
            ['nome'=>'Maria','cognome'=>'Lívia Moura Teixeira Soares Lima','username'=>'maria.lima','password'=>'marialima','email'=>'mayracoelho050@gmail.com'],
            ['nome'=>'Maria','cognome'=>'Rosa Teles Cardoso de Souza','username'=>'maria.souza','password'=>'mariasouza','email'=>'fskikochent@gmail.com'],
            ['nome'=>'Nidia','cognome'=>'Gabryelle Ferreira Benevenuto Silva','username'=>'nidia.silva','password'=>'nidiasilva','email'=>'nidiasilva@crismaquest.local'],
            ['nome'=>'Ruan','cognome'=>'Felipe Marinho de Brito','username'=>'ruan.brito','password'=>'ruanbrito','email'=>'ruanbrito@crismaquest.local'],
            ['nome'=>'Sofia','cognome'=>'Damasceno Moura','username'=>'sofia.moura','password'=>'sofiamoura','email'=>'adameireles@gmail.com'],
            ['nome'=>'Sofia','cognome'=>'Isabely Rodrigues Sousa','username'=>'sofia.sousa','password'=>'sofiasousa','email'=>'sofiasousa@crismaquest.local'],
        ];
    }
}

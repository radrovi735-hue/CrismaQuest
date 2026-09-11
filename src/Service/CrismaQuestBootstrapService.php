<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Instala, atualiza e saneia as extensões próprias do CrismaQuest de forma idempotente. */
class CrismaQuestBootstrapService
{
    private const LOCK_NAME = 'crismaquest_schema_bootstrap_v10';

    public static function ensureInstalled(): void
    {
        $pdo = Database::getConnection();

        self::sanitizeLegacySeed($pdo);
        CrismaQuestRosterService::ensureSeeded();
        self::curateSaintCharacters($pdo);
        self::syncSaintArtwork($pdo);

        if (self::isCoreReady($pdo) && self::isSocialReady($pdo)) return;

        $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name'=>self::LOCK_NAME]);
        if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Não foi possível obter o bloqueio de atualização do CrismaQuest.');

        try {
            $root = dirname(__DIR__, 2);
            if (!self::isCoreReady($pdo)) {
                self::importSqlFile($pdo, $root.'/sql/crismaquest/001_core.sql');
                self::importSqlFile($pdo, $root.'/sql/crismaquest/002_saints_seed.sql');
            }
            if (!self::isSocialReady($pdo)) self::importSqlFile($pdo, $root.'/sql/crismaquest/003_social_economy.sql');

            if (!self::isCoreReady($pdo) || !self::isSocialReady($pdo)) {
                throw new RuntimeException('A atualização do banco do CrismaQuest não foi concluída.');
            }
        } finally {
            try { $release=$pdo->prepare('SELECT RELEASE_LOCK(:lock_name)'); $release->execute(['lock_name'=>self::LOCK_NAME]); } catch (Throwable) {}
        }
    }

    /**
     * Atualiza instalações já completas quando muda apenas a curadoria visual.
     * A consulta é barata e o seed só é reaplicado enquanto a imagem canônica
     * de Carlo ainda não estiver presente no banco.
     */
    private static function syncSaintArtwork(PDO $pdo): void
    {
        try {
            $tables = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema=DATABASE()
                   AND table_name IN ('cq_saint_cards','cq_card_editions')"
            );
            if ((int)$tables->fetchColumn() !== 2) return;

            $current = (string)($pdo->query(
                "SELECT image_path FROM cq_saint_cards WHERE card_number=1 LIMIT 1"
            )->fetchColumn() ?: '');
            $expected = 'https://www.ctsbooks.org/wp-content/uploads/2025/10/St-Carlo-Acutis-Prayer-Card-1.png.webp';
            if ($current === $expected) return;

            self::importSqlFile($pdo, dirname(__DIR__,2).'/sql/crismaquest/002_saints_seed.sql');
        } catch (Throwable) {
            // Curadoria visual nunca impede o app de abrir; a próxima requisição tenta novamente.
        }
    }

    private static function sanitizeLegacySeed(PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT c.id_classe, c.fk_anno_scolastico
                 FROM ct_classi c
                 WHERE c.nome_classe = 'Test Class' AND c.eliminata = 0
                 LIMIT 1"
            );
            $stmt->execute();
            $demo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$demo) return;

            $pdo->beginTransaction();

            $rename = $pdo->prepare(
                "UPDATE ct_classi
                 SET nome_classe = 'Crisma 2026–2027',
                     icona = 'fa-dove',
                     colore = '#6f1d2a'
                 WHERE id_classe = :id_classe
                   AND nome_classe = 'Test Class'"
            );
            $rename->execute(['id_classe'=>(int)$demo['id_classe']]);

            $year = $pdo->prepare(
                "UPDATE ct_anni_scolastici
                 SET anno_scolastico = '2026/2027'
                 WHERE id_anno = :id_anno
                   AND anno_scolastico = '2025/2026'"
            );
            $year->execute(['id_anno'=>(int)$demo['fk_anno_scolastico']]);

            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }

    /**
     * Mantém um vínculo determinístico entre o personagem legado e o santo.
     *
     * Importante: a UI original do CrismaQuest usava id_personaggio % 12 para
     * escolher o santo. Portanto os registros originais (originale=1) precisam
     * conservar exatamente essa regra; mudar a ordem da lista não pode trocar
     * o avatar de um crismando que já fez sua escolha.
     *
     * Novos santos usam apenas linhas criadas pelo próprio CrismaQuest
     * (originale=0). Linhas personalizadas que não pertencem ao catálogo não
     * são tocadas.
     */
    private static function curateSaintCharacters(PDO $pdo): void
    {
        $legacy = [
            ['São Carlo Acutis','Jovem testemunha de amor à Eucaristia e de evangelização no mundo digital.','/assets/crismaquest/saints/sao-carlo-acutis-user.jpg'],
            ['Santa Teresinha do Menino Jesus','Recorda que a santidade também passa pelas pequenas coisas feitas com grande amor.','/assets/crismaquest/saints/santa-teresinha-menino-jesus.jpg'],
            ['São Francisco de Assis','Inspira simplicidade, fraternidade, cuidado com a criação e alegria no seguimento de Cristo.','/assets/crismaquest/saints/sao-francisco-assis-user.jpg?v=20260911j'],
            ['São Pedro','Discípulo chamado por Jesus a amadurecer na fé e servir à Igreja com coragem.','/assets/crismaquest/saints/sao-pedro.jpg'],
            ['Santa Faustina Kowalska','Testemunha da misericórdia de Deus e do chamado a confiar em Jesus.','/assets/crismaquest/saints/santa-faustina-kowalska.png'],
            ['São João Paulo II','Convidou os jovens a não terem medo de seguir Cristo e assumir sua missão no mundo.','/assets/crismaquest/saints/sao-joao-paulo-ii.jpg'],
            ['Santa Gianna Beretta Molla','Testemunha de vocação, serviço, responsabilidade e amor concreto ao próximo.','/assets/crismaquest/saints/santa-gianna-beretta-molla.jpg'],
            ['Santo Agostinho','Sua busca pela verdade recorda que fé, razão e conversão caminham juntas.','/assets/crismaquest/saints/santo-agostinho.jpg'],
            ['Santa Mônica','Exemplo de perseverança na oração, esperança e cuidado com a família.','/assets/crismaquest/saints/santa-monica.jpg'],
            ['São José','Modelo de fidelidade, trabalho, silêncio e disponibilidade ao projeto de Deus.','/assets/crismaquest/saints/sao-jose-user.jpg?v=20260911j'],
            ['São Vicente de Paulo','Mostra como a fé se torna caridade concreta e serviço aos mais vulneráveis.','/assets/crismaquest/saints/sao-vicente-de-paulo.jpg'],
            ['São Sebastião','Recorda a coragem de permanecer fiel a Cristo mesmo diante das dificuldades.','/assets/crismaquest/saints/sao-sebastiao.jpg'],
        ];

        $extras = [
            ['Santa Joana d’Arc','Padroeira da turma e testemunha de coragem, fidelidade e disponibilidade ao chamado de Deus.','/assets/crismaquest/saints/santa-joana-darc.jpg'],
            ['São Paulo','Apóstolo das nações: conversão, anúncio do Evangelho e perseverança na missão.','/assets/crismaquest/saints/sao-paulo.jpg'],
            ['Santa Clara de Assis','Testemunha de pobreza evangélica, oração e confiança em Cristo.','/assets/crismaquest/saints/santa-clara-assis.jpg'],
            ['Santa Catarina de Sena','Amor à Igreja, busca da verdade e coragem para servir.','/assets/crismaquest/saints/santa-catarina-sena.jpg'],
            ['São João Bosco','Amigo da juventude e educador que uniu fé, alegria e acompanhamento.','/assets/crismaquest/saints/sao-joao-bosco.jpg'],
            ['Santa Teresa de Calcutá','Mostra o amor cristão em gestos concretos de cuidado e serviço.','/assets/crismaquest/saints/santa-teresa-calcuta.jpg'],
            ['Santo Antônio de Pádua','Testemunha da Palavra, proximidade com os pobres e anúncio de Cristo.','/assets/crismaquest/saints/santo-antonio-padua.webp'],
            ['São Domingos Sávio','Jovem santo que viveu amizade com Cristo, alegria e responsabilidade no cotidiano.','/assets/crismaquest/saints/sao-domingos-savio.jpg'],
            ['São Pier Giorgio Frassati','Jovem de oração, amizade, serviço aos pobres e vida vivida sempre para o alto.','/assets/crismaquest/saints/sao-pier-giorgio-frassati.jpg'],
            ['São João Evangelista','Apóstolo e evangelista, testemunha do amor de Cristo e da força da Palavra.','/assets/crismaquest/saints/sao-joao-evangelista.jpg'],
            ['Santo André','Apóstolo que respondeu ao chamado de Jesus e levou outros ao encontro com Cristo.','/assets/crismaquest/saints/santo-andre.jpg'],
        ];

        try {
            $knownNames = [];
            foreach (array_merge($legacy, $extras) as $saint) $knownNames[] = $saint[0];

            $classes = $pdo->query('SELECT id_classe FROM ct_classi WHERE eliminata = 0 ORDER BY id_classe')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $update = $pdo->prepare(
                'UPDATE ct_personaggi
                 SET nome_personaggio=:nome,
                     descrizione=:descricao,
                     immagine=:imagem,
                     img_senza_sfondo=:imagem,
                     color=:cor,
                     bordercolor=:borda
                 WHERE id_personaggio=:id AND fk_classe=:id_classe'
            );

            foreach ($classes as $classIdRaw) {
                $classId = (int)$classIdRaw;
                $stmt = $pdo->prepare(
                    'SELECT id_personaggio, nome_personaggio, originale
                     FROM ct_personaggi
                     WHERE fk_classe=:id_classe
                     ORDER BY id_personaggio'
                );
                $stmt->execute(['id_classe'=>$classId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $extraIds = [];

                foreach ($rows as $row) {
                    $id = (int)$row['id_personaggio'];
                    if ((int)$row['originale'] === 1) {
                        $saint = $legacy[abs($id) % count($legacy)];
                        $update->execute([
                            'nome'=>$saint[0], 'descricao'=>$saint[1], 'imagem'=>$saint[2],
                            'cor'=>'#0d3a4a', 'borda'=>'#c8a55c', 'id'=>$id, 'id_classe'=>$classId,
                        ]);
                        continue;
                    }

                    $name = (string)($row['nome_personaggio'] ?? '');
                    if ($name === 'CrismaQuest Avatar' || in_array($name, $knownNames, true)) {
                        $extraIds[] = $id;
                    }
                }

                while (count($extraIds) < count($extras)) {
                    $pdo->prepare(
                        "INSERT INTO ct_personaggi
                         (uuid,nome_personaggio,immagine,vita_iniziale,descrizione,color,bordercolor,mana_iniziale,fk_classe,img_senza_sfondo,originale)
                         VALUES (UUID(),'CrismaQuest Avatar','',5,'Avatar da Jornada','#0d3a4a','#c8a55c',5,:id_classe,'',0)"
                    )->execute(['id_classe'=>$classId]);
                    $extraIds[] = (int)$pdo->lastInsertId();
                }

                sort($extraIds, SORT_NUMERIC);
                foreach ($extras as $i => $saint) {
                    if (!isset($extraIds[$i])) break;
                    $update->execute([
                        'nome'=>$saint[0], 'descricao'=>$saint[1], 'imagem'=>$saint[2],
                        'cor'=>'#0d3a4a', 'borda'=>'#c8a55c', 'id'=>$extraIds[$i], 'id_classe'=>$classId,
                    ]);
                }
            }
        } catch (Throwable) {
            // Curadoria visual nunca deve impedir o app de abrir.
        }
    }

    private static function isCoreReady(PDO $pdo): bool
    {
        try {
            $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('cq_streaks','cq_streak_events','cq_saint_cards','cq_card_editions','cq_user_cards','cq_meetings','cq_attendance','cq_attendance_audit')");
            if ((int)$q->fetchColumn() !== 8) return false;
            return (int)$pdo->query('SELECT COUNT(*) FROM cq_saint_cards')->fetchColumn() >= 40
                && (int)$pdo->query("SELECT COUNT(*) FROM cq_card_editions WHERE edition_type='normal'")->fetchColumn() >= 40;
        } catch (Throwable) { return false; }
    }

    private static function isSocialReady(PDO $pdo): bool
    {
        try {
            $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('cq_lumen_ledger','cq_gift_catalog','cq_peer_notes','cq_gifts','cq_user_cosmetics','cq_trade_offers')");
            if ((int)$q->fetchColumn() !== 6) return false;
            return (int)$pdo->query('SELECT COUNT(*) FROM cq_gift_catalog WHERE active=1')->fetchColumn() >= 11;
        } catch (Throwable) { return false; }
    }

    private static function importSqlFile(PDO $pdo, string $path): void
    {
        if (!is_readable($path)) throw new RuntimeException('Arquivo SQL do CrismaQuest não encontrado: '.basename($path));
        $sql=file_get_contents($path); if ($sql===false) throw new RuntimeException('Não foi possível ler '.basename($path));
        $sql=preg_replace('/^\s*--.*$/m','',$sql) ?? $sql;
        $sql=preg_replace('/^\s*#.*$/m','',$sql) ?? $sql;
        foreach(explode(';',$sql) as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}
    }
}

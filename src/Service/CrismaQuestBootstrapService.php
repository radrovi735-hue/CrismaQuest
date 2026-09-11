<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Instala, atualiza e saneia as extensões próprias do CrismaQuest de forma idempotente. */
class CrismaQuestBootstrapService
{
    private const LOCK_NAME = 'crismaquest_schema_bootstrap_v6';

    public static function ensureInstalled(): void
    {
        $pdo = Database::getConnection();

        self::sanitizeLegacySeed($pdo);
        self::curateSaintCharacters($pdo);

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
            ['São Carlo Acutis','Jovem testemunha de amor à Eucaristia e de evangelização no mundo digital.','https://commons.wikimedia.org/wiki/Special:FilePath/St._Carlo_Acutis.jpg'],
            ['Santa Teresinha do Menino Jesus','Recorda que a santidade também passa pelas pequenas coisas feitas com grande amor.','https://commons.wikimedia.org/wiki/Special:FilePath/Teresa-de-Lisieux.jpg'],
            ['São Francisco de Assis','Inspira simplicidade, fraternidade, cuidado com a criação e alegria no seguimento de Cristo.','https://commons.wikimedia.org/wiki/Special:FilePath/Francis_of_Assisi_-_Cimabue.jpg'],
            ['São Pedro','Discípulo chamado por Jesus a amadurecer na fé e servir à Igreja com coragem.','https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Peter_A26043.jpg'],
            ['Santa Faustina Kowalska','Testemunha da misericórdia de Deus e do chamado a confiar em Jesus.','https://commons.wikimedia.org/wiki/Special:FilePath/Faustyna_Kowalska.png'],
            ['São João Paulo II','Convidou os jovens a não terem medo de seguir Cristo e assumir sua missão no mundo.','https://commons.wikimedia.org/wiki/Special:FilePath/JohannesPaul2-portrait.jpg'],
            ['Santa Gianna Beretta Molla','Testemunha de vocação, serviço, responsabilidade e amor concreto ao próximo.','https://commons.wikimedia.org/wiki/Special:FilePath/Gianna_Beretta_Molla_(cropped).jpg'],
            ['Santo Agostinho','Sua busca pela verdade recorda que fé, razão e conversão caminham juntas.','https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Augustine_by_Philippe_de_Champaigne.jpg'],
            ['Santa Mônica','Exemplo de perseverança na oração, esperança e cuidado com a família.','https://commons.wikimedia.org/wiki/Special:FilePath/Sainte_Monique.jpg'],
            ['São José','Modelo de fidelidade, trabalho, silêncio e disponibilidade ao projeto de Deus.','https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Joseph_with_the_Infant_Jesus_by_Guido_Reni,_c_1635.jpg'],
            ['São Vicente de Paulo','Mostra como a fé se torna caridade concreta e serviço aos mais vulneráveis.','https://commons.wikimedia.org/wiki/Special:FilePath/Anonymous_-_Portrait_de_saint_Vincent_de_Paul_(1581-1660)._-_P863_-_Musée_Carnavalet.jpg'],
            ['São Sebastião','Recorda a coragem de permanecer fiel a Cristo mesmo diante das dificuldades.','https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Sebastian_painting.jpg'],
        ];

        $extras = [
            ["Santa Joana d'Arc",'Padroeira da turma e testemunha de coragem, fidelidade e disponibilidade ao chamado de Deus.','https://commons.wikimedia.org/wiki/Special:FilePath/John_Everett_Millais_-_Joan_of_Arc.jpg'],
            ['São Paulo','Apóstolo das nações: conversão, anúncio do Evangelho e perseverança na missão.','https://commons.wikimedia.org/wiki/Special:FilePath/Almeida_J%C3%BAnior_-_Ap%C3%B3stolo_S%C3%A3o_Paulo%2C_1869.jpg'],
            ['Santa Clara','Testemunha de pobreza evangélica, oração e confiança em Cristo.','https://commons.wikimedia.org/wiki/Special:FilePath/Santa_Chiara_d%27Assisi_di_Giovan_Battista_Moroni.jpg'],
            ['Santa Catarina de Sena','Amor à Igreja, busca da verdade e coragem para servir.','https://commons.wikimedia.org/wiki/Special:FilePath/Catherine_of_Siena.jpg'],
            ['São João Bosco','Amigo da juventude e educador que uniu fé, alegria e acompanhamento.','https://commons.wikimedia.org/wiki/Special:FilePath/Portrait_de_Don_Bosco.jpg'],
            ['Santa Teresa de Calcutá','Mostra o amor cristão em gestos concretos de cuidado e serviço.','https://commons.wikimedia.org/wiki/Special:FilePath/Mother_Teresa.jpg'],
            ['Santo Antônio','Testemunha da Palavra, proximidade com os pobres e anúncio de Cristo.','https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Anthony_of_Padua_Sano_di_Pietro.webp'],
            ['São Domingos Sávio','Jovem santo que viveu amizade com Cristo, alegria e responsabilidade no cotidiano.','https://commons.wikimedia.org/wiki/Special:FilePath/Life_of_Dominic_Savio_(page_6_crop).jpg'],
            ['Beato Pier Giorgio Frassati','Jovem de oração, amizade, serviço aos pobres e vida vivida sempre para o alto.','https://commons.wikimedia.org/wiki/Special:FilePath/PIER_GIORGIO_FRASSATI1925.jpg'],
            ['São João Evangelista','Apóstolo e evangelista, testemunha do amor de Cristo e da força da Palavra.','https://commons.wikimedia.org/wiki/Special:FilePath/Fran%C3%A7ois_Andr%C3%A9_Vincent_-_Saint_John_the_Evangelist_-_80.6_-_Detroit_Institute_of_Arts.jpg'],
            ['Santo André','Apóstolo que respondeu ao chamado de Jesus e levou outros ao encontro com Cristo.','https://commons.wikimedia.org/wiki/Special:FilePath/Saint_andrew.jpg'],
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

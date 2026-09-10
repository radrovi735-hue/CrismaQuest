<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$result = ['ok' => true, 'checks' => []];

try {
    require __DIR__ . '/../vendor/autoload.php';
    $result['checks']['autoload'] = 'ok';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'stage'=>'autoload','error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

try {
    if (!file_exists(__DIR__ . '/../.env')) {
        throw new RuntimeException('.env ausente');
    }
    Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();
    $result['checks']['env'] = 'ok';
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['checks']['env'] = $e->getMessage();
}

try {
    $pdo = App\Service\Database::getConnection();
    $result['checks']['database'] = 'ok';
    $result['checks']['db_server'] = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

    $tables = ['ct_utenti','ct_studenti','ct_studenti_classi','ct_classi','ct_anni_scolastici'];
    foreach ($tables as $table) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');
        $result['checks']['table_' . $table] = (int)$stmt->fetchColumn();
    }

    $sql = 'SELECT c.id_classe, c.nome_classe, c.colore, c.icona, a.anno_scolastico
            FROM ct_utenti u
            INNER JOIN ct_studenti s ON s.fk_utente = u.id_utente
            INNER JOIN ct_studenti_classi sc ON sc.fk_studente = s.id_studente
            INNER JOIN ct_classi c ON c.id_classe = sc.fk_classe
            INNER JOIN ct_anni_scolastici a ON a.id_anno = c.fk_anno_scolastico
            WHERE 1 = 0';
    $pdo->query($sql);
    $result['checks']['student_class_query_schema'] = 'ok';
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['checks']['database_or_schema'] = get_class($e) . ': ' . $e->getMessage();
}

try {
    $t = new App\Service\TranslationService();
    $result['checks']['translation_service'] = method_exists($t, 'all') ? 'ok' : 'missing all()';
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['checks']['translation_service'] = get_class($e) . ': ' . $e->getMessage();
}

try {
    $s = new App\Service\CrismaQuestSocialService();
    $result['checks']['social_service'] = [
        'unread' => $s->getUnreadCountSafe(),
        'cosmetics' => $s->getEquippedCosmeticClassesSafe(),
    ];
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['checks']['social_service'] = get_class($e) . ': ' . $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

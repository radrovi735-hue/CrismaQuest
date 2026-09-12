<?php

use App\Service\TranslationService;

$translator = new TranslationService();
$viewsPath = __DIR__ . '/..';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentView = (string)($_GET['view'] ?? '');
$renderPagePartial = static function (string $partial, array $partialData = []) use ($viewsPath): void {
    if (str_contains($partial, '..')) { throw new RuntimeException('Percurso inválido.'); }
    $partialPath = $viewsPath . '/' . ltrim($partial, '/') . '.php';
    if (!file_exists($partialPath)) { throw new RuntimeException("Partial não encontrada: {$partialPath}"); }
    extract($partialData, EXTR_SKIP); require $partialPath;
};
$nav = [
    ['/docenti/dashboard','fa-house','Painel'],
    ['/docenti/studenti','fa-users','Turma'],
    ['/docenti/jogo','fa-compass','Missões'],
    ['/docenti/dashboard?view=attendance','fa-calendar-check','Presença'],
    ['/docenti/correio','fa-envelope-open-text','Correio'],
    ['/docenti/badge','fa-award','Conquistas'],
    ['/docenti/profilo','fa-gear','Configurações'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d3a4a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CrismaQuest">
    <link rel="manifest" href="/manifest.webmanifest?v=1">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/crismaquest/pwa/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/crismaquest/pwa/icon-192.png">
    <meta name="author" content="CrismaQuest — Paróquia Nossa Senhora dos Remédios">
    <title>CrismaQuest — Painel do Catequista</title>
    <link href="/assets/bootstrap-5.3.8/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/fontawesome-7.2/css/all.min.css" rel="stylesheet">
    <link href="/css/crismaquest-theme.css" rel="stylesheet">
    <link href="/css/crismaquest-teacher.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.dataTables.min.css">
    <?php if (!empty($pageStyles ?? [])): foreach ($pageStyles as $style): ?><link href="<?= htmlspecialchars($style) ?>" rel="stylesheet"><?php endforeach; endif; ?>
</head>
<body class="cq-teacher-body">
<div class="cq-teacher-shell">
    <aside class="cq-teacher-side">
        <a class="cq-teacher-logo" href="/docenti/dashboard">Crisma<span>Quest</span></a>
        <div class="cq-teacher-parish">Paróquia Nossa Senhora dos Remédios<br>Arquidiocese de Fortaleza</div>
        <nav class="cq-teacher-nav" aria-label="Menu dos catequistas">
            <?php foreach ($nav as [$href,$icon,$label]): ?>
                <?php $base = explode('?', $href)[0]; $active = $currentPath === $base && (($label !== 'Presença') || $currentView === 'attendance'); ?>
                <a href="<?= htmlspecialchars($href) ?>" class="<?= $active ? 'active':'' ?>"><i class="fa-solid <?= htmlspecialchars($icon) ?>"></i><span><?= htmlspecialchars($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="cq-teacher-exit"><a href="/logout" style="color:#dbe3e1;text-decoration:none;font-size:12px"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Sair</a></div>
    </aside>
    <div class="cq-teacher-main">
        <header class="cq-teacher-top">
            <div><strong>Jornada da Crisma</strong><br><small>Formar hoje, testemunhas para amanhã.</small></div>
            <a href="/docenti/profilo" class="cq-secondary-btn" style="min-height:38px;padding:0 13px"><i class="fa-solid fa-user"></i> Catequista</a>
        </header>
        <div class="cq-teacher-content">
            <?php require __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </div>
        <footer class="cq-teacher-footer"><strong>CrismaQuest</strong> · Paróquia Nossa Senhora dos Remédios · Arquidiocese de Fortaleza</footer>
    </div>
</div>

<?php if (!empty($pageModals ?? [])): foreach ($pageModals as $modal):
    $modalView = is_array($modal) ? ($modal['view'] ?? null) : $modal;
    $modalData = is_array($modal) ? ($modal['data'] ?? []) : [];
    if (is_string($modalView) && $modalView !== '') { $renderPagePartial($modalView, is_array($modalData) ? $modalData : []); }
endforeach; endif; ?>

<script src="/assets/jquery/jquery.min.js"></script>
<script src="/assets/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
<script src="/assets/datatables/dataTables.bootstrap4.min.js"></script>
<script>
window.CQ={baseUrl:'/',timezone:'America/Fortaleza',i18n:<?= json_encode($translator->all(), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>};
window.cqT = function(key, fallback) {
    return (window.CQ && window.CQ.i18n && window.CQ.i18n[key]) || fallback || key;
};

// Compatibilidade temporária para módulos herdados do ChronoQuest que ainda usam
// a API jQuery do Bootstrap 4. O CrismaQuest roda Bootstrap 5.
if (window.jQuery && window.bootstrap && window.bootstrap.Modal) {
    window.jQuery.fn.modal = function(action) {
        return this.each(function() {
            var instance = window.bootstrap.Modal.getOrCreateInstance(this);
            if (action === 'hide') instance.hide();
            else if (action === 'toggle') instance.toggle();
            else instance.show();
        });
    };

    document.addEventListener('click', function(event) {
        var trigger = event.target.closest('[data-toggle="modal"][data-target]');
        if (!trigger) return;
        var selector = trigger.getAttribute('data-target');
        var modal = selector ? document.querySelector(selector) : null;
        if (!modal) return;
        event.preventDefault();
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    });
}
</script>
<?php if (!empty($pageScripts ?? [])): foreach ($pageScripts as $script): ?><script src="<?= htmlspecialchars($script) ?>"></script><?php endforeach; endif; ?>
<script src="/js/crismaquest-pwa.js?v=1"></script>\n</body>
</html>
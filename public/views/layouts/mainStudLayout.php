<?php

use App\Service\TranslationService;

$translator = new TranslationService();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [
    ['/studenti/classe/dashboard', 'fa-house', 'Início'],
    ['/studenti/quest', 'fa-compass', 'Missões'],
    ['/studenti/jornada', 'fa-map', 'Jornada'],
    ['/studenti/album', 'fa-images', 'Álbum'],
    ['/studenti/profilo', 'fa-user', 'Perfil'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d3a4a">
    <meta name="author" content="CrismaQuest — Paróquia Nossa Senhora dos Remédios">
    <title>CrismaQuest — Jornada da Crisma</title>
    <link href="/assets/bootstrap-5.3.8/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/fontawesome-7.2/css/all.min.css" rel="stylesheet">
    <link href="/css/crismaquest-theme.css" rel="stylesheet">
    <link href="/css/crismaquest-app.css" rel="stylesheet">
    <?php if (!empty($pageStyles ?? [])): ?>
        <?php foreach ($pageStyles as $style): ?>
            <link href="<?= htmlspecialchars($style) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body id="page-top">
<header class="cq-app-topbar">
    <div class="container-fluid h-100 d-flex align-items-center justify-content-between px-3 px-md-4">
        <a href="/studenti/classe/dashboard" class="cq-brand">Crisma<span class="quest">Quest</span></a>
        <div class="d-none d-sm-block text-center cq-parish">Paróquia Nossa Senhora dos Remédios · Arquidiocese de Fortaleza</div>
        <a class="cq-top-profile" href="/studenti/profilo" aria-label="Abrir perfil"><i class="fa-solid fa-user"></i></a>
    </div>
</header>

<main>
    <?php require __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?>
</main>

<footer class="cq-app-footer">
    <strong>CrismaQuest</strong> · Paróquia Nossa Senhora dos Remédios · Arquidiocese de Fortaleza
</footer>

<nav class="cq-bottom-nav" aria-label="Navegação principal do CrismaQuest">
    <?php foreach ($navItems as [$href, $icon, $label]): ?>
        <?php $active = $currentPath === $href || ($href !== '/studenti/classe/dashboard' && str_starts_with($currentPath, $href . '/')); ?>
        <a href="<?= htmlspecialchars($href) ?>" class="<?= $active ? 'active' : '' ?>">
            <i class="fa-solid <?= htmlspecialchars($icon) ?>"></i>
            <span><?= htmlspecialchars($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<script src="/assets/jquery/jquery.min.js"></script>
<script src="/assets/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
window.CQ = {
    baseUrl: '/',
    timezone: 'America/Fortaleza',
    i18n: <?= json_encode($translator->all(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
};
</script>
<?php if (!empty($pageScripts ?? [])): ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>

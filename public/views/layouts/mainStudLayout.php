<?php

use App\Service\TranslationService;
use App\Service\CrismaQuestSocialService;

$translator = new TranslationService();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentView = (string) ($_GET['view'] ?? 'home');
$socialService = new CrismaQuestSocialService();
$unreadSocial = $socialService->getUnreadCountSafe();
$cosmeticClasses = $socialService->getEquippedCosmeticClassesSafe();
$navItems = [
    ['/studenti/classe/dashboard', 'home', 'fa-house', 'Início'],
    ['/studenti/quest', 'missions', 'fa-compass', 'Missões'],
    ['/studenti/classe/dashboard?view=journey', 'journey', 'fa-map', 'Jornada'],
    ['/studenti/classe/dashboard?view=album', 'album', 'fa-images', 'Álbum'],
    ['/studenti/profilo', 'profile', 'fa-user', 'Perfil'],
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
    <link href="/css/crismaquest-social.css" rel="stylesheet">
    <link href="/css/crismaquest-graphics.css?v=20260910b" rel="stylesheet">
    <?php if (!empty($pageStyles ?? [])): ?>
        <?php foreach ($pageStyles as $style): ?>
            <link href="<?= htmlspecialchars($style) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body id="page-top" class="<?= htmlspecialchars(implode(' ', $cosmeticClasses)) ?>">
<header class="cq-app-topbar">
    <div class="container-fluid h-100 d-flex align-items-center justify-content-between px-3 px-md-4">
        <a href="/studenti/classe/dashboard" class="cq-brand">Crisma<span class="quest">Quest</span></a>
        <div class="d-none d-sm-block text-center cq-parish">Paróquia Nossa Senhora dos Remédios · Arquidiocese de Fortaleza</div>
        <div class="d-flex align-items-center">
            <a class="cq-top-mail" href="/studenti/correio" aria-label="Abrir Correio da Jornada">
                <i class="fa-regular fa-envelope"></i>
                <?php if ($unreadSocial > 0): ?><span class="cq-mail-count"><?= min(99, $unreadSocial) ?></span><?php endif; ?>
            </a>
            <a class="cq-top-profile" href="/studenti/profilo" aria-label="Abrir perfil"><i class="fa-solid fa-user"></i></a>
        </div>
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
    <?php foreach ($navItems as [$href, $key, $icon, $label]): ?>
        <?php
        $active = match ($key) {
            'home' => $currentPath === '/studenti/classe/dashboard' && $currentView === 'home',
            'journey' => $currentPath === '/studenti/classe/dashboard' && $currentView === 'journey',
            'album' => $currentPath === '/studenti/classe/dashboard' && $currentView === 'album',
            'missions' => str_starts_with($currentPath, '/studenti/quest'),
            'profile' => str_starts_with($currentPath, '/studenti/profilo'),
            default => false,
        };
        ?>
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
<script src="/js/crismaquest-saint-avatars.js"></script>
<script src="/js/crismaquest-graphics.js?v=20260910b"></script>
<?php if (!empty($pageScripts ?? [])): ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>

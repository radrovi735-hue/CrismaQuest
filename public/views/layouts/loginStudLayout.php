<?php

use App\Service\TranslationService;
$translator = new TranslationService();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
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
    <title>CrismaQuest — Jornada da Crisma</title>
    <link href="/assets/bootstrap-5.3.8/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/fontawesome-7.2/css/all.min.css" rel="stylesheet">
    <link href="/css/crismaquest-theme.css" rel="stylesheet">
    <?php if (!empty($pageStyles ?? [])): ?>
        <?php foreach ($pageStyles as $style): ?>
            <link href="<?= htmlspecialchars($style) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="cq-auth-body">
    <?= $content ?>
    <script src="/assets/jquery/jquery.min.js"></script>
    <script src="/assets/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="/js/crismaquest-pwa.js?v=1"></script>\n</body>
</html>

<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();
session_start();

$result = ['ok' => true, 'stage' => 'start', 'checks' => []];

try {
    $service = new App\Service\StudentDashboardService();
    $data = $service->getSelectionPageData();
    $result['checks']['selection_data'] = [
        'permissionStatus' => $data['permissionStatus'] ?? null,
        'classes_count' => count($data['classes'] ?? []),
        'selectedClassId' => $data['selectedClassId'] ?? null,
        'session_user_id' => App\Service\Session::get('user')['id'] ?? null,
        'session_class_id' => App\Service\Session::get('class')['id'] ?? null,
    ];
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['stage'] = 'selection_data';
    $result['error'] = [get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()];
    echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $translator = new App\Service\TranslationService();
    $result['checks']['translations'] = [
        'title' => $translator->translate('student.dashboard.title'),
        'subtitle' => $translator->translate('student.dashboard.subtitle'),
        'enter' => $translator->translate('student.classes.enter'),
    ];
    $json = json_encode($translator->all(), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
    $result['checks']['translation_json'] = $json === false ? json_last_error_msg() : 'ok';
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['stage'] = 'translations';
    $result['error'] = [get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()];
    echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    ob_start();
    App\Core\View::render('studenti/dashboard', array_merge($data, [
        'title' => 'student.dashboard.title',
        'disableStudentTopbarData' => true,
        'pageStyles' => ['/css/headers.css','/css/classes.css'],
        'useMathJax' => false,
    ]), 'mainStudLayout');
    $html = ob_get_clean();
    $result['checks']['render'] = ['status' => 'ok', 'bytes' => strlen($html)];
} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $result['ok'] = false;
    $result['stage'] = 'render';
    $result['error'] = [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTraceAsString() ? explode("\n", $e->getTraceAsString()) : [], 0, 8),
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

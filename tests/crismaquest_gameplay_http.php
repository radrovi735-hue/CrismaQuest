<?php

declare(strict_types=1);
// Test-only router: never available in the public deployment or a normal web SAPI.
if (PHP_SAPI !== 'cli' && (PHP_SAPI !== 'cli-server' || getenv('CQ_HTTP_TEST') !== '1')) {
    http_response_code(404); exit;
}
require getenv('CQ_TEST_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) $_ENV[$key] = (string)getenv($key);

use App\Service\Database;

if (PHP_SAPI === 'cli-server') {
    $_SESSION = [];
    $role = $_SERVER['HTTP_X_CQ_TEST_ROLE'] ?? '';
    if (in_array($role,['student','teacher'],true)) {
        $_SESSION['user'] = ['id'=>(int)getenv($role === 'student' ? 'CQ_HTTP_STUDENT' : 'CQ_HTTP_TEACHER')];
        $_SESSION['class'] = ['id'=>1];
        $_SESSION['cq_game_csrf'] = (string)getenv('CQ_HTTP_TOKEN');
    }
    $router = new App\Core\Router();
    require dirname(__DIR__) . '/routes/crismaquest.php';
    $router->dispatch();
    exit;
}

$pdo = Database::getConnection();
$student = (int)$pdo->query("SELECT id_utente FROM ct_utenti WHERE username='cqstress104'")->fetchColumn();
$teacher = (int)$pdo->query('SELECT MIN(fk_utente) FROM ct_utenti_classi WHERE fk_classe=1')->fetchColumn();
if (!$student || !$teacher) throw new RuntimeException('Missing HTTP fixtures.');
putenv('CQ_HTTP_TEST=1'); putenv('CQ_HTTP_STUDENT='.$student); putenv('CQ_HTTP_TEACHER='.$teacher);
$token = bin2hex(random_bytes(24)); putenv('CQ_HTTP_TOKEN='.$token);
$log = tempnam(sys_get_temp_dir(),'cq-http-');
$command = [PHP_BINARY,'-c',php_ini_loaded_file() ?: '', '-S','127.0.0.1:8097',__FILE__];
$server = proc_open($command,[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__));
if (!is_resource($server)) throw new RuntimeException('HTTP server did not start.');
function request(string $path, string $role='', ?array $post=null): array {
    $headers="X-CQ-Test-Role: {$role}\r\n";
    if ($post !== null) $headers.="Content-Type: application/x-www-form-urlencoded\r\n";
    $context=stream_context_create(['http'=>['method'=>$post===null?'GET':'POST','header'=>$headers,'content'=>$post===null?'':http_build_query($post),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]);
    $body=file_get_contents('http://127.0.0.1:8097'.$path,false,$context);
    preg_match('/\s(\d{3})\s/',$http_response_header[0] ?? '',$match);
    return [(int)($match[1] ?? 0),$body ?: ''];
}
function expect(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "OK: {$message}\n";
}
try {
    for ($attempt=0;$attempt<30;$attempt++) {
        $socket=@fsockopen('127.0.0.1',8097,$errno,$error,.1);
        if ($socket) { fclose($socket); break; }
        usleep(100000);
    }
    expect(request('/studenti/missoes')[0]===302,'missões exigem sessão de crismando');
    expect(request('/docenti/jogo/setup/1','student',[])[0]===403,'crismando não instala o jogo');
    expect(request('/docenti/jogo/setup/1','teacher',[])[0]===403,'instalação sem CSRF bloqueada por HTTP');
    foreach (['/docenti/jogo','/docenti/jogo/setup','/docenti/jogo/previa','/docenti/jogo/jornada'] as $path) {
        [$status,$body]=request($path,'teacher');
        expect($status===200 && !str_contains($body,'Fatal error'),"tela {$path} renderiza");
    }
    [$status,$body]=request('/studenti/missoes','student');
    expect($status===200 && str_contains($body,'Missões da Jornada') && str_contains($body,'Centelha'),'tela real do crismando renderiza missões e Centelha');
    $mission=(int)$pdo->query("SELECT id FROM cq_missions WHERE slug='c1e1-palavra'")->fetchColumn();
    expect(request('/studenti/missoes/'.$mission.'/concluir','student',['confirm'=>'1'])[0]===403,'conclusão sem CSRF bloqueada por HTTP');
    $path='/studenti/missoes/'.$mission.'/concluir';
    expect(request($path,'student',['confirm'=>'1','csrf_token'=>$token])[0]===302,'missão concluída pelo formulário HTTP');
    $xp=(int)$pdo->query('SELECT xp FROM ct_studenti WHERE fk_utente='.$student)->fetchColumn();
    expect($xp===10,'formulário concede os 10 XP previstos');
    request($path,'student',['confirm'=>'1','csrf_token'=>$token]);
    expect((int)$pdo->query('SELECT xp FROM ct_studenti WHERE fk_utente='.$student)->fetchColumn()===$xp,'reenviar formulário não duplica XP');
    echo "PASS: telas autenticadas, permissões e conclusão por HTTP\n";
} finally {
    proc_terminate($server); proc_close($server);
    $contents=file_get_contents($log) ?: '';
    if (preg_match('/PHP (Fatal|Warning|Parse)/',$contents)) fwrite(STDERR,$contents);
    unlink($log);
}

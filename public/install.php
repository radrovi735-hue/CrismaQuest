<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root . '/.env';
$sqlPath = $root . '/sql/finali/chronoquest_italiano.sql';
$installed = file_exists($envPath);
$errors = [];
$success = false;

$values = [
    'db_host' => trim((string)($_POST['db_host'] ?? 'sql306.infinityfree.com')),
    'db_name' => trim((string)($_POST['db_name'] ?? '')),
    'db_user' => trim((string)($_POST['db_user'] ?? '')),
    'db_password' => (string)($_POST['db_password'] ?? ''),
    'admin_email' => trim((string)($_POST['admin_email'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $adminPassword = (string)($_POST['admin_password'] ?? '');
    $adminPasswordConfirm = (string)($_POST['admin_password_confirm'] ?? '');

    foreach (['db_host','db_name','db_user','db_password','admin_email'] as $field) {
        if ($values[$field] === '') {
            $errors[] = 'Preencha todos os campos obrigatórios.';
            break;
        }
    }
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido para o administrador.';
    }
    if (strlen($adminPassword) < 8) {
        $errors[] = 'A senha do administrador precisa ter pelo menos 8 caracteres.';
    }
    if ($adminPassword !== $adminPasswordConfirm) {
        $errors[] = 'As duas senhas do administrador não coincidem.';
    }
    if (!is_readable($sqlPath)) {
        $errors[] = 'A base de instalação não foi encontrada.';
    }

    if ($errors === []) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $values['db_host'], $values['db_name']),
                $values['db_user'],
                $values['db_password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // A previous interrupted installation may have created only part of the schema.
            // Because .env does not exist yet, this is still the initial setup. Start clean so
            // retries are deterministic and never fail with "table already exists".
            resetSchema($pdo);

            $sql = file_get_contents($sqlPath);
            if ($sql === false) {
                throw new RuntimeException('Não foi possível ler a base SQL.');
            }
            foreach (splitSql($sql) as $statement) {
                $statement = trim($statement);
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            $stmt = $pdo->prepare('UPDATE ct_utenti SET password=:password,email=:email WHERE username="admin"');
            $stmt->execute([
                'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
                'email' => $values['admin_email'],
            ]);
            if ($stmt->rowCount() < 1) {
                $exists = $pdo->query("SELECT COUNT(*) FROM ct_utenti WHERE username='admin'")->fetchColumn();
                if ((int)$exists < 1) {
                    throw new RuntimeException('O usuário administrador não existe na base importada.');
                }
            }

            $env = implode(PHP_EOL, [
                'DB_HOST=' . envVal($values['db_host']),
                'DB_NAME=' . envVal($values['db_name']),
                'DB_USER=' . envVal($values['db_user']),
                'DB_PASSWORD=' . envVal($values['db_password']),
                '',
                'MAIL_HOST=',
                'MAIL_PORT=587',
                'MAIL_ENCRYPTION=tls',
                'MAIL_USERNAME=',
                'MAIL_PASSWORD=',
                'MAIL_FROM_NAME=' . envVal('CrismaQuest'),
                'MAIL_FROM_ADDRESS=' . envVal($values['admin_email']),
                '',
            ]);

            if (file_put_contents($envPath, $env) === false) {
                throw new RuntimeException('Não foi possível criar o arquivo de configuração.');
            }
            if (!is_dir($root . '/var')) {
                @mkdir($root . '/var', 0775, true);
            }
            @file_put_contents($root . '/var/installed.lock', 'CrismaQuest instalado em ' . date('c') . PHP_EOL);
            $success = true;
            $installed = true;
        } catch (Throwable $e) {
            $errors[] = 'Instalação não concluída: ' . $e->getMessage();
            if (file_exists($envPath)) {
                @unlink($envPath);
            }
        }
    }
}

function resetSchema(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $row) {
            $table = (string)($row[0] ?? '');
            if ($table === '') {
                continue;
            }
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
        }
        $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);
        foreach ($views as $row) {
            $view = (string)($row[0] ?? '');
            if ($view === '') {
                continue;
            }
            $pdo->exec('DROP VIEW IF EXISTS `' . str_replace('`', '``', $view) . '`');
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

function envVal(string $v): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
}

function splitSql(string $sql): array
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $out = [];
    $buf = '';
    $quote = null;
    $line = false;
    $block = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $n = $i + 1 < $len ? $sql[$i + 1] : '';
        if ($line) {
            if ($c === "\n") {
                $line = false;
                $buf .= "\n";
            }
            continue;
        }
        if ($block) {
            if ($c === '*' && $n === '/') {
                $block = false;
                $i++;
            }
            continue;
        }
        if ($quote !== null) {
            $buf .= $c;
            if ($c === '\\' && $n !== '') {
                $buf .= $n;
                $i++;
                continue;
            }
            if ($c === $quote && $n === $quote) {
                $buf .= $n;
                $i++;
                continue;
            }
            if ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '#') {
            $line = true;
            continue;
        }
        if ($c === '-' && $n === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
            $line = true;
            $i++;
            continue;
        }
        if ($c === '/' && $n === '*') {
            $block = true;
            $i++;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
            $buf .= $c;
            continue;
        }
        if ($c === ';') {
            $out[] = $buf;
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') {
        $out[] = $buf;
    }
    return $out;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalar CrismaQuest</title>
<style>
:root{--b:#6f1d2a;--a:#0d3a4a;--g:#c8a55c;--m:#f6f0e4;--ink:#1d2630}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 85% 0,#edd698 0,transparent 25%),#efe6d8;color:var(--ink);font-family:Arial,sans-serif}.wrap{max-width:760px;margin:0 auto;padding:34px 16px}.hero{background:linear-gradient(135deg,#082b39,#15566a);color:#fff8eb;border-radius:26px;padding:26px;box-shadow:0 18px 45px rgba(9,45,58,.18);margin-bottom:16px}.brand{font:700 34px Georgia,serif}.brand span{color:#ead39a}.hero p{color:#e5dbc5;margin:7px 0 0}.card{background:#fffaf1;border:1px solid #ddcfbb;border-radius:22px;padding:22px;box-shadow:0 10px 30px rgba(66,49,29,.08)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field{display:grid;gap:5px;margin-bottom:12px}.field.full{grid-column:1/-1}label{font-weight:700;font-size:12px}input{width:100%;padding:12px;border:1px solid #d8c8b2;border-radius:12px;background:white;font-size:14px}.hint{font-size:11px;color:#777}.btn{border:0;border-radius:13px;background:linear-gradient(#8d2b36,#6f1d2a);color:white;font-weight:700;padding:13px 18px;cursor:pointer;width:100%}.alert{border-radius:13px;padding:12px 14px;margin-bottom:12px}.err{background:#f7e1e1;color:#79242f}.ok{background:#dfecdf;color:#2f6d50}.parish{text-align:center;color:#71695f;font-size:11px;margin-top:15px}@media(max-width:640px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.hero{padding:21px}.brand{font-size:29px}}
</style>
</head>
<body>
<div class="wrap">
<div class="hero"><div class="brand">Crisma<span>Quest</span></div><p>Uma jornada de participação, descoberta e comunidade.</p></div>
<div class="card">
<?php if ($installed && !$success): ?>
<div class="alert ok"><strong>CrismaQuest já está instalado.</strong><br><a href="/loginStud">Abrir login dos crismandos</a> · <a href="/loginDoc">Abrir painel do catequista</a></div>
<?php elseif ($success): ?>
<div class="alert ok"><strong>Instalação concluída.</strong> O banco e o administrador foram configurados.</div>
<a class="btn" style="display:block;text-align:center;text-decoration:none" href="/loginDoc">Entrar no CrismaQuest</a>
<?php else: ?>
<h1 style="font:700 27px Georgia,serif;margin-top:0">Configuração inicial</h1>
<p class="hint">Use os dados mostrados em “MySQL Databases” no InfinityFree. Não é necessário configurar e-mail/SMTP para começar. Se uma tentativa anterior foi interrompida, o instalador limpa somente este banco e recomeça automaticamente.</p>
<?php foreach ($errors as $e): ?><div class="alert err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
<form method="post">
<div class="grid">
<div class="field"><label>Host MySQL</label><input name="db_host" required value="<?= htmlspecialchars($values['db_host'], ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="field"><label>Nome do banco</label><input name="db_name" required value="<?= htmlspecialchars($values['db_name'], ENT_QUOTES, 'UTF-8') ?>" placeholder="if0_..._crismaquest"></div>
<div class="field"><label>Usuário MySQL</label><input name="db_user" required value="<?= htmlspecialchars($values['db_user'], ENT_QUOTES, 'UTF-8') ?>" placeholder="if0_..."></div>
<div class="field"><label>Senha MySQL</label><input name="db_password" type="password" required value=""></div>
<div class="field full"><label>E-mail do administrador</label><input name="admin_email" type="email" required value="<?= htmlspecialchars($values['admin_email'], ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="field"><label>Senha do administrador</label><input name="admin_password" type="password" required minlength="8"></div>
<div class="field"><label>Repita a senha</label><input name="admin_password_confirm" type="password" required minlength="8"></div>
</div>
<button class="btn" type="submit">Instalar CrismaQuest</button>
</form>
<?php endif; ?>
</div>
<div class="parish">Paróquia Nossa Senhora dos Remédios · Arquidiocese de Fortaleza</div>
</div>
</body>
</html>

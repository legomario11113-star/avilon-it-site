<?php
/*
 * Avilon.IT — приём заявок с формы сайта (#request-form).
 *
 * Принимает POST с JSON { first_name, last_name, email, phone, message, website },
 * сохраняет заявку в SQLite (вне public_html) и шлёт письмо через SMTP.
 * Отвечает { ok: true } / { ok: true, warning } / { ok: false, error }.
 *
 * Настройки (почта, SMTP-пароль) — в api/config.php рядом с этим файлом.
 * Шаблон — api/config.example.php. config.php в git не попадает.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const RATE_LIMIT_COUNT = 5;    // не больше 5 заявок...
const RATE_LIMIT_MINUTES = 10; // ...за 10 минут с одного IP

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'Метод не поддерживается.']);
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    error_log('Avilon.IT: нет api/config.php — скопируйте config.example.php и заполните');
    respond(500, ['ok' => false, 'error' => 'Сервис временно недоступен.']);
}
$config = require $configFile;

/* ---------- Чтение и проверка данных ---------- */

$raw = (string) file_get_contents('php://input');
$body = strlen($raw) <= 20000 ? json_decode($raw, true) : null;
if (!is_array($body)) {
    respond(400, ['ok' => false, 'error' => 'Некорректный запрос.']);
}

function field(array $body, string $name): string
{
    $value = $body[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

$first_name = field($body, 'first_name');
$last_name  = field($body, 'last_name');
$email      = field($body, 'email');
$phone      = field($body, 'phone');
$message    = field($body, 'message');
$website    = field($body, 'website'); // honeypot

// Honeypot: скрытое от людей поле. Бот его заполнил — тихо отвечаем
// успехом, ничего не сохраняем и не отправляем.
if ($website !== '') {
    respond(200, ['ok' => true]);
}

if ($first_name === '' || $last_name === '' || $email === '') {
    respond(400, ['ok' => false, 'error' => 'Заполните имя, фамилию и email.']);
}

$namePattern = '/^[\p{L}\- ]{2,50}$/u';
if (!preg_match($namePattern, $first_name) || !preg_match($namePattern, $last_name)) {
    respond(400, ['ok' => false, 'error' => 'Имя и фамилия: только буквы, от 2 до 50 символов.']);
}

// Тот же простой шаблон, что и в браузере; \s заодно исключает переводы строк,
// поэтому email безопасно подставлять в заголовок Reply-To.
if (mb_strlen($email) > 100 || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/u', $email)) {
    respond(400, ['ok' => false, 'error' => 'Укажите корректный email.']);
}

if (mb_strlen($phone) > 30 || preg_match('/[^0-9+()\-\s]/', $phone)) {
    respond(400, ['ok' => false, 'error' => 'Укажите корректный телефон.']);
}

if (mb_strlen($message) > 1000) {
    respond(400, ['ok' => false, 'error' => 'Комментарий слишком длинный.']);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

/* ---------- Сохранение в SQLite ---------- */

function open_db(string $path): PDO
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Не удалось создать папку $dir");
    }
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec("
        CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            message TEXT,
            ip TEXT
        )
    ");
    return $db;
}

$db = null;
$saved = false;
try {
    $db = open_db($config['db_path']);

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM submissions WHERE ip = ? AND created_at > datetime('now', ?)"
    );
    $stmt->execute([$ip, '-' . RATE_LIMIT_MINUTES . ' minutes']);
    if ((int) $stmt->fetchColumn() >= RATE_LIMIT_COUNT) {
        respond(429, ['ok' => false, 'error' => 'Слишком много заявок с вашего IP. Попробуйте позже.']);
    }

    $db->prepare(
        'INSERT INTO submissions (first_name, last_name, email, phone, message, ip) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$first_name, $last_name, $email, $phone, $message, $ip]);
    $saved = true;
} catch (Throwable $e) {
    error_log('Avilon.IT: ошибка сохранения заявки в БД: ' . $e->getMessage());
}

/* ---------- Письмо ---------- */

function mime_header(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function build_message(array $config, string $replyTo, string $subject, string $text): string
{
    $fromDomain = substr(strrchr($config['from_email'], '@') ?: '@localhost', 1);
    $headers = [
        'Date: ' . date('r'),
        'From: ' . mime_header($config['from_name']) . ' <' . $config['from_email'] . '>',
        'To: <' . $config['to_email'] . '>',
        'Reply-To: <' . $replyTo . '>',
        'Subject: ' . mime_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $fromDomain . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    return implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($text)));
}

/** Минимальный SMTP-клиент: SSL (465) или STARTTLS (587), AUTH LOGIN. */
function smtp_send(array $config, string $rawMessage): void
{
    $port = (int) $config['smtp_port'];
    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $config['smtp_host'] . ':' . $port;
    $sock = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$sock) {
        throw new RuntimeException("SMTP: не удалось подключиться к $remote: $errstr");
    }
    stream_set_timeout($sock, 15);

    $read = function () use ($sock): string {
        $reply = '';
        while (($line = fgets($sock, 515)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $reply;
    };
    $cmd = function (?string $command, array $okCodes) use ($sock, $read): string {
        if ($command !== null) fwrite($sock, $command . "\r\n");
        $reply = $read();
        if (!in_array((int) substr($reply, 0, 3), $okCodes, true)) {
            $shown = preg_match('/^AUTH|^[A-Za-z0-9+\/=]+$/', (string) $command) ? '(auth)' : $command;
            throw new RuntimeException("SMTP: на «{$shown}» сервер ответил: " . trim($reply));
        }
        return $reply;
    };

    try {
        $helo = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $cmd(null, [220]);
        $cmd("EHLO $helo", [250]);
        if ($port !== 465) {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP: не удалось включить TLS');
            }
            $cmd("EHLO $helo", [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($config['smtp_user']), [334]);
        $cmd(base64_encode($config['smtp_pass']), [235]);
        $cmd('MAIL FROM:<' . $config['from_email'] . '>', [250]);
        $cmd('RCPT TO:<' . $config['to_email'] . '>', [250, 251]);
        $cmd('DATA', [354]);
        // Точка в начале строки — признак конца письма, её нужно удвоить.
        $data = preg_replace('/^\./m', '..', $rawMessage);
        $cmd($data . "\r\n.", [250]);
        fwrite($sock, "QUIT\r\n");
    } finally {
        fclose($sock);
    }
}

$text = implode("\n", [
    "Имя: $first_name",
    "Фамилия: $last_name",
    "Email: $email",
    'Телефон: ' . ($phone !== '' ? $phone : '—'),
    'Комментарий: ' . ($message !== '' ? $message : '—'),
    'IP: ' . ($ip !== '' ? $ip : '—'),
]);

$sent = false;
try {
    smtp_send($config, build_message($config, $email, 'Новая заявка с сайта', $text));
    $sent = true;
} catch (Throwable $e) {
    error_log('Avilon.IT: ошибка отправки письма: ' . $e->getMessage());
}

/* ---------- Ответ ---------- */

if ($saved && $sent) {
    respond(200, ['ok' => true]);
}
if ($saved || $sent) {
    // Заявка не потерялась — не отвечаем ошибкой, чтобы человек не отправлял её повторно.
    respond(200, ['ok' => true, 'warning' => $saved ? 'Заявка сохранена, но письмо не отправлено.' : 'Письмо отправлено, но заявка не сохранена в базу.']);
}
respond(500, ['ok' => false, 'error' => 'Не удалось отправить заявку. Попробуйте позже.']);

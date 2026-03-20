<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'contact-send.php ist erreichbar. Bitte nur per POST vom Formular verwenden.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo 'Method Not Allowed';
    exit;
}

$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsJson = stripos($acceptHeader, 'application/json') !== false;

$respond = static function (int $statusCode, bool $success, string $message, array $extra = []) use ($wantsJson): void {
    http_response_code($statusCode);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
};

$honey = trim((string)($_POST['_honey'] ?? ''));
if ($honey !== '') {
    $respond(200, true, 'ok');
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$subjectRaw = trim((string)($_POST['_subject'] ?? 'Neue Nachricht über arnovoyer.com'));
$source = trim((string)($_POST['source'] ?? 'unknown'));

if ($name === '' || $email === '' || $message === '') {
    $respond(422, false, 'Bitte alle Felder ausfuellen.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respond(422, false, 'Bitte eine gueltige E-Mail-Adresse eingeben.');
}

if (mb_strlen($message) < 3) {
    $respond(422, false, 'Nachricht ist zu kurz.');
}

$to = 'arno.voyer@aon.at';
$safeSubject = preg_replace('/[\r\n]+/', ' ', $subjectRaw);
$subject = $safeSubject !== null && $safeSubject !== '' ? $safeSubject : 'Neue Nachricht über arnovoyer.com';

$bodyLines = [
    "Name: {$name}",
    "E-Mail: {$email}",
    "Quelle: {$source}",
    "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    '',
    'Nachricht:',
    $message,
];
$body = implode("\n", $bodyLines);

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ArnoVoyer Kontakt <no-reply@arnovoyer.com>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . phpversion(),
];

$mailSent = @mail($to, $subject, $body, implode("\r\n", $headers));

if (!$mailSent) {
    $respond(500, false, 'Server konnte die E-Mail nicht versenden.');
}

$respond(200, true, 'Nachricht gesendet. Danke.');

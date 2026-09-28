<?php
/* --------------------------------------------------------------
   AKTUELL wird formsubmit.co verwendet (siehe index.html Formular).
   Diese Datei ist daher inaktiv → Redirect zurück zur Kontakt-Sektion.
   
   Soll PHP-Mail später wieder aktiviert werden (benötigt Forwarder
   noreply@arnovoyer.com → arno.voyer@aon.at im Spacemail Panel):
   - Kommentar unten entfernen
   - In index.html action="./kontakt.php" setzen
   - Hidden <input _captcha/_template/_next> aus index.html raus
   -------------------------------------------------------------- */

$host  = $_SERVER['HTTP_HOST'] ?? '';
$uri   = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
$extra = 'index.html#contact';
header('Location: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $host . $uri . '/' . $extra, true, 302);
exit;

/* ================= ALTE VERSION (PHPMail / SMTP) – zum Reaktivieren einfach den header/exit oben löschen:

$MAIL_TO           = 'arno.voyer@aon.at';
$MAIL_FROM         = 'noreply@arnovoyer.com';
$MAIL_FROM_NAME    = 'arnovoyer.com Kontaktformular';
$MAIL_SUBJECT      = 'Neue Nachricht von arnovoyer.com';
$TEST_PASSWORD     = 'test-mail-2026';
$MAIL_DRIVER       = 'mail';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $allowed = $scheme . '://' . $host;
    if (stripos($_SERVER['HTTP_ORIGIN'], $allowed) === 0) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    }
}

function out($ok, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $pw = (string)($_GET['pw'] ?? $_POST['pw'] ?? '');
    if (!hash_equals($TEST_PASSWORD, $pw)) {
        http_response_code(403);
        out(false, ['error' => 'Diagnose passwortgeschützt. ?pw=' . $TEST_PASSWORD . ' anhängen.']);
    }
    $info = [
        'driver'       => $MAIL_DRIVER,
        'mail_to'      => $MAIL_TO,
        'mail_from'    => $MAIL_FROM,
        'mail_subject' => $MAIL_SUBJECT,
        'php_version'  => PHP_VERSION,
        'sendmail_path'=> ini_get('sendmail_path'),
        'smtp_ini'     => ini_get('SMTP'),
        'smtp_port_ini'=> ini_get('smtp_port'),
        'server_name'  => $_SERVER['SERVER_NAME'] ?? null,
        'document_root'=> $_SERVER['DOCUMENT_ROOT'] ?? null,
    ];
    $ok = @mail($MAIL_TO,
        '=?UTF-8?B?' . base64_encode('[TEST] arnovoyer.com kontakt.php ' . date('H:i')) . '?=',
        "Test-Mail von kontakt.php.\n\nZeit: " . date('c') . "\nDriver: $MAIL_DRIVER\nFrom: $MAIL_FROM\n",
        "From: =?UTF-8?B?" . base64_encode($MAIL_FROM_NAME) . "?= <$MAIL_FROM>\r\n" .
        "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n",
        "-f$MAIL_FROM"
    );
    $info['test_send'] = $ok;
    out($ok, ['info' => $info]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    out(false, ['error' => 'Methode nicht erlaubt.']);
}

if (!empty($_POST['_honey'])) {
    http_response_code(400);
    out(false, ['error' => 'Spamverdacht.']);
}

$name    = trim((string)($_POST['name']    ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$errors = [];
if (mb_strlen($name)    < 2)   $errors[] = 'Bitte gib deinen Namen an.';
if (mb_strlen($message) < 10)  $errors[] = 'Deine Nachricht ist zu kurz (min. 10 Zeichen).';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
if ($errors) { http_response_code(400); out(false, ['error' => implode(' ', $errors)]); }

if (mb_strlen($name) > 120)    $name    = mb_substr($name,    0, 120);
if (mb_strlen($email) > 200)   $email   = mb_substr($email,   0, 200);
if (mb_strlen($message) > 8000) $message = mb_substr($message, 0, 8000);

if (!empty($_POST['_subject']) && is_string($_POST['_subject'])) {
    $custom = trim($_POST['_subject']);
    if (mb_strlen($custom) >= 3 && mb_strlen($custom) <= 200) $MAIL_SUBJECT = $custom;
}

$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$date = date('d.m.Y H:i:s');

$plain = "Neue Nachricht über das Kontaktformular\n"
       . "Name:   $name\nE-Mail: $email\nDatum:  $date\nIP:     $ip\n\n$message\n";

$html = "<div style=\"font-family:Arial,sans-serif;font-size:14px;line-height:1.55\">"
      . "<h2 style=\"margin:0 0 12px\">Neue Nachricht von arnovoyer.com</h2>"
      . "<table cellpadding=\"6\" cellspacing=\"0\"><tr><td style=\"width:120px;font-weight:bold\">Name:</td><td>" . htmlspecialchars($name)  . "</td></tr>"
      . "<tr><td style=\"font-weight:bold\">E-Mail:</td><td>" . htmlspecialchars($email) . "</td></tr>"
      . "<tr><td style=\"font-weight:bold\">Datum:</td><td>" . htmlspecialchars($date)  . "</td></tr></table>"
      . "<div style=\"margin-top:14px;padding:12px;background:#F8F8F5;border-left:4px solid #FF5A36\">" . nl2br(htmlspecialchars($message)) . "</div></div>";

$boundary = '=_Boundary_' . md5(uniqid((string)mt_rand(), true));
$body  = "--" . $boundary . "\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $plain . "\r\n\r\n";
$body .= "--" . $boundary . "\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n\r\n";
$body .= "--" . $boundary . "--";

$fromEnc = '=?UTF-8?B?' . base64_encode($MAIL_FROM_NAME) . "?= <$MAIL_FROM>";
$subjEnc = '=?UTF-8?B?' . base64_encode($MAIL_SUBJECT) . '?=';
$headers = "From: $fromEnc\r\nReply-To: $email\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

$sent = @mail($MAIL_TO, $subjEnc, $body, rtrim($headers, "\r\n"), "-f$MAIL_FROM");
if (!$sent) {
    http_response_code(500);
    out(false, [
        'error' => 'Mail-Versand fehlgeschlagen.',
        'fallback' => 'mailto:' . $MAIL_TO . '?subject=' . rawurlencode($MAIL_SUBJECT) . '&body=' . rawurlencode("Name: $name\nE-Mail: $email\n\n$message\n"),
    ]);
}
out(true, ['to' => $MAIL_TO, 'via' => $MAIL_DRIVER]);

// ================= ENDE ALTE VERSION */

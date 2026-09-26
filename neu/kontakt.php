<?php
/* ================================================================
   KONTAKT-FORMULAR: VERSAND PER PHP mail() ODER SMTP (ohne Extrakosten!)
   ----------------------------------------------------------------
   Standard = SMTP via A1 / Spacemail, mit deiner vorhandenen
   arno.voyer@aon.at Mailbox + Passwort. KEIN neues Postfach nötig!

   SMTP klappt auf deinem Server nicht? Einfach $MAIL_DRIVER = 'mail'
   stellen → nutzt wieder die lokale mail() Funktion.
   ================================================================ */

// --- EMPFÄNGER (Deine echte Mailbox) ---
$MAIL_TO           = 'arno.voyer@aon.at';

// --- ABSENDER (für den Betreff-Zeile "From:") --------------------
//     Wenn SMTP mit A1 verwendet wird, MUSS dies hier zwingend auch
//     arno.voyer@aon.at sein. Kein Problem – geht ohne Extrakosten!
$MAIL_FROM         = 'arno.voyer@aon.at';

// --- ANZEIGE NAME im Betreff / E-Mail Client (optional) ---------
$MAIL_FROM_NAME    = 'arnovoyer.com Kontaktformular';

// --- Betreff-Zeile -----------------------------------------------
$MAIL_SUBJECT      = 'Neue Nachricht von arnovoyer.com';

// --- Passwort für Diagnose (GET-Parameter ?pw=...) --------------
$TEST_PASSWORD     = 'test-mail-2026';

// --- TREIBER: 'smtp' = A1 SMTP (empfohlen!), 'mail' = altes mail() ---
$MAIL_DRIVER       = 'smtp';

// ==================================================================
// SMTP-EINSTELLUNGEN FÜR A1 / SPACEMAIL
// (Benutze dein normales A1 / Spacemail Passwort – genau wie Thunderbird)
// ==================================================================
$SMTP = [
    'host'     => 'smtp.aon.at',   // Standard A1 / Spacemail Server
    'port'     => 587,              // STARTTLS (Alternativen: 465 = SSL, 25)
    'security' => 'tls',            // 'tls' | 'ssl' | 'none'
    'username' => 'arno.voyer@aon.at',
    'password' => 'Voi4Vod2',      // <-- DEIN A1 / SPACEMAIL PASSWORT HIER EINTRAGEN!
    'debug'    => false,            // true = SMTP-Chat im Diagnose-Output
];

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

/* ----------------------------------------------------------------
   HILFSFUNKTION: roher SMTP-Versand via fsockopen + STARTTLS
   Braucht KEINE Bibliotheken (kein PHPMailer, keine Composer).
   ---------------------------------------------------------------- */
function smtp_send($cfg, $from, $fromName, $to, $subject, $body, $boundary, $headersClean, &$debugLog = null) {
    $host = $cfg['host'];
    $port = (int)$cfg['port'];
    $secure = $cfg['security'];
    $user = $cfg['username'];
    $pass = $cfg['password'];

    $errno = 0; $errstr = '';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ]
    ]);

    $transport = 'tcp://';
    if ($secure === 'ssl') { $transport = 'ssl://'; }

    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        $debugLog[] = "stream_socket_client fehlgeschlagen ($transport$host:$port) err=$errno: $errstr";
        return false;
    }
    stream_set_timeout($fp, 25);

    function smtp_cmd($fp, $cmd, $expectedCode = null, &$debugLog) {
        if ($cmd !== null) {
            fputs($fp, $cmd . "\r\n");
            $debugLog[] = "S: $cmd";
        }
        $resp = '';
        $startTime = microtime(true);
        while (!feof($fp)) {
            $line = fgets($fp, 512);
            if ($line === false) break;
            $resp .= $line;
            $debugLog[] = "R: " . trim($line);
            if (isset($line[3]) && $line[3] === ' ') break;
            if (microtime(true) - $startTime > 20) break;
        }
        if ($expectedCode !== null) {
            return (int)substr(ltrim($resp), 0, 3) === (int)$expectedCode;
        }
        return $resp;
    }

    $dbg = [];
    $debugLog = &$dbg;

    // 220 Welcome
    $r = smtp_cmd($fp, null, 220, $dbg);
    if (!$r) { fclose($fp); return false; }

    // EHLO
    $domain = explode('@', $from)[1] ?? 'localhost';
    smtp_cmd($fp, "EHLO " . $domain, 250, $dbg);

    // STARTTLS wenn nötig (Port 587)
    if ($secure === 'tls') {
        smtp_cmd($fp, "STARTTLS", 220, $dbg);
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!stream_socket_enable_crypto($fp, true, $crypto)) {
            $dbg[] = "STARTTLS stream_socket_enable_crypto FEHLGESCHLAGEN.";
            fclose($fp);
            return false;
        }
        smtp_cmd($fp, "EHLO " . $domain, 250, $dbg);
    }

    // AUTH LOGIN
    smtp_cmd($fp, "AUTH LOGIN", 334, $dbg);
    smtp_cmd($fp, base64_encode($user), 334, $dbg);
    smtp_cmd($fp, base64_encode($pass), 235, $dbg);

    // MAIL FROM / RCPT TO / DATA
    smtp_cmd($fp, "MAIL FROM:<" . $from . ">", 250, $dbg);
    smtp_cmd($fp, "RCPT TO:<" . $to . ">", 250, $dbg);
    smtp_cmd($fp, "DATA", 354, $dbg);

    // Header + Body aufbauen (gemäß RFC: auf max 998 Zeichen Breite aufteilen)
    $date = date('r');
    $fromEncoded = ($fromName ? ('=?UTF-8?B?' . base64_encode($fromName) . "?= <$from>") : "<$from>");
    $subjEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $head  = "Date: $date\r\n";
    $head .= "From: $fromEncoded\r\n";
    $head .= "To: <$to>\r\n";
    $head .= "Subject: $subjEncoded\r\n";
    $head .= "MIME-Version: 1.0\r\n";
    $head .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $head .= "X-Mailer: arnovoyer.com custom-SMTP\r\n";
    foreach ($headersClean as $k => $v) { $head .= "$k: $v\r\n"; }
    $head .= "\r\n";

    $mailData = $head . $body . "\r\n.\r\n";

    // Dot-stuffing
    $fixed = '';
    foreach (explode("\n", str_replace("\r\n", "\n", $mailData)) as $line) {
        $line = rtrim($line, "\r") . "\r\n";
        if (strncmp($line, '.', 1) === 0) $line = '.' . $line;
        $fixed .= $line;
    }
    fputs($fp, $fixed);

    $ok = smtp_cmd($fp, null, 250, $dbg);
    smtp_cmd($fp, "QUIT", 221, $dbg);
    fclose($fp);
    return $ok;
}

/* ----------------------------------------------------------------
   EIGENTLICHER VERSAND-HELFER: je nach Treiber
   ---------------------------------------------------------------- */
function versende($cfg, $driver, $from, $fromName, $to, $subj, $plain, $html, &$diagnose = null) {
    $boundary = '=_Boundary_' . md5(uniqid((string)mt_rand(), true));
    $mailBody  = "--" . $boundary . "\r\n";
    $mailBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mailBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mailBody .= $plain . "\r\n\r\n";
    $mailBody .= "--" . $boundary . "\r\n";
    $mailBody .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mailBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mailBody .= $html . "\r\n\r\n";
    $mailBody .= "--" . $boundary . "--";

    if ($driver === 'smtp') {
        // SMTP (keine zusätzlichen header in $head übergeben, weil smtp_send die eh selber baut)
        $debug = null;
        $ok = smtp_send($cfg, $from, $fromName, $to, $subj, $mailBody, $boundary, $headExtra = [], $debug);
        $diagnose = [
            'driver' => 'smtp',
            'host'   => $cfg['host'] . ':' . $cfg['port'],
            'secure' => $cfg['security'],
            'debug'  => $cfg['debug'] ? $debug : null,
        ];
        return $ok;
    }

    // Fallback: mail()
    $subjClean = str_replace(["\r","\n"], [' ',' '], $subj);
    $subjEnc = '=?UTF-8?B?' . base64_encode($subjClean) . '?=';

    $fromEnc = ($fromName ? ('=?UTF-8?B?' . base64_encode($fromName) . "?= <$from>") : "<$from>");

    $headers  = [];
    $headers['MIME-Version']  = '1.0';
    $headers['Content-Type']  = 'multipart/alternative; boundary="' . $boundary . '"';
    $headers['From']          = $fromEnc;
    $headers['Reply-To']      = $from;
    $headers['X-Mailer']      = 'PHP/' . phpversion();

    $params = '';
    // Envelope-Sender forcieren (-f) – verhindert bei manchen Hostern "From rejected"
    if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $params = "-f$from";
    }
    $headerStr = '';
    foreach ($headers as $k => $v) $headerStr .= "$k: $v\r\n";

    $ok = @mail($to, $subjEnc, $mailBody, rtrim($headerStr, "\r\n"), $params);
    $diagnose = [
        'driver' => 'mail()',
        'params' => $params,
        'from'   => $from,
        'to'     => $to,
    ];
    return $ok;
}

/* ----------------------------------------------------------------
   DIAGNOSE (GET)
---------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $pw = (string)($_GET['pw'] ?? $_POST['pw'] ?? '');
    if (!hash_equals($TEST_PASSWORD, $pw)) {
        http_response_code(403);
        out(false, [
            'error' => 'Diagnose passwortgeschützt. ?pw=' . $TEST_PASSWORD . ' anhängen.',
            'tip'   => 'Beispiel: kontakt.php?pw=' . urlencode($TEST_PASSWORD)
        ]);
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

    // Prüfe Obiger SMTP-Server erreichbar?
    $smtpReachable = false;
    $smtpErr = '';
    if ($MAIL_DRIVER === 'smtp') {
        $host = $SMTP['host'];
        $port = $SMTP['port'];
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $transport = $SMTP['security'] === 'ssl' ? 'ssl://' : 'tcp://';
        $fp = @stream_socket_client($transport . $host . ':' . $port, $e1, $e2, 10, STREAM_CLIENT_CONNECT, $context);
        if ($fp) {
            $smtpReachable = true;
            $greet = @fgets($fp, 512);
            fclose($fp);
            $info['smtp_greeting'] = trim($greet);
        } else {
            $smtpErr = "$e1 / $e2";
        }
        $info['smtp_reachable'] = $smtpReachable;
        $info['smtp_hostport']  = "$host:$port";
        if (!$smtpReachable) $info['smtp_error'] = $smtpErr;
        $info['smtp_username_is_set'] = !empty($SMTP['username']) && $SMTP['password'] !== 'CHANGE-ME';
    }

    // Test-Mail verschicken? NUR wenn SMTP erreichbar / mail() driver
    $testOk = null; $diag = null;
    $testSubj = '[TEST] arnovoyer.com ' . strtoupper($MAIL_DRIVER) . ' Diagnose ' . date('H:i');
    $testBody = "Das ist eine Test-Mail.\n\nPHP: " . PHP_VERSION . "\nServer: " . ($_SERVER['SERVER_SOFTWARE'] ?? '?') . "\n";
    $testHtml = "<i>Test-Mail vom Kontaktformular-Skript.</i><br><br>"
              . "<b>PHP:</b> " . PHP_VERSION . "<br>"
              . "<b>Treiber:</b> " . htmlspecialchars($MAIL_DRIVER);

    $testOk = versende($SMTP, $MAIL_DRIVER, $MAIL_FROM, $MAIL_FROM_NAME, $MAIL_TO, $testSubj, $testBody, $testHtml, $diag);
    $info['test_send'] = $testOk;
    $info['test_diag'] = $diag;

    out($testOk, [
        'info' => $info,
        'tip_password'  => $info['smtp_username_is_set'] ?? false ? '' : '⚠️ SMTP-PASSWORT ist noch auf CHANGE-ME gesetzt! Bitte in kontakt.php SMTP["password"] eintragen!',
        'tip_smtp_reachable' => $MAIL_DRIVER === 'smtp' ? ($smtpReachable ? '✅ SMTP Host erreichbar.' : '❌ SMTP Host NICHT erreichbar → prüfe Firewall / anderes SMTP / Port (465 / 25)') : '',
        'tip_result' => $testOk
            ? '✅ Test-Mail wurde versandt! Kontrolliere in 1-2 Minuten deinen Posteingang + SPAM-Ordner bei ' . $MAIL_TO . '.'
            : '❌ Versand fehlgeschlagen. Oben unter info→test_diag debug-Output einsehen.'
    ]);
}

/* ----------------------------------------------------------------
   POST: Eigentlicher Versand aus dem Formular
---------------------------------------------------------------- */
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
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
$date = date('d.m.Y H:i:s');

$plain = "Neue Nachricht über das Kontaktformular\n"
       . "=======================================\n\n"
       . "Name:    $name\n"
       . "E-Mail:  $email\n"
       . "Datum:   $date\n"
       . "IP:      $ip\n"
       . "UA:      $ua\n"
       . "---------------------------------------\n"
       . $message . "\n"
       . "---------------------------------------\n"
       . "Gesendet via kontakt.php ($MAIL_DRIVER)\n";

$html = "<div style=\"font-family:Arial,sans-serif;font-size:14px;line-height:1.55\">"
      . "<h2 style=\"margin:0 0 12px;color:#0F0F11\">Neue Nachricht von arnovoyer.com</h2>"
      . "<table border=\"0\" cellpadding=\"6\" cellspacing=\"0\" style=\"border-collapse:collapse\">"
      . "<tr><td style=\"width:120px;font-weight:bold;vertical-align:top\">Name:</td><td>" . htmlspecialchars($name)  . "</td></tr>"
      . "<tr><td style=\"font-weight:bold;vertical-align:top\">E-Mail:</td><td>" . htmlspecialchars($email) . "</td></tr>"
      . "<tr><td style=\"font-weight:bold;vertical-align:top\">Datum:</td><td>" . htmlspecialchars($date)  . "</td></tr>"
      . "</table>"
      . "<div style=\"margin-top:14px;padding:12px;background:#F8F8F5;border-left:4px solid #FF5A36\">"
      . nl2br(htmlspecialchars($message)) . "</div>"
      . "<div style=\"margin-top:12px;color:#888;font-size:11px\">"
      . "IP: " . htmlspecialchars($ip) . "</div></div>";

$diag = null;
$sent = versende($SMTP, $MAIL_DRIVER, $MAIL_FROM, $MAIL_FROM_NAME, $MAIL_TO, $MAIL_SUBJECT, $plain, $html, $diag);

if (!$sent) {
    http_response_code(500);
    out(false, [
        'error'   => 'Mail konnte auf dem Server nicht verschickt werden (' . $MAIL_DRIVER . ' liefert false).',
        'tip'     => 'Öffne kontakt.php?pw=' . urlencode($TEST_PASSWORD) . ' für eine Diagnose. Falls SMTP → korrektes A1-Passwort setzen!',
        'fallback' => 'mailto:' . $MAIL_TO . '?subject=' . rawurlencode($MAIL_SUBJECT) . '&body=' . rawurlencode("Name: $name\nE-Mail: $email\n\n$message\n"),
        'debug_hint' => ini_get('display_errors') ? (error_get_last() ?? null) : null,
    ]);
}

out(true, [
    'message' => 'Nachricht wurde übermittelt.',
    'to'      => $MAIL_TO,
    'via'     => $MAIL_DRIVER,
]);

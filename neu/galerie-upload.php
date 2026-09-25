<?php
/* ================================================================
   GALERIE UPLOAD – SERVER-SEITIGES PHP-SKRIPT
   ---------------------------------------------------------------
   BITTE VOR DEM ERSTEN EINSATZ UNBEDINGT:
   1. Passwort ($UPLOAD_PASSWORD) unten ändern
   2. Ordner ./galerie/ anlegen und Schreibrechte geben (chmod 755 oder 777)
   3. Diese Datei auf keinen Fall umbenennen – das Admin-HTML ruft sie
      exakt unter ./galerie-upload.php auf.
   ================================================================ */

// ----- KONFIGURATION (anpassen!) -----
$UPLOAD_PASSWORD = 'change-me-1234';  // <-- BITTE ÄNDERN !!!
$UPLOAD_DIR      = __DIR__ . '/galerie';
$ALLOWED_EXTS    = ['jpg', 'jpeg', 'png', 'webp'];
$ALLOWED_MIME    = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$MAX_FILESIZE    = 12 * 1024 * 1024;  // 12 MB
// --------------------------------------

header('Content-Type: application/json; charset=utf-8');

/* Basis-CORS für Aufruf von gleicher Domain */
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== '') {
    $self = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    if (strpos($_SERVER['HTTP_ORIGIN'], $self) === 0) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    }
}
header('X-Content-Type-Options: nosniff');

/* Ordner anlegen, falls nicht vorhanden (falls Rechte es zulassen) */
if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0755, true);
}

/* Sehr einfache Session/Token-Auth. Kein Passwort-Transport im JS nötig. */
session_start();

function json_out($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';

/* ----------------------------------------------
   1) AUTH: Passwort prüfen → Token (=Session-ID)
   ---------------------------------------------- */
if ($action === 'auth') {
    $pw = (string)($_POST['password'] ?? '');
    if (!hash_equals($UPLOAD_PASSWORD, $pw)) {
        sleep(1); // Brute-Force abfedern
        json_out(false, ['error' => 'Falsches Passwort.']);
    }
    session_regenerate_id(true);
    $_SESSION['galerie_upload_allowed'] = true;
    json_out(true, ['token' => session_id()]);
}

/* ----------------------------------------------
   2) VALIDIERUNG VOM TOKEN für Upload + List
   ---------------------------------------------- */
$givenToken = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$tokenValid = false;
if ($givenToken !== '' && session_status() === PHP_SESSION_ACTIVE) {
    $currentId = session_id();
    if (hash_equals($currentId, $givenToken) && !empty($_SESSION['galerie_upload_allowed'])) {
        $tokenValid = true;
    }
}

/* ----------------------------------------------
   3) UPLOAD: Datei speichern
   ---------------------------------------------- */
if ($action === 'upload') {
    if (!$tokenValid) json_out(false, ['error' => 'Nicht freigeschaltet. Passwort eingeben.']);
    if (!is_dir($UPLOAD_DIR) || !is_writable($UPLOAD_DIR)) {
        json_out(false, ['error' => 'Zielordner galerie/ fehlt oder ist nicht beschreibbar. Bitte Ordner anlegen + Schreibrechte setzen.']);
    }
    if (!isset($_FILES['file']) || !is_array($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['file']['error'] ?? 'unknown';
        $msg = [
            UPLOAD_ERR_INI_SIZE   => 'Datei ist zu groß (php.ini upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Datei ist zu groß (Formular-Limit).',
            UPLOAD_ERR_PARTIAL    => 'Upload wurde unterbrochen.',
            UPLOAD_ERR_NO_FILE    => 'Keine Datei erhalten.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP tmp-Ordner fehlt (Server-Meldung).',
            UPLOAD_ERR_CANT_WRITE => 'Kann Datei nicht schreiben (Server-Meldung).',
            UPLOAD_ERR_EXTENSION  => 'PHP-Extension hat Upload blockiert.',
        ];
        json_out(false, ['error' => $msg[$err] ?? ('Upload fehlgeschlagen – Code ' . $err)]);
    }

    $file = $_FILES['file'];
    if ($file['size'] > $MAX_FILESIZE) {
        json_out(false, ['error' => 'Datei zu groß. Maximal ' . round($MAX_FILESIZE/1024/1024) . ' MB erlaubt.']);
    }

    /* Typ-Prüfung 1: Extension */
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXTS, true)) {
        json_out(false, ['error' => 'Dateityp nicht erlaubt. Nur JPG / PNG / WEBP.']);
    }

    /* Typ-Prüfung 2: MIME + getimagesize (eigentliches Bildformat) */
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $file['tmp_name']);
        finfo_close($fi);
    } else {
        $mime = $file['type'];
    }
    if (!in_array($mime, $ALLOWED_MIME, true)) {
        json_out(false, ['error' => 'Datei ist kein gültiges Bild (MIME: ' . $mime . ').']);
    }
    $imgSize = @getimagesize($file['tmp_name']);
    if (!$imgSize || $imgSize[0] < 10 || $imgSize[1] < 10) {
        json_out(false, ['error' => 'Bild konnte nicht gelesen werden oder ist zu klein.']);
    }

    /* Dateiname generieren (mit Datum + Zufall, keine überschreibung) */
    $prefix = date('Ymd_His');
    $rand   = substr(bin2hex(random_bytes(4)), 0, 6);
    $finalName = $prefix . '_' . $rand . '.' . $ext;
    $finalPath = $UPLOAD_DIR . '/' . $finalName;
    $inc = 1;
    while (file_exists($finalPath)) {
        $finalName = $prefix . '_' . $rand . '_' . $inc . '.' . $ext;
        $finalPath = $UPLOAD_DIR . '/' . $finalName;
        $inc++;
        if ($inc > 99) { json_out(false, ['error' => 'Konnte keinen freien Dateinamen finden.']); }
    }

    if (!@move_uploaded_file($file['tmp_name'], $finalPath)) {
        json_out(false, ['error' => 'move_uploaded_file() fehlgeschlagen – prüfe Ordnerrechte.']);
    }
    @chmod($finalPath, 0644);

    json_out(true, [
        'name' => $finalName,
        'size' => filesize($finalPath),
        'url'  => './galerie/' . rawurlencode($finalName),
    ]);
}

/* ----------------------------------------------
   4) LIST: Gibt alle Bilder im Galerie-Ordner
      als JSON zurück – newest-first.
      (Wird von blog.html Galerie-Loader verwendet)
   ---------------------------------------------- */
if (($action === 'list') || (($_GET['list'] ?? '') === '1')) {
    if (!is_dir($UPLOAD_DIR)) json_out(true, ['images' => []]);
    $files = scandir($UPLOAD_DIR);
    if (!$files) json_out(true, ['images' => []]);
    $images = [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $UPLOAD_DIR . '/' . $f;
        if (!is_file($p)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXTS, true)) continue;
        $images[] = [
            'name' => $f,
            'url'  => './galerie/' . rawurlencode($f),
            'size' => filesize($p),
            'time' => filemtime($p),
        ];
    }
    usort($images, function($a,$b){ return $b['time'] <=> $a['time']; });
    json_out(true, ['images' => $images, 'count' => count($images)]);
}

json_out(false, ['error' => 'Unbekannte Aktion: ' . htmlspecialchars($action)]);

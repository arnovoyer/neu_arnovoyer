<?php

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';

auth_start_session();

$statusLine = '';
$statusClass = 'muted';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        $statusLine = '> fehler: ungültige sitzung. bitte neu laden.';
        $statusClass = 'error';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '' || $email === '' || $password === '') {
            $statusLine = '> fehler: name, e-mail und passwort sind erforderlich.';
            $statusClass = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $statusLine = '> fehler: bitte eine gültige e-mail eingeben.';
            $statusClass = 'error';
        } elseif (strlen($password) < 8) {
            $statusLine = '> fehler: das passwort muss mindestens 8 zeichen haben.';
            $statusClass = 'error';
        } elseif (auth_find_user_by_email($email)) {
            $statusLine = '> fehler: dieser account existiert bereits.';
            $statusClass = 'error';
        } else {
            $user = [
                'id' => bin2hex(random_bytes(8)),
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'message' => $message,
                'status' => 'pending',
                'created_at' => date('c'),
                'approved_at' => null,
            ];

            if (auth_upsert_user($user)) {
                $statusLine = '> registrierung gespeichert. dein account wartet auf freigabe.';
                $statusClass = 'ok';
            } else {
                $statusLine = '> fehler: account konnte nicht gespeichert werden.';
                $statusClass = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title>Registrierung | Arno Voyer</title>
  <style>
    :root { --accent: #00f5d4; --bg: #050505; --card: rgba(255,255,255,.04); --border: rgba(255,255,255,.12); --text-soft: rgba(255,255,255,.7); }
    * { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; background: var(--bg); color: #fff; }
    .noise { position: fixed; inset: 0; pointer-events: none; opacity: .035; background-image: url("https://grainy-gradients.vercel.app/noise.svg"); z-index: 0; }
    .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; position: relative; z-index: 1; }
    .terminal { width: 100%; max-width: 760px; background: var(--card); border: 1px solid var(--border); border-radius: 16px; backdrop-filter: blur(8px); overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,.35); }
    .terminal-bar { height: 42px; display: flex; align-items: center; gap: 8px; padding: 0 14px; border-bottom: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04); }
    .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; } .dot.red { background: #ff5f56; } .dot.yellow { background: #ffbd2e; } .dot.green { background: #27c93f; }
    .terminal-title { margin-left: auto; color: var(--text-soft); font-size: 12px; }
    .terminal-body { padding: 24px; display: grid; gap: 14px; }
    .line { color: var(--accent); font-size: 14px; margin: 0; overflow-wrap: anywhere; }
    .line.muted { color: var(--text-soft); } .line.error { color: #ff7d7d; } .line.ok { color: #87ffdb; }
    .prompt { display: grid; gap: 6px; }
    .label { color: var(--accent); font-size: 14px; }
    .input, .textarea { width: 100%; border: 0; border-bottom: 1px solid rgba(255,255,255,.2); background: transparent; color: white; padding: 8px 0; font: inherit; outline: none; }
    .input:focus, .textarea:focus { border-bottom-color: var(--accent); }
    .submit { margin-top: 4px; border: 0; background: transparent; color: var(--accent); font: inherit; text-align: left; padding: 0; cursor: pointer; }
    .back { display: inline-block; margin-top: 8px; color: var(--text-soft); text-decoration: none; font-size: 13px; }
    .back:hover { color: var(--accent); }
    .status { min-height: 1.2em; }
    @media (max-width: 640px) { .terminal-body { padding: 18px; } }
  </style>
</head>
<body>
  <div class="noise"></div>
  <main class="wrap">
    <section class="terminal" aria-label="Registrierung">
      <div class="terminal-bar"><span class="dot red"></span><span class="dot yellow"></span><span class="dot green"></span><span class="terminal-title">secure shell — register</span></div>
      <div class="terminal-body">
        <p class="line">> account registration</p>
        <p class="line muted">> dein account wird erst nach freigabe aktiv</p>
        <p class="line status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLine, ENT_QUOTES, 'UTF-8'); ?></p>
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="prompt"><label class="label" for="name">name</label><input class="input" id="name" name="name" type="text" required></div>
          <div class="prompt" style="margin-top:14px;"><label class="label" for="email">e-mail</label><input class="input" id="email" name="email" type="email" required></div>
          <div class="prompt" style="margin-top:14px;"><label class="label" for="password">passwort</label><input class="input" id="password" name="password" type="password" minlength="8" required></div>
          <div class="prompt" style="margin-top:14px;"><label class="label" for="message">warum möchtest du zugang?</label><textarea class="textarea" id="message" name="message" rows="4"></textarea></div>
          <button class="submit" type="submit">> registrieren</button>
        </form>
        <a class="back" href="/index.html">zurück zur seite</a>
        <a class="back" href="/auth/login.php">bereits einen account? login</a>
      </div>
    </section>
  </main>
</body>
</html>

<?php
session_start();
header('X-Robots-Tag: noindex, nofollow, noarchive', true);

$statusLine = '';
$statusClass = '';
$authConfigPath = __DIR__ . '/auth.local.php';
$auth = file_exists($authConfigPath) ? require $authConfigPath : [];
$validUsername = $auth['username'] ?? '';
$validPassword = $auth['password'] ?? '';

if (!empty($_SESSION['is_logged_in'])) {
	header('Location: index.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = trim($_POST['password'] ?? '');

	if ($validUsername === '' || $validPassword === '') {
		$statusLine = '> fehler: auth.local.php fehlt oder ist unvollständig.';
		$statusClass = 'error';
	} elseif ($username === '' || $password === '') {
		$statusLine = '> fehler: username und passwort erforderlich.';
		$statusClass = 'error';
	} elseif (!hash_equals($validUsername, $username) || !hash_equals($validPassword, $password)) {
		$statusLine = '> dieser befehl ist fehlgeschlagen. versuche es erneut.';
		$statusClass = 'error';
	} else {
		$_SESSION['is_logged_in'] = true;
		$_SESSION['username'] = $validUsername;
		header('Location: index.php');
		exit;
	}
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Login · Arno Voyer">
	<meta name="robots" content="noindex, nofollow, noarchive">
	<title>Login | Arno Voyer</title>
	<link rel="icon" type="image/x-icon" href="/assets/logo.webp">
	<style>
		:root {
			--accent: #00f5d4;
			--bg: #050505;
			--card: rgba(255, 255, 255, 0.04);
			--border: rgba(255, 255, 255, 0.12);
			--text-soft: rgba(255, 255, 255, 0.7);
		}

		* {
			box-sizing: border-box;
		}

		html,
		body {
			margin: 0;
			min-height: 100%;
			font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
			background: var(--bg);
			color: #fff;
		}

		.noise {
			position: fixed;
			inset: 0;
			pointer-events: none;
			opacity: .035;
			background-image: url("https://grainy-gradients.vercel.app/noise.svg");
			z-index: 0;
		}

		.wrap {
			min-height: 100vh;
			display: grid;
			place-items: center;
			padding: 24px;
			position: relative;
			z-index: 1;
		}

		.terminal {
			width: 100%;
			max-width: 740px;
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: 16px;
			backdrop-filter: blur(8px);
			overflow: hidden;
			box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
		}

		.terminal-bar {
			height: 42px;
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 0 14px;
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
			background: rgba(255, 255, 255, 0.04);
		}

		.dot {
			width: 10px;
			height: 10px;
			border-radius: 50%;
			display: inline-block;
		}

		.dot.red {
			background: #ff5f56;
		}

		.dot.yellow {
			background: #ffbd2e;
		}

		.dot.green {
			background: #27c93f;
		}

		.terminal-title {
			margin-left: auto;
			color: var(--text-soft);
			font-size: 12px;
		}

		.terminal-body {
			padding: 24px;
			display: grid;
			gap: 14px;
		}

		.line {
			color: var(--accent);
			font-size: 14px;
			margin: 0;
			white-space: normal;
			overflow-wrap: anywhere;
		}

		.line.muted {
			color: var(--text-soft);
		}

		.line.error {
			color: #ff7d7d;
		}

		.line.ok {
			color: #87ffdb;
		}

		.prompt {
			display: grid;
			gap: 6px;
		}

		.label {
			color: var(--accent);
			font-size: 14px;
		}

		.input {
			width: 100%;
			border: 0;
			border-bottom: 1px solid rgba(255, 255, 255, 0.2);
			background: transparent;
			color: white;
			padding: 8px 0;
			font: inherit;
			outline: none;
		}

		.input:focus {
			border-bottom-color: var(--accent);
		}

		.submit {
			margin-top: 4px;
			border: 0;
			background: transparent;
			color: var(--accent);
			font: inherit;
			text-align: left;
			padding: 0;
			cursor: pointer;
		}

		.back {
			display: inline-block;
			margin-top: 8px;
			color: var(--text-soft);
			text-decoration: none;
			font-size: 13px;
		}

		.back:hover {
			color: var(--accent);
		}

		@media (max-width: 640px) {
			.terminal-body {
				padding: 18px;
			}
		}
	</style>
</head>

<body>
	<div class="noise"></div>

	<main class="wrap">
		<section class="terminal" aria-label="Terminal Login">
			<div class="terminal-bar">
				<span class="dot red"></span>
				<span class="dot yellow"></span>
				<span class="dot green"></span>
				<span class="terminal-title">secure shell — login.php</span>
			</div>

			<div class="terminal-body">
				<p class="line">> booting secure channel...</p>
				<p class="line muted">> loading secure interface</p>
				<p class="line muted">> ready</p>

				<form method="post" autocomplete="off">
					<div class="prompt">
						<label class="label" for="username">sudo apt username</label>
						<input class="input" id="username" name="username" type="text" required>
					</div>

					<div class="prompt" style="margin-top: 14px;">
						<label class="label" for="password">sudo apt password</label>
						<input class="input" id="password" name="password" type="password" required>
					</div>

					<button class="submit" type="submit" style="margin-top: 16px;">> login --init</button>
				</form>

				<?php if ($statusLine !== ''): ?>
					<p class="line <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLine, ENT_QUOTES, 'UTF-8'); ?></p>
				<?php endif; ?>

				<a class="back" href="index.html">← zurück zur startseite</a>
			</div>
		</section>
	</main>
</body>

</html>

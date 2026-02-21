<?php
session_start();

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['is_logged_in'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard · Arno Voyer">
    <title>Dashboard | Arno Voyer</title>
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
            padding: 24px;
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
        }

        .panel {
            width: 100%;
            max-width: 980px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            backdrop-filter: blur(8px);
            overflow: hidden;
        }

        .topbar {
            height: 46px;
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

        .title {
            margin-left: auto;
            color: var(--text-soft);
            font-size: 12px;
        }

        .body {
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .line {
            margin: 0;
            font-size: 14px;
            color: var(--accent);
        }

        .subline {
            margin: 0;
            font-size: 14px;
            color: var(--text-soft);
        }

        .grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .dashboard-card {
            padding: 20px;
        }

        .dashboard-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 12px;
        }

        .dashboard-time {
            font-size: 30px;
            line-height: 1;
            color: var(--accent);
            margin: 0;
        }

        .dashboard-date {
            font-size: 13px;
            color: var(--text-soft);
            margin: 0;
        }

        .dashboard-stats {
            display: grid;
            gap: 8px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 6px;
            font-size: 13px;
        }

        .stat-key {
            color: var(--text-soft);
        }

        .stat-val {
            color: #fff;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.02);
        }

        .card h2 {
            margin: 0 0 8px;
            font-size: 15px;
        }

        .card p {
            margin: 0;
            font-size: 13px;
            color: var(--text-soft);
        }

        .links {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        a.link {
            color: var(--accent);
            text-decoration: none;
            font-size: 14px;
        }

        a.link:hover {
            opacity: .85;
        }

        @media (min-width: 768px) {
            .grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .dashboard-card {
                grid-column: span 2;
            }
        }
    </style>
</head>

<body>
    <div class="noise"></div>

    <main class="wrap">
        <section class="panel" aria-label="Dashboard">
            <div class="topbar">
                <span class="dot red"></span>
                <span class="dot yellow"></span>
                <span class="dot green"></span>
                <span class="title">dashboard — index.php</span>
            </div>

            <div class="body">
                <p class="line">> login erfolgreich</p>
                <p class="subline">> willkommen, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>

                <div class="grid">
                    <article class="card dashboard-card">
                        <h2>Dashboard</h2>
                        <div class="dashboard-head">
                            <p id="dashboard-time" class="dashboard-time">--:--:--</p>
                            <p id="dashboard-date" class="dashboard-date">--.--.----</p>
                        </div>
                        <div class="dashboard-stats">
                            <div class="stat-row">
                                <span class="stat-key">Status</span>
                                <span class="stat-val">Aktiv</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-key">Benutzer</span>
                                <span
                                    class="stat-val"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-key">Wochentag</span>
                                <span id="dashboard-day" class="stat-val">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-key">Zeitzone</span>
                                <span id="dashboard-zone" class="stat-val">--</span>
                            </div>
                        </div>
                    </article>
                    <article class="card">
                        <h2>Nützliche Infos</h2>
                        <p>Dein Login ist aktiv. Nutze die Schnelllinks unten für direkte Navigation.</p>
                    </article>
                    <article class="card">
                        <h2>Links</h2>
                        <p>Direktzugriffe auf deine wichtigsten Seiten.</p>
                        <a class="link" href="https://schule.arnovoyer.com" target="_blank">schule.arnovoyer.com</a>
                        <a class="link" href="https://github.com/arnovoyer" target="_blank">github</a>
                    </article>
                </div>

                <div class="links">
                    <a class="link" href="index.html">> zur öffentlichen seite</a>
                    <a class="link" href="index.php?logout=1">> logout</a>
                </div>
            </div>
        </section>
    </main>

    <script>
        const timeEl = document.getElementById('dashboard-time')
        const dateEl = document.getElementById('dashboard-date')
        const dayEl = document.getElementById('dashboard-day')
        const zoneEl = document.getElementById('dashboard-zone')

        function updateDashboardClock() {
            const now = new Date()
            timeEl.textContent = now.toLocaleTimeString('de-AT', { hour12: false })
            dateEl.textContent = now.toLocaleDateString('de-AT')
            dayEl.textContent = now.toLocaleDateString('de-AT', { weekday: 'long' })
            zoneEl.textContent = Intl.DateTimeFormat().resolvedOptions().timeZone
        }

        updateDashboardClock()
        setInterval(updateDashboardClock, 1000)
    </script>
</body>

</html>
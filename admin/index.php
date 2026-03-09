<?php
session_start();
header('X-Robots-Tag: noindex, nofollow, noarchive', true);

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
$blogFilePath = __DIR__ . '/blogs.json';
$statusLine = '';
$statusClass = 'muted';
$editEntry = null;

function loadBlogs(string $filePath): array
{
    if (!file_exists($filePath)) {
        return [];
    }

    $raw = file_get_contents($filePath);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function saveBlogs(string $filePath, array $blogs): bool
{
    $json = json_encode($blogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($filePath, $json . PHP_EOL, LOCK_EX) !== false;
}

function buildSlug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post-' . substr(bin2hex(random_bytes(4)), 0, 8);
}

$blogs = loadBlogs($blogFilePath);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = trim($_POST['id'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $published = isset($_POST['published']);

        if ($title === '' || $content === '') {
            $statusLine = '> fehler: titel und inhalt sind erforderlich.';
            $statusClass = 'error';
        } else {
            $slug = buildSlug($slugInput !== '' ? $slugInput : $title);
            $now = date('c');
            $existingIndex = null;

            foreach ($blogs as $index => $entry) {
                if (($entry['id'] ?? '') === $id && $id !== '') {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                $blogs[$existingIndex]['title'] = $title;
                $blogs[$existingIndex]['slug'] = $slug;
                $blogs[$existingIndex]['excerpt'] = $excerpt;
                $blogs[$existingIndex]['content'] = $content;
                $blogs[$existingIndex]['published'] = $published;
                $blogs[$existingIndex]['updated_at'] = $now;
                $statusLine = '> blog aktualisiert.';
                $statusClass = 'ok';
            } else {
                $blogs[] = [
                    'id' => bin2hex(random_bytes(8)),
                    'title' => $title,
                    'slug' => $slug,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'published' => $published,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $statusLine = '> neuer blog erstellt.';
                $statusClass = 'ok';
            }

            usort($blogs, static function (array $a, array $b): int {
                return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
            });

            if (!saveBlogs($blogFilePath, $blogs)) {
                $statusLine = '> fehler: blogs konnten nicht gespeichert werden.';
                $statusClass = 'error';
            }
        }
    }

    if ($action === 'delete') {
        $id = trim($_POST['id'] ?? '');
        $before = count($blogs);
        $blogs = array_values(array_filter($blogs, static function (array $entry) use ($id): bool {
            return ($entry['id'] ?? '') !== $id;
        }));

        if (count($blogs) === $before) {
            $statusLine = '> fehler: eintrag nicht gefunden.';
            $statusClass = 'error';
        } elseif (saveBlogs($blogFilePath, $blogs)) {
            $statusLine = '> blog entfernt.';
            $statusClass = 'ok';
        } else {
            $statusLine = '> fehler: loeschen fehlgeschlagen.';
            $statusClass = 'error';
        }
    }
}

$editId = trim($_GET['edit'] ?? '');
if ($editId !== '') {
    foreach ($blogs as $entry) {
        if (($entry['id'] ?? '') === $editId) {
            $editEntry = $entry;
            break;
        }
    }
}

$publishedCount = count(array_filter($blogs, static function (array $entry): bool {
    return !empty($entry['published']);
}));
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blog Dashboard · Arno Voyer">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Blog Dashboard | Arno Voyer</title>
    <link rel="icon" type="image/x-icon" href="/assets/logo.webp">
    <style>
        :root {
            --accent: #00f5d4;
            --bg: #050505;
            --card: rgba(255, 255, 255, 0.04);
            --border: rgba(255, 255, 255, 0.12);
            --text-soft: rgba(255, 255, 255, 0.7);
            --error: #ff8c8c;
            --ok: #90ffd4;
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
            max-width: 1100px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            backdrop-filter: blur(8px);
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
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
            padding: 20px;
            display: grid;
            gap: 16px;
        }

        .line {
            margin: 0;
            font-size: 14px;
            color: var(--accent);
        }

        .line.muted {
            color: var(--text-soft);
        }

        .line.error {
            color: var(--error);
        }

        .line.ok {
            color: var(--ok);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .dashboard-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .dashboard-card {
            grid-column: span 2;
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

        .stat {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.02);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-soft);
        }

        .stat-value {
            font-size: 20px;
            color: #fff;
            margin-top: 4px;
        }

        .layout {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 14px;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.02);
        }

        .card h2 {
            margin: 0 0 10px;
            font-size: 15px;
        }

        .form-grid {
            display: grid;
            gap: 10px;
        }

        .label {
            color: var(--text-soft);
            font-size: 13px;
            margin-bottom: 4px;
            display: block;
        }

        .input,
        .textarea {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(0, 0, 0, 0.2);
            color: #fff;
            padding: 10px;
            font: inherit;
            border-radius: 8px;
            outline: none;
        }

        .input:focus,
        .textarea:focus {
            border-color: var(--accent);
        }

        .textarea {
            min-height: 170px;
            resize: vertical;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-soft);
            font-size: 13px;
            margin-top: 2px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 2px;
        }

        button,
        .link {
            border: 1px solid rgba(0, 245, 212, 0.45);
            background: rgba(0, 245, 212, 0.08);
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
        }

        button:hover,
        .link:hover {
            color: var(--accent);
            border-color: rgba(0, 245, 212, 0.9);
            background: rgba(0, 245, 212, 0.14);
        }

        .list {
            display: grid;
            gap: 10px;
            max-height: 560px;
            overflow: auto;
        }

        .entry {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            display: grid;
            gap: 8px;
        }

        .entry-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }

        .entry h3 {
            margin: 0;
            font-size: 14px;
        }

        .meta {
            font-size: 12px;
            color: var(--text-soft);
        }

        .badge {
            font-size: 11px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 999px;
            padding: 2px 8px;
            white-space: nowrap;
        }

        .badge.live {
            border-color: rgba(0, 245, 212, 0.6);
            color: var(--accent);
        }

        .entry-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .entry-actions form {
            margin: 0;
        }

        .links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 920px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-card {
                grid-column: span 1;
            }

            .row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="noise"></div>

    <main class="wrap">
        <section class="panel" aria-label="Blog Dashboard">
            <div class="topbar">
                <span class="dot red"></span>
                <span class="dot yellow"></span>
                <span class="dot green"></span>
                <span class="title">blog-admin -- index.php</span>
            </div>

            <div class="body">
                <p class="line">> login erfolgreich</p>
                <p class="line muted">> willkommen, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="line muted">> blog datei: <?php echo htmlspecialchars(basename($blogFilePath), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($statusLine !== ''): ?>
                    <p class="line <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLine, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <div class="stats">
                    <article class="stat">
                        <div class="stat-label">Gesamt</div>
                        <div class="stat-value"><?php echo count($blogs); ?></div>
                    </article>
                    <article class="stat">
                        <div class="stat-label">Veroeffentlicht</div>
                        <div class="stat-value"><?php echo $publishedCount; ?></div>
                    </article>
                    <article class="stat">
                        <div class="stat-label">Entwuerfe</div>
                        <div class="stat-value"><?php echo count($blogs) - $publishedCount; ?></div>
                    </article>
                </div>

                <div class="dashboard-grid">
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
                                <span class="stat-val"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
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
                        <h2>Info</h2>
                        <p class="meta">Login aktiv. Du kannst hier Dashboard und Blog-Verwaltung gemeinsam nutzen.</p>
                    </article>
                </div>

                <div class="layout">
                    <article class="card">
                        <h2><?php echo $editEntry ? 'Blog bearbeiten' : 'Neuen Blog erstellen'; ?></h2>

                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editEntry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                            <div>
                                <label class="label" for="title">Titel</label>
                                <input class="input" id="title" name="title" type="text" required value="<?php echo htmlspecialchars($editEntry['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="row">
                                <div>
                                    <label class="label" for="slug">Slug (optional)</label>
                                    <input class="input" id="slug" name="slug" type="text" value="<?php echo htmlspecialchars($editEntry['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div>
                                    <label class="label" for="excerpt">Kurztext</label>
                                    <input class="input" id="excerpt" name="excerpt" type="text" value="<?php echo htmlspecialchars($editEntry['excerpt'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>

                            <div>
                                <label class="label" for="content">Inhalt</label>
                                <textarea class="textarea" id="content" name="content" required><?php echo htmlspecialchars($editEntry['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="meta">Links: <code>[Linktext](https://example.com)</code> oder <code>[Kontakt](mailto:mail@beispiel.at)</code></div>
                            </div>

                            <label class="toggle" for="published">
                                <input id="published" name="published" type="checkbox" <?php echo !empty($editEntry['published']) ? 'checked' : ''; ?>>
                                veroeffentlichen
                            </label>

                            <div class="actions">
                                <button type="submit">> speichern</button>
                                <?php if ($editEntry): ?>
                                    <a class="link" href="index.php">> neuer eintrag</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>

                    <article class="card">
                        <h2>Vorhandene Blogs</h2>
                        <div class="list">
                            <?php if (count($blogs) === 0): ?>
                                <p class="line muted">> noch keine eintraege vorhanden</p>
                            <?php endif; ?>

                            <?php foreach ($blogs as $entry): ?>
                                <article class="entry">
                                    <div class="entry-head">
                                        <div>
                                            <h3><?php echo htmlspecialchars($entry['title'] ?? 'Ohne Titel', ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <div class="meta">
                                                /<?php echo htmlspecialchars($entry['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?> ·
                                                <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($entry['updated_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </div>
                                        <span class="badge <?php echo !empty($entry['published']) ? 'live' : ''; ?>">
                                            <?php echo !empty($entry['published']) ? 'live' : 'draft'; ?>
                                        </span>
                                    </div>

                                    <div class="entry-actions">
                                        <a class="link" href="index.php?edit=<?php echo urlencode($entry['id'] ?? ''); ?>">> bearbeiten</a>
                                        <form method="post" onsubmit="return confirm('Blog wirklich loeschen?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($entry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit">> loeschen</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>

                <div class="links">
                    <a class="link" href="/admin/blog.php" target="_blank">> blog ansehen</a>
                    <a class="link" href="/index.html" target="_blank">> zur startseite</a>
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

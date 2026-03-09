<?php
$blogFilePath = __DIR__ . '/admin/blogs.json';
$allBlogs = [];

if (file_exists($blogFilePath)) {
    $raw = file_get_contents($blogFilePath);
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $allBlogs = $decoded;
        }
    }
}

$publishedBlogs = array_values(array_filter($allBlogs, static function (array $entry): bool {
    return !empty($entry['published']);
}));

usort($publishedBlogs, static function (array $a, array $b): int {
    return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
});

$slug = trim($_GET['post'] ?? '');
$activePost = null;

if ($slug !== '') {
    foreach ($publishedBlogs as $entry) {
        if (($entry['slug'] ?? '') === $slug) {
            $activePost = $entry;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de-AT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blog von Arno Voyer über Webentwicklung, Projekte und Workflow.">
    <title>Blog | Arno Voyer</title>
    <link rel="icon" type="image/svg+xml" href="/assets/logo.svg">
    <style>
        :root {
            --accent: #00f5d4;
            --bg: #050505;
            --card: rgba(255, 255, 255, 0.04);
            --border: rgba(255, 255, 255, 0.12);
            --text-soft: rgba(255, 255, 255, 0.72);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            background: radial-gradient(circle at 10% 10%, #121212, #050505 50%);
            color: #fff;
            padding: 26px 16px;
        }

        .noise {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .03;
            background-image: url("https://grainy-gradients.vercel.app/noise.svg");
        }

        .window {
            width: min(1040px, 100%);
            margin: 0 auto;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
        }

        .topbar {
            height: 46px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .red { background: #ff5f56; }
        .yellow { background: #ffbd2e; }
        .green { background: #27c93f; }

        .title {
            margin-left: auto;
            color: var(--text-soft);
            font-size: 12px;
        }

        .url {
            margin: 12px 14px 0;
            border: 1px solid rgba(255, 255, 255, 0.13);
            background: rgba(0, 0, 0, 0.18);
            border-radius: 9px;
            padding: 8px 12px;
            font-size: 13px;
            color: var(--text-soft);
        }

        .content {
            padding: 18px 14px 20px;
            display: grid;
            gap: 14px;
        }

        .head h1 {
            margin: 0;
            font-size: clamp(1.5rem, 2.8vw, 2.2rem);
            line-height: 1.15;
        }

        .head p {
            margin: 10px 0 0;
            color: var(--text-soft);
            font-size: 14px;
        }

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .nav a {
            color: var(--accent);
            text-decoration: none;
            font-size: 13px;
            border: 1px solid rgba(0, 245, 212, 0.4);
            border-radius: 8px;
            padding: 6px 10px;
            background: rgba(0, 245, 212, 0.08);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 0.9fr;
            gap: 12px;
        }

        .panel {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
        }

        .panel h2 {
            margin: 0 0 10px;
            font-size: 14px;
            color: var(--accent);
            font-weight: 600;
        }

        .post-list {
            display: grid;
            gap: 10px;
            max-height: 560px;
            overflow: auto;
        }

        .post-item {
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.18);
        }

        .post-item h3 {
            margin: 0;
            font-size: 14px;
        }

        .post-item p {
            margin: 8px 0 0;
            color: var(--text-soft);
            font-size: 13px;
            line-height: 1.5;
        }

        .meta {
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-soft);
        }

        .post-item a {
            color: var(--accent);
            text-decoration: none;
        }

        .article {
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.65;
            color: #f1f1f1;
        }

        .empty {
            color: var(--text-soft);
            font-size: 14px;
        }

        @media (max-width: 860px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .post-list {
                max-height: none;
            }
        }
    </style>
</head>

<body>
    <div class="noise"></div>

    <main class="window" aria-label="Blog Fenster">
        <div class="topbar">
            <span class="dot red"></span>
            <span class="dot yellow"></span>
            <span class="dot green"></span>
            <span class="title">blog -- arnovoyer.com</span>
        </div>

        <div class="url">https://arnovoyer.com/blog.php</div>

        <div class="content">
            <header class="head">
                <h1>Blog im minimalistischen Dev-Stil</h1>
                <p>Notizen ueber Code, Projekte und was gerade gebaut wird.</p>
            </header>

            <nav class="nav" aria-label="Seitennavigation">
                <a href="/index.html">/ home</a>
                <a href="/admin/index.php">/ admin</a>
            </nav>

            <section class="grid">
                <article class="panel">
                    <h2>Posts</h2>
                    <div class="post-list">
                        <?php if (count($publishedBlogs) === 0): ?>
                            <p class="empty">Noch keine veroeffentlichten Blogeintraege vorhanden.</p>
                        <?php endif; ?>

                        <?php foreach ($publishedBlogs as $entry): ?>
                            <div class="post-item">
                                <h3>
                                    <a href="?post=<?php echo urlencode($entry['slug'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($entry['title'] ?? 'Ohne Titel', ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </h3>
                                <?php if (!empty($entry['excerpt'])): ?>
                                    <p><?php echo htmlspecialchars($entry['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <div class="meta">
                                    <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($entry['updated_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel">
                    <h2><?php echo $activePost ? 'Geoeffneter Post' : 'Hinweis'; ?></h2>
                    <?php if ($activePost): ?>
                        <h3><?php echo htmlspecialchars($activePost['title'] ?? 'Ohne Titel', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="meta">
                            zuletzt aktualisiert: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($activePost['updated_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <p class="article"><?php echo htmlspecialchars($activePost['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                        <p class="empty">Waehle links einen Post aus, um den Inhalt zu sehen.</p>
                    <?php endif; ?>
                </article>
            </section>
        </div>
    </main>
</body>

</html>

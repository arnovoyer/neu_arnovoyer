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

function isAllowedBlogUrl(string $url): bool
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return false;
    }

    if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, '#')) {
        return true;
    }

    $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
}

function renderBlogContent(string $content): string
{
    $replacements = [];

    // Parse markdown links first, then escape the rest of the text.
    $withPlaceholders = preg_replace_callback(
        '/\[([^\]]+)\]\(\s*([^\)]+?)\s*\)/',
        static function (array $matches) use (&$replacements): string {
            $label = trim($matches[1]);
            $url = trim($matches[2]);

            if ($label === '' || !isAllowedBlogUrl($url)) {
                return $matches[0];
            }

            $placeholder = '__BLOG_LINK_' . count($replacements) . '__';
            $replacements[$placeholder] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' .
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
                '</a>';

            return $placeholder;
        },
        $content
    );

    $escaped = htmlspecialchars($withPlaceholders ?? $content, ENT_QUOTES, 'UTF-8');
    $withLinks = strtr($escaped, $replacements);

    return nl2br($withLinks, false);
}

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

$postsForClient = [];
foreach ($publishedBlogs as $entry) {
    $entrySlug = $entry['slug'] ?? '';
    if ($entrySlug === '') {
        continue;
    }

    $postsForClient[$entrySlug] = [
        'title' => $entry['title'] ?? 'Ohne Titel',
        'updated_at' => date('d.m.Y H:i', strtotime($entry['updated_at'] ?? 'now')),
        'content_html' => renderBlogContent($entry['content'] ?? ''),
    ];
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
    <link rel="stylesheet" href="/assets/context-menu.css">
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
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.28);
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
            padding: 0;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
        }

        .content {
            padding: 20px 14px 22px;
            display: grid;
            gap: 18px;
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
            max-width: 520px;
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

        .task-dock {
            position: fixed;
            left: 50%;
            bottom: 20px;
            transform: translate(-50%, 120%);
            opacity: 0;
            pointer-events: none;
            z-index: 10000;
            transition: transform .35s ease, opacity .25s ease;
        }

        .task-dock.visible {
            transform: translate(-50%, 0);
            opacity: 1;
            pointer-events: auto;
        }

        @media (min-width: 861px) {
            .task-dock {
                transform: translate(-50%, 0);
                opacity: 1;
                pointer-events: auto;
            }
        }

        .task-dock-shell {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            border-radius: 18px;
            background: linear-gradient(150deg, rgba(26, 26, 26, 0.86), rgba(10, 10, 10, 0.92));
            border: 1px solid rgba(255, 255, 255, 0.16);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(10px) saturate(130%);
        }

        .dock-start,
        .dock-item {
            height: 48px;
            min-width: 48px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 12px;
            cursor: pointer;
            transition: transform .2s ease, background .2s ease, border-color .2s ease;
            text-decoration: none;
            white-space: nowrap;
            position: relative;
        }

        .dock-start {
            width: 48px;
            min-width: 48px;
            padding: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(0, 245, 212, 0.95), rgba(0, 245, 212, 0.65));
            border-color: rgba(0, 245, 212, 0.7);
        }

        .dock-start img {
            width: 24px;
            height: 24px;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
            pointer-events: none;
        }

        .dock-item:hover,
        .dock-item:focus-visible,
        .dock-start:hover,
        .dock-start:focus-visible {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(0, 245, 212, 0.5);
            outline: none;
        }

        .dock-item.active::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 4px;
            width: 6px;
            height: 6px;
            border-radius: 999px;
            transform: translateX(-50%);
            background: var(--accent);
            box-shadow: 0 0 12px rgba(0, 245, 212, 0.8);
        }

        .dock-icon {
            font-size: 1.1rem;
            line-height: 1;
        }

        .dock-label {
            font-size: 0.82rem;
            letter-spacing: .01em;
            color: rgba(255, 255, 255, 0.95);
        }

        .dock-sep {
            width: 1px;
            height: 30px;
            background: rgba(255, 255, 255, 0.18);
            margin: 0 2px;
        }

        .grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(260px, 0.7fr);
            gap: 14px;
        }

        .panel {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.015);
        }

        .panel h2 {
            margin: 0 0 10px;
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.52);
            font-weight: 600;
        }

        .post-list {
            display: grid;
            gap: 10px;
            max-height: 560px;
            overflow: auto;
        }

        .post-item {
            display: block;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.015);
            cursor: pointer;
            transition: border-color 140ms ease, background-color 140ms ease;
            text-decoration: none;
            color: inherit;
        }

        .post-item:hover,
        .post-item:focus-visible {
            border-color: rgba(0, 245, 212, 0.3);
            background: rgba(255, 255, 255, 0.035);
            outline: none;
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
            font-size: 11px;
            color: var(--text-soft);
        }

        .note-copy {
            color: rgba(255, 255, 255, 0.64);
            font-size: 13px;
            line-height: 1.6;
            margin: 0;
        }

        .article {
            font-size: 14px;
            line-height: 1.65;
            color: #f1f1f1;
        }

        .article a {
            color: var(--accent);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .open-post {
            color: var(--accent);
            text-decoration: none;
        }

        .post-modal {
            position: fixed;
            inset: 0;
            display: grid;
            place-items: center;
            background: rgba(0, 0, 0, 0.72);
            backdrop-filter: blur(3px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 160ms ease;
            z-index: 30;
            padding: 14px;
        }

        .post-modal-backdrop-close {
            position: absolute;
            inset: 0;
            display: block;
            z-index: 0;
        }

        .post-modal.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .post-modal-window {
            position: relative;
            z-index: 1;
            width: min(860px, 100%);
            max-height: min(84vh, 900px);
            background: rgba(8, 8, 8, 0.96);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.48);
            display: grid;
            grid-template-rows: auto auto 1fr;
        }

        .post-modal-topbar {
            height: 46px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot-control {
            appearance: none;
            border: 0;
            padding: 0;
            margin: 0;
            background: transparent;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            cursor: pointer;
        }

        .post-modal-title {
            margin-left: auto;
            color: var(--text-soft);
            font-size: 12px;
        }

        .dot-control.red {
            background: #ff5f56;
        }

        .dot-control:focus-visible {
            outline: 2px solid rgba(255, 255, 255, 0.8);
            outline-offset: 2px;
        }

        .post-modal-meta {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-soft);
            font-size: 12px;
        }

        .post-modal-content {
            padding: 14px;
            overflow: auto;
        }

        body.modal-open {
            overflow: hidden;
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

            .post-modal {
                padding: 8px;
            }

            .post-modal-window {
                max-height: 92vh;
            }

            .task-dock {
                left: 50%;
                right: auto;
                width: min(560px, calc(100% - 24px));
                transform: translate(-50%, 120%);
            }

            .task-dock.visible {
                transform: translate(-50%, 0);
            }

            .task-dock-shell {
                justify-content: space-between;
                width: 100%;
                padding: 8px;
                border-radius: 14px;
            }

            .dock-item {
                flex: 1;
                min-width: 0;
                padding: 0 8px;
            }

            .dock-label {
                display: none;
            }

            .dock-sep {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .task-dock {
                transition: none;
            }

            .dock-start,
            .dock-item {
                transition: none;
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
                <h1>Mein persoenlicher Blog</h1>
                <p>Notizen ueber Code, Projekte und was gerade gebaut wird.</p>
            </header>

            <section class="grid">
                <article class="panel">
                    <h2>Posts</h2>
                    <div class="post-list">
                        <?php if (count($publishedBlogs) === 0): ?>
                            <p class="empty">Noch keine veroeffentlichten Blogeintraege vorhanden.</p>
                        <?php endif; ?>

                        <?php foreach ($publishedBlogs as $entry): ?>
                            <a class="post-item open-post" href="?post=<?php echo urlencode($entry['slug'] ?? ''); ?>" data-open-post="<?php echo htmlspecialchars($entry['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Post oeffnen: <?php echo htmlspecialchars($entry['title'] ?? 'Ohne Titel', ENT_QUOTES, 'UTF-8'); ?>">
                                <h3>
                                    <?php echo htmlspecialchars($entry['title'] ?? 'Ohne Titel', ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                                <?php if (!empty($entry['excerpt'])): ?>
                                    <p><?php echo htmlspecialchars($entry['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <div class="meta">
                                    <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($entry['updated_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel">
                    <h2>Lesen</h2>
                    <p class="note-copy">Ein Klick auf einen Eintrag oeffnet den Beitrag direkt als ruhige Overlay-Ansicht auf dieser Seite.</p>
                    <?php if ($activePost): ?>
                        <p class="meta">Aktiv: <a class="open-post" href="?post=<?php echo urlencode($activePost['slug'] ?? ''); ?>" data-open-post="<?php echo htmlspecialchars($activePost['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($activePost['title'] ?? 'Ohne Titel', ENT_QUOTES, 'UTF-8'); ?></a></p>
                    <?php endif; ?>
                </article>
            </section>
        </div>
    </main>

    <footer style="width:min(1040px,100%);margin:14px auto 96px;padding:0 6px;color:rgba(255,255,255,.55);font-size:12px;line-height:1.5;position:relative;z-index:1;display:flex;flex-wrap:wrap;gap:14px;">
        <a href="/ueber-mich.html" style="color:inherit;text-decoration:none;">Ueber mich</a>
        <a href="/kontakt.html" style="color:inherit;text-decoration:none;">Kontakt</a>
        <a href="/impressum.html" style="color:inherit;text-decoration:none;">Impressum</a>
        <a href="/datenschutz.html" style="color:inherit;text-decoration:none;">Datenschutz</a>
    </footer>

    <nav class="task-dock" id="task-dock" aria-label="Schnellnavigation">
        <div class="task-dock-shell">
            <a href="/index.html" class="dock-start" aria-label="Zur Startseite"><img src="/assets/logo.svg" alt="Logo"></a>
            <span class="dock-sep" aria-hidden="true"></span>

            <a href="/index.html" class="dock-item" data-target="home" aria-label="Home">
                <span class="dock-icon" aria-hidden="true">◎</span>
                <span class="dock-label">Home</span>
            </a>

            <a href="/blog.php" class="dock-item active" data-target="blog" aria-label="Blog" aria-current="page">
                <span class="dock-icon" aria-hidden="true">✎</span>
                <span class="dock-label">Blog</span>
            </a>

            <a href="/projects.html" class="dock-item" data-target="projects" aria-label="Projekte">
                <span class="dock-icon" aria-hidden="true">⌘</span>
                <span class="dock-label">Projekte</span>
            </a>

            <a href="/ueber-mich.html" class="dock-item" data-target="about" aria-label="Ueber mich">
                <span class="dock-icon" aria-hidden="true">◉</span>
                <span class="dock-label">Ueber</span>
            </a>

            <a href="/kontakt.html" class="dock-item" data-target="contact" aria-label="Kontakt">
                <span class="dock-icon" aria-hidden="true">⌁</span>
                <span class="dock-label">Kontakt</span>
            </a>
        </div>
    </nav>

        <!-- CONTEXT MENU -->
    <div id="context-menu" class="context-menu">
        <div class="context-menu-item" data-action="home">
            <span class="context-menu-icon">⚡</span>
            <span>~/root</span>
        </div>
        <div class="context-menu-item" data-action="about">
            <span class="context-menu-icon">👤</span>
            <span>whoami</span>
        </div>
        <div class="context-menu-item" data-action="projects" id="context-projects">
            <span class="context-menu-icon">📂</span>
            <span>./repositories</span>
        </div>

        <div class="context-menu-item divider"></div>

        <div class="context-menu-item" data-action="contact">
            <span class="context-menu-icon">📡</span>
            <span>ping --remote</span>
        </div>
        <div class="context-menu-item" data-action="tech">
            <span class="context-menu-icon">🛠️</span>
            <span>view --stack</span>
        </div>

        <div class="context-menu-item divider"></div>

        <div class="context-menu-item" data-action="refresh">
            <span class="context-menu-icon">⚠️</span>
            <span>systemctl reboot</span>
        </div>
    </div>
    
    <div class="post-modal <?php echo $activePost ? 'is-open' : ''; ?>" id="post-modal" aria-hidden="<?php echo $activePost ? 'false' : 'true'; ?>">
        <a class="post-modal-backdrop-close" href="/blog.php" aria-label="Detailansicht schliessen"></a>
        <article class="post-modal-window" role="dialog" aria-modal="true" aria-labelledby="post-modal-heading">
            <div class="post-modal-topbar">
                <a class="dot-control red" id="post-modal-red-close" href="/blog.php" aria-label="Detailansicht schliessen"></a>
                <span class="dot yellow"></span>
                <span class="dot green"></span>
                <span class="post-modal-title">blog-post -- preview</span>
            </div>
            <div class="post-modal-meta" id="post-modal-meta">
                zuletzt aktualisiert: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($activePost['updated_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="post-modal-content">
                <h3 id="post-modal-heading"><?php echo htmlspecialchars($activePost['title'] ?? 'Ohne Titel', ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="article" id="post-modal-body"><?php echo $activePost ? renderBlogContent($activePost['content'] ?? '') : ''; ?></div>
            </div>
        </article>
    </div>

    <script>
        const posts = <?php echo json_encode($postsForClient, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        const modal = document.getElementById('post-modal')
        const modalWindow = document.querySelector('.post-modal-window')
        const modalRedClose = document.getElementById('post-modal-red-close')
        const modalHeading = document.getElementById('post-modal-heading')
        const modalMeta = document.getElementById('post-modal-meta')
        const modalBody = document.getElementById('post-modal-body')
        const postLinks = document.querySelectorAll('[data-open-post]')

        function setModalState(isOpen) {
            modal.classList.toggle('is-open', isOpen)
            modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true')
            document.body.classList.toggle('modal-open', isOpen)
        }

        function openPost(slug, shouldPushState = true) {
            if (!Object.prototype.hasOwnProperty.call(posts, slug)) {
                return
            }

            const post = posts[slug]
            modalHeading.textContent = post.title
            modalMeta.textContent = `zuletzt aktualisiert: ${post.updated_at}`
            modalBody.innerHTML = post.content_html
            setModalState(true)

            if (shouldPushState) {
                const nextUrl = `${window.location.pathname}?post=${encodeURIComponent(slug)}`
                try {
                    window.history.pushState({ post: slug }, '', nextUrl)
                } catch (error) {
                    window.location.assign(nextUrl)
                }
            }
        }

        function closeModal(shouldPushState = true) {
            setModalState(false)
            if (shouldPushState) {
                try {
                    window.history.replaceState({}, '', window.location.pathname)
                } catch (error) {
                    window.location.assign(window.location.pathname)
                }
            }
        }

        window.__closeBlogModal = closeModal

        postLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                const slug = link.dataset.openPost || ''
                if (!slug || !Object.prototype.hasOwnProperty.call(posts, slug)) {
                    return
                }

                event.preventDefault()
                openPost(slug)
            })
        })

        if (modalRedClose) {
            modalRedClose.addEventListener('click', (event) => {
                event.preventDefault()
                closeModal()
                window.location.assign('/blog.php')
            })
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return
            }

            const active = document.activeElement
            if (!active || !active.matches('[data-open-post]')) {
                return
            }

            const slug = active.dataset.openPost || ''
            if (!slug || !Object.prototype.hasOwnProperty.call(posts, slug)) {
                return
            }

            event.preventDefault()
            openPost(slug)
        })

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal()
            }
        })

        window.addEventListener('popstate', () => {
            const params = new URLSearchParams(window.location.search)
            const slug = params.get('post') || ''

            if (slug && Object.prototype.hasOwnProperty.call(posts, slug)) {
                openPost(slug, false)
                return
            }

            setModalState(false)
        })

        if (modal.classList.contains('is-open')) {
            document.body.classList.add('modal-open')
        }

        const taskDock = document.getElementById('task-dock')

        function updateTaskDockVisibility() {
            if (!taskDock) return
            const isMobile = window.matchMedia('(max-width: 860px)').matches
            if (!isMobile) {
                return
            }

            const maxScroll = Math.max(document.documentElement.scrollHeight - window.innerHeight, 0)
            taskDock.classList.toggle('visible', maxScroll <= 24 || window.scrollY > 40)
        }

        window.addEventListener('scroll', updateTaskDockVisibility, { passive: true })
        updateTaskDockVisibility()
    </script>
    <script src="/assets/context-menu.js"></script>
</body>

</html>

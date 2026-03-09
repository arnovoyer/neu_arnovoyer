(function () {
    const contextMenu = document.getElementById('context-menu');
    if (!contextMenu) {
        return;
    }

    const menuItems = contextMenu.querySelectorAll('.context-menu-item:not(.divider)');

    function hideMenu() {
        contextMenu.classList.remove('visible');
    }

    function showMenu(x, y) {
        contextMenu.classList.add('visible');
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';

        requestAnimationFrame(() => {
            const rect = contextMenu.getBoundingClientRect();

            if (rect.right > window.innerWidth) {
                contextMenu.style.left = Math.max(8, x - rect.width) + 'px';
            }

            if (rect.bottom > window.innerHeight) {
                contextMenu.style.top = Math.max(8, y - rect.height) + 'px';
            }
        });
    }

    function navigate(target) {
        if (target === 'refresh') {
            window.location.reload();
            return;
        }

        const routes = {
            home: '/index.html',
            about: '/index.html#about',
            projects: '/projects.html',
            contact: '/index.html#contact',
            tech: '/index.html#about'
        };

        const href = routes[target] || '/index.html';
        window.location.assign(href);
    }

    document.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        showMenu(event.clientX, event.clientY);
    });

    document.addEventListener('click', hideMenu);
    window.addEventListener('resize', hideMenu);
    window.addEventListener('scroll', hideMenu, { passive: true });

    menuItems.forEach((item) => {
        item.addEventListener('click', (event) => {
            event.stopPropagation();
            hideMenu();
            navigate(item.getAttribute('data-action'));
        });
    });
})();

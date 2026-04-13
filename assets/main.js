(function () {
  const header = document.querySelector('[data-header]');
  const menuBtn = document.querySelector('[data-menu-btn]');
  const nav = document.querySelector('[data-nav]');

  if (header && menuBtn && nav) {
    menuBtn.addEventListener('click', function () {
      header.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', header.classList.contains('open') ? 'true' : 'false');
    });

    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        header.classList.remove('open');
        menuBtn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  const revealNodes = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });

    revealNodes.forEach(function (node) { io.observe(node); });
  } else {
    revealNodes.forEach(function (node) { node.classList.add('visible'); });
  }

  const sentMessage = document.querySelector('[data-sent-message]');
  if (sentMessage) {
    const params = new URLSearchParams(window.location.search);
    const sent = params.get('sent');

    if (sent === '1' || sent === '0') {
      const isSuccess = sent === '1';
      const text = params.get('msg');

      sentMessage.hidden = false;
      sentMessage.textContent = text || (isSuccess
        ? 'Danke, deine Nachricht wurde erfolgreich gesendet.'
        : 'Beim Senden ist ein Fehler aufgetreten. Bitte versuche es erneut.');
      sentMessage.classList.toggle('is-success', isSuccess);
      sentMessage.classList.toggle('is-error', !isSuccess);

      if (window.history && typeof window.history.replaceState === 'function') {
        const cleanUrl = window.location.pathname + window.location.hash;
        window.history.replaceState({}, document.title, cleanUrl);
      }
    }
  }

})();

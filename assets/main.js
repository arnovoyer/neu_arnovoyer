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

  // Split text into characters for staggered reveal
  function splitText(node) {
    if (!node || node.dataset._splitDone) return;
    const text = node.textContent || '';
    if (!text.trim()) return;
    node.dataset._splitDone = '1';
    // preserve leading/trailing whitespace by trimming then re-adding space nodes
    const chars = Array.from(text);
    node.textContent = '';
    chars.forEach(function (ch, i) {
      const span = document.createElement('span');
      span.className = 'char';
      span.textContent = ch;
      span.style.setProperty('--i', i);
      node.appendChild(span);
    });
  }

  // Prepare split for elements marked with data-split or .split-reveal
  const splitNodes = document.querySelectorAll('[data-split], .split-reveal');
  splitNodes.forEach(function (n) { splitText(n); });

  // Animated send button behavior (show spinner briefly before submit)
  const contactForm = document.querySelector('.contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function (ev) {
      const btn = contactForm.querySelector('.btn.primary');
      if (!btn) return;
      ev.preventDefault();
      btn.disabled = true;
      btn.classList.add('sending');
      // small UX delay to show spinner, then submit
      setTimeout(function () { contactForm.submit(); }, 350);
    });
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

  (function initGitHubCard() {
    const card = document.querySelector('[data-github-card]');
    if (!card) return;

    const stats = {
      repos: card.querySelector('[data-stat="repos"]'),
      latest: card.querySelector('[data-stat="latest"]'),
      following: card.querySelector('[data-stat="following"]')
    };

    const githubUser = 'arnovoyer';
    const cacheKey = 'github-card-cache-v1';
    const cacheTtl = 60 * 60 * 1000;

    function formatNumber(value) {
      return new Intl.NumberFormat('de-AT').format(value);
    }

    function formatTimeAgo(dateStr) {
      if (!dateStr) return '—';
      const date = new Date(dateStr);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);
      
      if (diffMins < 1) return 'now';
      if (diffMins < 60) return diffMins + 'm ago';
      if (diffHours < 24) return diffHours + 'h ago';
      if (diffDays < 30) return diffDays + 'd ago';
      return date.toLocaleDateString('de-AT', { month: 'short', day: 'numeric' });
    }

    function setStats(data) {
      if (stats.repos) stats.repos.textContent = formatNumber(data.public_repos || 0);
      if (stats.following) stats.following.textContent = formatNumber(data.following || 0);
      if (stats.latest) {
        const latestDate = data.pushed_at || data.updated_at;
        stats.latest.textContent = formatTimeAgo(latestDate);
      }
    }

    function readCache() {
      try {
        const raw = localStorage.getItem(cacheKey);
        if (!raw) return null;
        const cached = JSON.parse(raw);
        if (!cached || !cached.data || !cached.timestamp) return null;
        if (Date.now() - cached.timestamp > cacheTtl) return null;
        return cached.data;
      } catch (error) {
        return null;
      }
    }

    function writeCache(data) {
      try {
        localStorage.setItem(cacheKey, JSON.stringify({ timestamp: Date.now(), data: data }));
      } catch (error) {
        // ignore storage failures
      }
    }

    const cached = readCache();
    if (cached) {
      setStats(cached);
    }

    fetch('https://api.github.com/users/' + githubUser, {
      headers: { 'Accept': 'application/vnd.github+json' }
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('GitHub API request failed');
        }
        return response.json();
      })
      .then(function (data) {
        setStats(data);
        writeCache(data);
      })
      .catch(function () {
        if (!cached) {
          if (stats.repos) stats.repos.textContent = '—';
          if (stats.latest) stats.latest.textContent = '—';
          if (stats.following) stats.following.textContent = '—';
        }
      });
  })();

  // Rotating conic-gradient angle updater for button(s) with class `rotating`
  (function startRotatingAngle(){
    let angle = 0;
    function step(){
      angle = (angle + 2.4) % 360;
      // set a global CSS variable so both buttons and input wrappers can use it
      document.documentElement.style.setProperty('--angle', angle + 'deg');
      requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  })();

  // Cursor-tracking glow effect for workflow section
  (function initWorkflowGlow() {
    const workflow = document.querySelector('[data-workflow]');
    const glow = document.querySelector('[data-workflow-glow]');
    const nodes = workflow ? workflow.querySelectorAll('.workflow-node') : [];
    
    if (!workflow || !glow || nodes.length === 0) return;
    
    let mouseX = 0;
    let mouseY = 0;
    let glowX = 0;
    let glowY = 0;
    let isActive = false;
    
    workflow.addEventListener('mousemove', function(e) {
      const rect = workflow.getBoundingClientRect();
      mouseX = e.clientX - rect.left;
      mouseY = e.clientY - rect.top;
      isActive = true;
      glow.style.opacity = '0.8';
      
      nodes.forEach(function(node) {
        const nodeRect = node.getBoundingClientRect();
        const nodeCenterX = nodeRect.left - rect.left + nodeRect.width / 2;
        const nodeCenterY = nodeRect.top - rect.top + nodeRect.height / 2;
        const dx = mouseX - nodeCenterX;
        const dy = mouseY - nodeCenterY;
        const distance = Math.sqrt(dx * dx + dy * dy);
        const mx = (dx / distance) * Math.min(distance, 24);
        const my = (dy / distance) * Math.min(distance, 24);
        node.style.setProperty('--mx', (50 + (mx / nodeRect.width) * 50) + '%');
        node.style.setProperty('--my', (50 + (my / nodeRect.height) * 50) + '%');
      });
    });
    
    workflow.addEventListener('mouseleave', function() {
      isActive = false;
      glow.style.opacity = '0';
      nodes.forEach(function(node) {
        node.style.setProperty('--mx', '50%');
        node.style.setProperty('--my', '50%');
      });
    });
    
    function animateGlow() {
      if (isActive) {
        glowX += (mouseX - glowX) * 0.18;
        glowY += (mouseY - glowY) * 0.18;
        glow.style.transform = 'translate(' + (glowX - 40) + 'px, ' + (glowY - 40) + 'px)';
      }
      requestAnimationFrame(animateGlow);
    }
    requestAnimationFrame(animateGlow);
  })();

  // Scroll-spy navigation: highlight current section
  (function initScrollSpy(){
    const sections = document.querySelectorAll('main section[id]');
    const navLinks = document.querySelectorAll('[data-nav] a');
    if (!sections.length || !navLinks.length) return;

    const spy = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          const id = entry.target.id;
          navLinks.forEach(function(a){
            const href = a.getAttribute('href') || '';
            a.classList.toggle('active', href === '#' + id);
          });
        }
      });
    }, { threshold: 0.5 });

    sections.forEach(function(s){ spy.observe(s); });
  })();

  // Animated counters for stats
  (function initCounters(){
    const counters = document.querySelectorAll('.stat-value');
    if (!counters.length || !('IntersectionObserver' in window)) return;

    const runCounter = function(el){
      const target = parseInt(el.getAttribute('data-target') || '0', 10);
      const duration = 1200;
      let start = null;
      function step(ts){
        if (!start) start = ts;
        const progress = Math.min((ts - start) / duration, 1);
        el.textContent = Math.floor(progress * target);
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target + (target >= 100 ? '' : '');
      }
      requestAnimationFrame(step);
    };

    const observer = new IntersectionObserver(function(entries, obs){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          runCounter(entry.target);
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });

    counters.forEach(function(c){ observer.observe(c); });
  })();

  // Ripple effect for buttons
  (function initButtonRipple(){
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.btn');
      if (!btn) return;
      const rect = btn.getBoundingClientRect();
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      const size = Math.max(rect.width, rect.height) * 0.6;
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
      ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
      btn.appendChild(ripple);
      setTimeout(function(){ ripple.remove(); }, 650);
    }, { passive: true });
  })();

  // Case-study before/after comparison
  (function initCaseStudy(){
    const compare = document.querySelector('[data-compare]');
    if (!compare) return;

    const stage = compare.querySelector('[data-compare-stage]');
    const before = compare.querySelector('[data-compare-before]');
    const after = compare.querySelector('[data-compare-after]');
    const range = compare.querySelector('[data-compare-range]');
    const buttons = compare.querySelectorAll('[data-compare-btn]');

    if (!stage || !before || !after || !range || !buttons.length) return;

    const sources = {
      news: {
        before: 'assets/rvhard-news-before.jpeg',
        after: 'assets/rvhard-news-after.jpeg'
      },
      events: {
        before: 'assets/rvhard-events-before.jpeg',
        after: 'assets/rvhard-events-after.jpeg'
      },
      main: {
        before: 'assets/rvhard-before.jpeg',
        after: 'assets/rvhard-after.jpeg'
      }
    };

    const labels = {
      news: ['RV Hard News vor dem Redesign', 'RV Hard News nach dem Redesign'],
      events: ['RV Hard Events vor dem Redesign', 'RV Hard Events nach dem Redesign'],
      main: ['RV Hard Hauptseite vor dem Redesign', 'RV Hard Hauptseite nach dem Redesign']
    };

    function setActive(key) {
      const source = sources[key];
      if (!source) return;
      before.src = source.before;
      after.src = source.after;
      before.alt = labels[key][0];
      after.alt = labels[key][1];
      buttons.forEach(function(btn){
        const active = btn.getAttribute('data-compare-btn') === key;
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }

    function setSplit(value) {
      stage.style.setProperty('--split', value + '%');
      // JS fallback: also set handle position directly in case CSS variable isn't picked up
      const handle = compare.querySelector('.compare-handle');
      if (handle) handle.style.left = value + '%';
    }

    buttons.forEach(function(button) {
      button.addEventListener('click', function() {
        setActive(button.getAttribute('data-compare-btn'));
      });
    });

    range.addEventListener('input', function() {
      setSplit(range.value);
    });

    setActive('main');
    setSplit(range.value || 50);
  })();

  // Cursor-tracking glow for signal tiles
  (function initSignalTileGlow() {
    const board = document.querySelector('[data-signal-board]');
    const tiles = board ? board.querySelectorAll('.signal-tile') : [];
    
    if (!board || tiles.length === 0) return;
    
    board.addEventListener('mousemove', function(e) {
      const rect = board.getBoundingClientRect();
      const boardX = e.clientX - rect.left;
      const boardY = e.clientY - rect.top;
      
      tiles.forEach(function(tile) {
        const tileRect = tile.getBoundingClientRect();
        const tileCenterX = tileRect.left - rect.left + tileRect.width / 2;
        const tileCenterY = tileRect.top - rect.top + tileRect.height / 2;
        const dx = boardX - tileCenterX;
        const dy = boardY - tileCenterY;
        const distance = Math.sqrt(dx * dx + dy * dy);
        const maxDist = Math.max(tileRect.width, tileRect.height) * 1.2;
        
        if (distance < maxDist) {
          const mx = (dx / distance) * Math.min(distance, 28);
          const my = (dy / distance) * Math.min(distance, 28);
          tile.style.setProperty('--sx', (50 + (mx / tileRect.width) * 50) + '%');
          tile.style.setProperty('--sy', (50 + (my / tileRect.height) * 50) + '%');
        } else {
          tile.style.setProperty('--sx', '50%');
          tile.style.setProperty('--sy', '50%');
        }
      });
    });
    
    board.addEventListener('mouseleave', function() {
      tiles.forEach(function(tile) {
        tile.style.setProperty('--sx', '50%');
        tile.style.setProperty('--sy', '50%');
      });
    });
  })();

  // Focus-tracking glow for form inputs
  (function initInputFocusGlow() {
    const wraps = document.querySelectorAll('[data-input-glow]');
    
    wraps.forEach(function(wrap) {
      const input = wrap.querySelector('input, textarea');
      if (!input) return;
      
      input.addEventListener('focus', function() {
        wrap.style.setProperty('--ix', '50%');
        wrap.style.setProperty('--iy', '50%');
      });
      
      wrap.addEventListener('mousemove', function(e) {
        const rect = wrap.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        wrap.style.setProperty('--ix', (x / rect.width) * 100 + '%');
        wrap.style.setProperty('--iy', (y / rect.height) * 100 + '%');
      });
      
      input.addEventListener('blur', function() {
        wrap.style.setProperty('--ix', '50%');
        wrap.style.setProperty('--iy', '50%');
      });
    });
  })();

})();

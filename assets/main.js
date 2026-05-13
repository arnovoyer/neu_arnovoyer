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

  (function initWorkflowGlow() {
    const workflow = document.querySelector('[data-workflow]');
    if (!workflow) return;

    function updateGlow(event) {
      const rect = workflow.getBoundingClientRect();
      const x = ((event.clientX - rect.left) / rect.width) * 100;
      const y = ((event.clientY - rect.top) / rect.height) * 100;
      workflow.style.setProperty('--mx', x + '%');
      workflow.style.setProperty('--my', y + '%');

      const glowEl = workflow.parentElement.querySelector('[data-workflow-glow]');
      if (glowEl) {
        // Berechne Position relativ zur Section (Parent des glow-Elements)
        const sectionRect = workflow.parentElement.getBoundingClientRect();
        glowEl.style.setProperty('--glow-x', (event.clientX - sectionRect.left) + 'px');
        glowEl.style.setProperty('--glow-y', (event.clientY - sectionRect.top) + 'px');
        glowEl.style.opacity = '0.22';
      }
    }

    workflow.addEventListener('mousemove', updateGlow);
    workflow.addEventListener('mouseleave', function () {
      workflow.style.setProperty('--mx', '50%');
      workflow.style.setProperty('--my', '50%');
      const glowEl = workflow.parentElement.querySelector('[data-workflow-glow]');
      if (glowEl) {
        glowEl.style.opacity = '0';
      }
    });
  })();

  (function initGitHubStats() {
    const card = document.querySelector('[data-github-stats]');
    if (!card) return;

    const latestCommitEl = card.querySelector('[data-stat="latest-commit"]');
    const languagesEl = card.querySelector('[data-stat="languages"]');
    const topReposEl = card.querySelector('[data-stat="top-repos"]');

    const githubUser = 'arnovoyer';
    const cacheKey = 'github-stats-cache-v2';
    const cacheTtl = 60 * 60 * 1000;

    function getCached() {
      try {
        const cached = localStorage.getItem(cacheKey);
        if (!cached) return null;
        const data = JSON.parse(cached);
        if (!data || !data.repos || !data.timestamp) return null;
        if (Date.now() - data.timestamp > cacheTtl) {
          localStorage.removeItem(cacheKey);
          return null;
        }
        return data.repos;
      } catch (error) {
        return null;
      }
    }

    function setCached(repos) {
      try {
        localStorage.setItem(cacheKey, JSON.stringify({
          repos: repos,
          timestamp: Date.now()
        }));
      } catch (error) {
        // ignore
      }
    }

    function formatDate(dateStr) {
      if (!dateStr) return '—';
      const date = new Date(dateStr);
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const mins = String(date.getMinutes()).padStart(2, '0');
      return `${day}.${month}. ${hours}:${mins}`;
    }

    function getLanguageColor(lang) {
      const colors = {
        JavaScript: '#f1e05a',
        PHP: '#777bb4',
        HTML: '#e34c26',
        CSS: '#563d7c',
        TypeScript: '#2b7489',
        Python: '#3572A5',
        Java: '#b07219',
        Go: '#00ADD8',
        Rust: '#ce422b',
        Ruby: '#cc342d'
      };
      return colors[lang] || '#858585';
    }

    function renderLanguages(repos) {
      const languages = {};
      let totalSize = 0;

      repos.forEach(function (repo) {
        if (repo.language) {
          languages[repo.language] = (languages[repo.language] || 0) + (repo.size || 1);
          totalSize += repo.size || 1;
        }
      });

      const topLangs = Object.entries(languages)
        .sort(function (a, b) { return b[1] - a[1]; })
        .slice(0, 5);

      languagesEl.innerHTML = topLangs.map(function (entry) {
        const lang = entry[0];
        const size = entry[1];
        const percent = totalSize ? Math.round((size / totalSize) * 100) : 0;
        const color = getLanguageColor(lang);
        return '<div class="lang-tag"><span class="lang-icon" style="background-color: ' + color + ';"></span><span>' + lang + ' <strong>' + percent + '%</strong></span></div>';
      }).join('');
    }

    function renderTopRepos(repos) {
      const topRepos = repos
        .filter(function (repo) { return !repo.fork; })
        .sort(function (a, b) { return (b.stargazers_count || 0) - (a.stargazers_count || 0); })
        .slice(0, 3);

      topReposEl.innerHTML = topRepos.map(function (repo) {
        // GitHub Octocat icon in accent color
        return '<a href="' + repo.html_url + '" target="_blank" rel="noopener noreferrer" class="repo-link repo-link-soft">' +
          '<svg class="repo-icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">' +
          '<path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>' +
          '</svg><span class="repo-name">' + repo.name + '</span></a>';
      }).join('');
    }

    function renderLatestCommit(repos) {
      let latestDate = null;
      repos.forEach(function (repo) {
        if (repo.pushed_at) {
          const pushDate = new Date(repo.pushed_at);
          if (!latestDate || pushDate > latestDate) {
            latestDate = pushDate;
          }
        }
      });

      if (latestCommitEl) {
        latestCommitEl.textContent = latestDate ? formatDate(latestDate.toISOString()) : '—';
      }
    }

    function setData(repos) {
      renderLatestCommit(repos);
      renderLanguages(repos);
      renderTopRepos(repos);
    }

    function setFallbackData() {
      if (latestCommitEl) latestCommitEl.textContent = 'Lokaler Aufruf blockiert';
      if (languagesEl) {
        languagesEl.innerHTML = [
          '<div class="lang-tag"><span class="lang-icon" style="background-color: #f1e05a;"></span><span>JavaScript <strong>50%</strong></span></div>',
          '<div class="lang-tag"><span class="lang-icon" style="background-color: #777bb4;"></span><span>PHP <strong>30%</strong></span></div>',
          '<div class="lang-tag"><span class="lang-icon" style="background-color: #e34c26;"></span><span>HTML <strong>20%</strong></span></div>'
        ].join('');
      }
      if (topReposEl) {
        topReposEl.innerHTML = [
          '<a href="#" class="repo-link repo-link-soft"><svg class="repo-icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg><span class="repo-name">Projekt A</span></a>',
          '<a href="#" class="repo-link repo-link-soft"><svg class="repo-icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg><span class="repo-name">Projekt B</span></a>',
          '<a href="#" class="repo-link repo-link-soft"><svg class="repo-icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg><span class="repo-name">Projekt C</span></a>'
        ].join('');
      }
    }

    if (window.location.protocol === 'file:') {
      setFallbackData();
      return;
    }

    const cached = getCached();
    if (cached) {
      setData(cached);
    }

    fetch('https://api.github.com/users/' + githubUser + '/repos?per_page=100', {
      headers: { 'Accept': 'application/vnd.github+json' }
    })
      .then(function (res) {
        if (!res.ok) throw new Error('API error');
        return res.json();
      })
      .then(function (repos) {
        setCached(repos);
        setData(repos);
      })
      .catch(function () {
        if (!cached) {
          setFallbackData();
        }
      });
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
    const orb = board ? board.querySelector('[data-work-orb]') : null;
    
    if (!board || tiles.length === 0) return;
    
    board.addEventListener('mousemove', function(e) {
      const rect = board.getBoundingClientRect();
      const boardX = e.clientX - rect.left;
      const boardY = e.clientY - rect.top;
      const hoveredTile = e.target.closest('.signal-tile');

      if (orb) {
        orb.style.left = boardX + 'px';
        orb.style.top = boardY + 'px';
        orb.style.opacity = '1';
      }
      
      tiles.forEach(function(tile) {
        if (tile === hoveredTile) {
          const tileRect = tile.getBoundingClientRect();
          const tileX = ((e.clientX - tileRect.left) / tileRect.width) * 100;
          const tileY = ((e.clientY - tileRect.top) / tileRect.height) * 100;
          tile.style.setProperty('--sx', tileX + '%');
          tile.style.setProperty('--sy', tileY + '%');
        } else {
          tile.style.setProperty('--sx', '50%');
          tile.style.setProperty('--sy', '50%');
        }
      });
    });
    
    board.addEventListener('mouseleave', function() {
      if (orb) {
        orb.style.opacity = '0';
      }
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

  // Enhanced cursor proximity effects for interactive elements
  (function initCursorProximity() {
    const interactiveElements = document.querySelectorAll('.btn, .repo-link, .social-link, a[href^="#"]');
    
    document.addEventListener('mousemove', function(e) {
      interactiveElements.forEach(function(el) {
        const rect = el.getBoundingClientRect();
        const elCenterX = rect.left + rect.width / 2;
        const elCenterY = rect.top + rect.height / 2;
        
        const dx = e.clientX - elCenterX;
        const dy = e.clientY - elCenterY;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        // Proximity effect: affect within 120px radius
        const proximityRadius = 120;
        if (distance < proximityRadius) {
          const intensity = 1 - (distance / proximityRadius);
          el.style.setProperty('--proximity', intensity.toFixed(2));
          
          // Subtle rotation/tilt based on proximity
          const angleX = (dy / proximityRadius) * 4;
          const angleY = (dx / proximityRadius) * 4;
          el.style.transform = 'perspective(1000px) rotateX(' + angleX + 'deg) rotateY(' + angleY + 'deg)';
        } else {
          el.style.setProperty('--proximity', '0');
          el.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
        }
      });
    }, { passive: true });
  })();

})();

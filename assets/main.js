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

})();

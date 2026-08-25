/**
 * NexABI — shared script for the static pages (landing, auth, client portal).
 *
 * The console is a module SPA; these pages are plain HTML and only need four
 * things: icons, the theme toggle, scroll reveals, and a tiny session shim so
 * "signing in" routes to the right place.
 */

/* ------------------------------------------------------------------ icons */

const ICONS = {
  spark:    'M13 2L3 14h9l-1 8 10-12h-9z',
  insight:  'M12 2a7 7 0 0 0-4 12.7V17a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.3A7 7 0 0 0 12 2zM9 21h6',
  arrow:    'M5 12h14M13 6l6 6-6 6',
  right:    'M9 18l6-6-6-6',
  left:     'M15 18l-6-6 6-6',
  check:    'M20 6L9 17l-5-5',
  home:     'M3 10l9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10',
  chart:    'M3 3v18h18M7 15l4-5 3 3 5-7',
  users:    'M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
  wallet:   'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
  doc:      'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M9 13h6M9 17h6',
  chat:     'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  ask:      'M12 2a10 10 0 1 0 4.6 18.9L21 22l-1.1-4.4A10 10 0 0 0 12 2zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01',
  moon:     'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
  sun:      'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
  clock:    'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
  shield:   'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
  lock:     'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
  mail:     'M4 4h16v16H4zM4 6l8 6 8-6',
  info:     'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 16v-4M12 8h.01',
  warn:     'M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01',
  key:      'M21 2l-2 2m-7.6 7.6a5 5 0 1 1-7 7 5 5 0 0 1 7-7zm0 0L15 8m0 0l3 3 3-3-3-3',
  layers:   'M12 2L2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
  building: 'M4 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18M16 8h3a2 2 0 0 1 2 2v12M8 6h2M8 10h2M8 14h2M8 18h2',
  pin:      'M12 22s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
  trophy:   'M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.7V17a2 2 0 0 1-2 2M14 14.7V17a2 2 0 0 0 2 2M18 2H6v7a6 6 0 0 0 12 0z',
};

export function iconSvg(name, size = 18) {
  const d = ICONS[name] || ICONS.info;
  return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none"
    stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
    stroke-linejoin="round" aria-hidden="true"><path d="${d}"/></svg>`;
}

function paintIcons(root = document) {
  root.querySelectorAll('[data-icon]').forEach(node => {
    if (node.dataset.painted) return;
    node.innerHTML = iconSvg(node.dataset.icon, +(node.dataset.size || 18));
    node.dataset.painted = '1';
  });
}

/* ------------------------------------------------------------------ theme */

/**
 * Theme lives in a cookie, not localStorage.
 *
 * The server needs to read it to stamp `data-theme` on <html> before any CSS
 * parses. Anything client-side produces a flash of the wrong background on
 * every navigation, which reads as a broken product.
 */
function readTheme() {
  const m = document.cookie.match(/(?:^|;\s*)nexabi_theme=(dark|light)/);
  return m ? m[1] : 'dark';
}

function writeTheme(theme) {
  document.cookie = `nexabi_theme=${theme};path=/;max-age=31536000;samesite=lax`;
}

export function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  document.querySelectorAll('[data-theme-toggle]').forEach(b => {
    b.innerHTML = iconSvg(theme === 'dark' ? 'sun' : 'moon', 16);
    b.title = `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`;
  });
  writeTheme(theme);
}

/* ---------------------------------------------------------------- session
   A demo shim, not authentication. It records which portal was chosen so the
   pages can greet the right person; it protects nothing and claims nothing. */

const SESSION_KEY = 'nexabi.session';

export function setSession(s) {
  try { localStorage.setItem(SESSION_KEY, JSON.stringify(s)); } catch {}
}
export function getSession() {
  try { return JSON.parse(localStorage.getItem(SESSION_KEY) || 'null'); } catch { return null; }
}
export function clearSession() {
  try { localStorage.removeItem(SESSION_KEY); } catch {}
}

/* ---------------------------------------------------------------- reveal */

function reveal() {
  if (!('IntersectionObserver' in window)) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      e.target.style.opacity = '1';
      e.target.style.transform = 'none';
      io.unobserve(e.target);
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

  document.querySelectorAll('[data-reveal]').forEach((n, i) => {
    n.style.opacity = '0';
    n.style.transform = 'translateY(14px)';
    n.style.transition = `opacity .55s cubic-bezier(.16,1,.3,1) ${(i % 4) * 60}ms,
                          transform .55s cubic-bezier(.16,1,.3,1) ${(i % 4) * 60}ms`;
    io.observe(n);
  });
}

/* --------------------------------------------------------------- counters */

function countUp() {
  document.querySelectorAll('[data-count]').forEach(node => {
    const target = parseFloat(node.dataset.count);
    const suffix = node.dataset.suffix || '';
    const dp = +(node.dataset.dp || 0);
    const dur = 1100;
    const t0 = performance.now();

    const tick = (t) => {
      const p = Math.min(1, (t - t0) / dur);
      const eased = 1 - Math.pow(1 - p, 3);
      node.textContent = (target * eased).toLocaleString('en-NG', {
        minimumFractionDigits: dp, maximumFractionDigits: dp,
      }) + suffix;
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });
}

/* ------------------------------------------------------------------- boot */

export function initSite() {
  applyTheme(readTheme());
  paintIcons();
  reveal();

  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-theme-toggle]');
    if (t) {
      applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    }
  });

  // Smooth in-page nav without losing keyboard focus.
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const id = a.getAttribute('href').slice(1);
      const target = id && document.getElementById(id);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      target.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
    });
  });

  if (document.querySelector('[data-count]')) {
    const io = new IntersectionObserver((en) => {
      if (en.some(x => x.isIntersecting)) { countUp(); io.disconnect(); }
    }, { threshold: 0.2 });
    io.observe(document.querySelector('[data-count]').closest('section') || document.body);
  }
}

export { paintIcons };

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSite);
} else {
  initSite();
}

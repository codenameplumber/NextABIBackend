/**
 * Client runtime.
 *
 * Livewire owns the data and the DOM. This file owns the three things that
 * genuinely belong on the client:
 *
 *   1. Charts   — 800-point SVG built in the browser rather than shipped as
 *                 markup over the wire.
 *   2. Theme    — must apply before first paint, so it is set from a cookie in
 *                 the <html> tag and only toggled here.
 *   3. Chrome   — sidebar collapse, toasts, keyboard shortcuts, the palette.
 *
 * Everything here is idempotent and re-runs after each Livewire DOM update,
 * because a component that re-renders must not lose its charts.
 */

import {
  lineChart, barChart, hBarChart, stackedBar, donut, funnelChart, ratingBars,
} from './charts.js';
import { el, icon, clear } from './dom.js';
import { monthLabel } from './format.js';

/* ---------------------------------------------------------------- charts */

const IDENTITY = (v) => v;

/**
 * Renders one `<div data-chart="line" data-payload="…">`.
 *
 * The payload comes from PHP, so `xLabel` cannot cross the wire as a function.
 * `xLabelMonth: true` opts a chart into month formatting; everything else gets
 * its labels through unchanged.
 */
function renderChart(host) {
  let payload;
  try {
    payload = JSON.parse(host.dataset.payload || '{}');
  } catch {
    return;
  }

  const kind = host.dataset.chart;
  const opts = { ...(payload.opts || {}) };
  opts.xLabel = opts.xLabelMonth ? monthLabel : IDENTITY;

  let node;
  try {
    switch (kind) {
      case 'line': node = lineChart(payload.series || [], opts); break;
      case 'bar': node = barChart(payload.data || [], opts); break;
      case 'hbar': node = hBarChart(payload.data || [], opts); break;
      case 'stacked': node = stackedBar(payload.categories || [], payload.series || [], opts); break;
      case 'donut': node = donut(payload.data || [], opts); break;
      case 'funnel': node = funnelChart(payload.stages || [], opts); break;
      case 'rating': node = ratingBars(payload.data || [], opts); break;
      default: return;
    }
  } catch (err) {
    console.error('[chart]', kind, err);
    node = el('div.empty', [el('div.empty__msg', { text: 'This chart could not be drawn.' })]);
  }

  clear(host);
  host.appendChild(node);
  host.dataset.drawn = host.dataset.payload.length + ':' + kind;
}

/** Draw every chart that has not been drawn, or whose data has changed. */
export function mountCharts(root = document) {
  root.querySelectorAll('[data-chart]').forEach((host) => {
    const stamp = (host.dataset.payload || '').length + ':' + host.dataset.chart;
    if (host.dataset.drawn === stamp) return;
    renderChart(host);
  });
}

/* ----------------------------------------------------------------- theme */

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  // Cookie, not localStorage — the server needs it to render the very first
  // paint in the right theme. A flash of the wrong background is a bug.
  document.cookie = `nexabi_theme=${theme};path=/;max-age=31536000;samesite=lax`;
  document.querySelectorAll('[data-theme-toggle]').forEach((b) => {
    clear(b);
    b.appendChild(icon(theme === 'dark' ? 'sun' : 'moon', 15));
    b.title = `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`;
  });
  // Charts read colour from CSS custom properties at build time, so a theme
  // change means a redraw.
  document.querySelectorAll('[data-chart]').forEach((h) => delete h.dataset.drawn);
  mountCharts();
}

export function toggleTheme() {
  applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
}

/* ---------------------------------------------------------------- toasts */

export function toast({ kind = '', title = '', msg = '', ms = 4600 }) {
  const host = document.getElementById('toasts');
  if (!host) return;

  const ico = kind === 'pos' ? 'check' : kind === 'neg' ? 'warn' : kind === 'ai' ? 'insight' : 'info';

  const node = el('div.toast', { class: kind && `toast--${kind}` }, [
    el('span.toast__icon', [icon(ico, 16)]),
    el('div.grow', [
      el('div.toast__title', { text: title }),
      msg && el('div.toast__msg', { text: msg }),
    ]),
  ]);

  host.appendChild(node);
  setTimeout(() => {
    node.classList.add('is-out');
    setTimeout(() => node.remove(), 220);
  }, ms);
}

/* --------------------------------------------------------------- chrome */

/**
 * Everything in here binds to `document`, so it must bind exactly once.
 *
 * boot() runs on DOMContentLoaded AND on every livewire:navigated, and without
 * this guard each soft navigation added another copy of these listeners. Two
 * copies of a TOGGLE cancel each other out: the sidebar button appeared to do
 * nothing at all after the first client-side navigation, on desktop as well as
 * on a phone. The only visible trace was the class attribute being rewritten
 * to an identical value.
 *
 * Re-running the DOM-scanning parts of boot() after a navigation is correct and
 * still happens. It is only the delegated listeners that must not stack.
 */
let chromeBound = false;

function bindChrome() {
  if (chromeBound) return;
  chromeBound = true;

  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-theme-toggle]');
    if (t) { e.preventDefault(); toggleTheme(); return; }

    const c = e.target.closest('[data-collapse]');
    if (c) {
      e.preventDefault();
      const app = document.getElementById('app');

      /* The same button does two different jobs.

         On a wide screen it narrows the sidebar to a rail, and that choice is
         remembered in a cookie because it is a preference. On a phone the
         sidebar is off-canvas and the button opens it — a drawer that stayed
         open across navigations would cover the page you just navigated to,
         so that state is deliberately NOT persisted. */
      if (isNarrow()) {
        app.classList.toggle('is-nav-open');
        return;
      }

      app.classList.toggle('is-collapsed');
      document.cookie = `nexabi_rail=${app.classList.contains('is-collapsed') ? 1 : 0};path=/;max-age=31536000;samesite=lax`;
    }

    // The scrim, and any nav link inside the open drawer.
    if (e.target.closest('[data-nav-close]') ||
        (isNarrow() && e.target.closest('.sidebar a'))) {
      document.getElementById('app')?.classList.remove('is-nav-open');
    }
  });

  // Escape closes it, and so does growing past the breakpoint — otherwise the
  // drawer state survives a rotation and the desktop layout renders with it on.
  addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.getElementById('app')?.classList.remove('is-nav-open');
  });

  addEventListener('resize', () => {
    if (!isNarrow()) document.getElementById('app')?.classList.remove('is-nav-open');
  });
}

/** The breakpoint the console shell collapses at. Kept in step with console.css. */
function isNarrow() {
  return window.matchMedia('(max-width: 900px)').matches;
}

function bindKeys() {
  addEventListener('keydown', (e) => {
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;

    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      document.querySelector('[data-palette-open]')?.click();
      return;
    }
    if (typing) return;

    if (e.key === '/') {
      e.preventDefault();
      document.querySelector('[data-palette-open]')?.click();
    }
    if (e.key === 't') toggleTheme();
    if (e.key === 'g') {
      // Two-key jumps, vim style: g then d, i, l, p, s, f, a, m.
      const map = {
        d: '/app', i: '/app/insights', l: '/app/leads', p: '/app/properties',
        s: '/app/sales', f: '/app/finance', a: '/app/ask', m: '/app/marketing',
      };
      const once = (ev) => {
        if (map[ev.key]) window.location.assign(map[ev.key]);
        removeEventListener('keydown', once);
      };
      addEventListener('keydown', once);
      setTimeout(() => removeEventListener('keydown', once), 1200);
    }
  });
}


/* ------------------------------------------------------------- copy-to-clipboard
   A realtor copies their link a dozen times a day, so the affordance is a
   click on the thing itself rather than a modal with a text field. Delegated,
   because Livewire replaces DOM subtrees wholesale and a bound handler would
   not survive the morph.                                                      */

function bindCopy() {
  if (document.__nexabiCopyBound) return;
  document.__nexabiCopyBound = true;

  document.addEventListener("click", async (e) => {
    const el = e.target.closest("[data-copy]");
    if (!el) return;

    const text = el.getAttribute("data-copy");
    if (!text) return;

    e.preventDefault();

    try {
      await navigator.clipboard.writeText(text);
    } catch {
      // Older browsers, and any page not served over TLS, refuse the
      // clipboard API outright. The fallback is ugly and always works.
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand("copy"); } catch { /* nothing left to try */ }
      document.body.removeChild(ta);
    }

    toast({ title: "Copied", msg: text.length > 54 ? text.slice(0, 51) + "\u2026" : text, kind: "pos" });
  });
}

/* ----------------------------------------------------------------- boot */

function boot() {
  mountCharts();
  bindChrome();
  bindKeys();
  bindCopy();

  // Light is the default the layouts stamp, so the toggle icon has to agree.
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  document.querySelectorAll('[data-theme-toggle]').forEach((b) => {
    clear(b);
    b.appendChild(icon(current === 'dark' ? 'sun' : 'moon', 15));
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

// Livewire replaces DOM subtrees wholesale; anything drawn imperatively has to
// be drawn again on the other side of a morph.
document.addEventListener('livewire:navigated', () => { boot(); });
document.addEventListener('livewire:init', () => {
  Livewire.hook('morphed', () => mountCharts());
  Livewire.on('toast', (payload) => toast(Array.isArray(payload) ? payload[0] : payload));
});

window.NexABI = { mountCharts, toast, toggleTheme };

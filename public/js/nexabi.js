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

function bindChrome() {
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-theme-toggle]');
    if (t) { e.preventDefault(); toggleTheme(); return; }

    const c = e.target.closest('[data-collapse]');
    if (c) {
      e.preventDefault();
      const app = document.getElementById('app');
      app.classList.toggle('is-collapsed');
      document.cookie = `nexabi_rail=${app.classList.contains('is-collapsed') ? 1 : 0};path=/;max-age=31536000;samesite=lax`;
    }
  });
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

/* ----------------------------------------------------------------- boot */

function boot() {
  mountCharts();
  bindChrome();
  bindKeys();

  const current = document.documentElement.getAttribute('data-theme') || 'dark';
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

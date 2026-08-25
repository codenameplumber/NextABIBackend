/**
 * DOM helpers + the shared component vocabulary.
 *
 * No framework. The convention that keeps this manageable at 13 modules:
 * builders return elements, views compose them, and anything a view subscribes
 * to is released in its unmount. Enforced from file one.
 */

import { esc, initials, hueOf } from './format.js';

/**
 * el('div.card', { onclick }, [child, 'text'])
 * el('select#topic.input', ...)   tag, #id and .classes, CSS-selector style
 *
 * The #id half is not a nicety. Without it `createElement('select#topic')` is
 * called verbatim, and browsers do NOT throw on that — they hand back an
 * unknown element named SELECT#TOPIC which is not a <select>, has no dropdown,
 * and fails silently. That cost a broken form on the support page.
 */
export function el(spec, props = {}, children = []) {
  // Tokenised rather than one big pattern, so #id and .class work in any order
  // and a malformed spec degrades to a <div> instead of throwing mid-render.
  const str = String(spec).trim();
  const tag = (str.match(/^[a-zA-Z][\w-]*/) || ['div'])[0];
  const id = (str.match(/#([^.#\s]+)/) || [])[1];
  const classes = [...str.matchAll(/\.([^.#\s]+)/g)].map(m => m[1]);

  const node = document.createElement(tag);
  if (id) node.id = id;
  if (classes.length) node.className = classes.join(' ');

  if (Array.isArray(props) || typeof props === 'string' || props instanceof Node) {
    children = props; props = {};
  }

  for (const [k, v] of Object.entries(props)) {
    if (v == null || v === false) continue;
    if (k === 'class') node.className += (node.className ? ' ' : '') + v;
    else if (k === 'html') node.innerHTML = v;
    else if (k === 'text') node.textContent = v;
    else if (k === 'style' && typeof v === 'object') Object.assign(node.style, v);
    else if (k === 'dataset') Object.assign(node.dataset, v);
    else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
    else if (k === 'value') node.value = v;
    else if (v === true) node.setAttribute(k, '');
    else node.setAttribute(k, v);
  }

  add(node, children);
  return node;
}

export function add(parent, children) {
  const list = Array.isArray(children) ? children : [children];
  list.flat(4).forEach(c => {
    if (c == null || c === false) return;
    parent.appendChild(c instanceof Node ? c : document.createTextNode(String(c)));
  });
  return parent;
}

export const frag = (children) => add(document.createDocumentFragment(), children);
export const clear = (node) => { node.innerHTML = ''; return node; };
export const $  = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

/** Inline SVG icon set — no icon font, no network request. */
const ICONS = {
  dashboard: 'M3 3h7v7H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 14h7v7H3z',
  insight: 'M12 2a7 7 0 0 0-4 12.7V17a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.3A7 7 0 0 0 12 2zM9 21h6',
  sales: 'M3 3v18h18M7 15l4-5 3 3 5-7',
  leads: 'M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
  property: 'M3 10l9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10',
  marketing: 'M3 11v3a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1zM16 8a5 5 0 0 1 0 8M19.5 5a9 9 0 0 1 0 14',
  customers: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM22 11l-3 3-2-2',
  finance: 'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
  feedback: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  analytics: 'M3 3v18h18M8 17V9M13 17V5M18 17v-5',
  reports: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M9 13h6M9 17h6',
  alerts: 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
  ask: 'M12 2a10 10 0 1 0 4.6 18.9L21 22l-1.1-4.4A10 10 0 0 0 12 2zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3',
  settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z',
  clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
  up: 'M7 14l5-5 5 5',
  down: 'M7 10l5 5 5-5',
  right: 'M9 18l6-6-6-6',
  left: 'M15 18l-6-6 6-6',
  check: 'M20 6L9 17l-5-5',
  x: 'M18 6L6 18M6 6l12 12',
  plus: 'M12 5v14M5 12h14',
  bolt: 'M13 2L3 14h9l-1 8 10-12h-9z',
  play: 'M5 3l14 9-14 9z',
  sun: 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
  moon: 'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
  panel: 'M3 3h18v18H3zM9 3v18',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
  upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
  reset: 'M1 4v6h6M23 20v-6h-6M20.5 9A9 9 0 0 0 5.6 5.6L1 10M23 14l-4.6 4.4A9 9 0 0 1 3.5 15',
  trophy: 'M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.7V17a2 2 0 0 1-2 2M14 14.7V17a2 2 0 0 0 2 2M18 2H6v7a6 6 0 0 0 12 0z',
  filter: 'M22 3H2l8 9.5V19l4 2v-8.5z',
  info: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 16v-4M12 8h.01',
  warn: 'M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01',
  eye: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8zM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
  agent: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z',
};

export function icon(name, size = 16) {
  const d = ICONS[name] || ICONS.info;
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('width', size);
  svg.setAttribute('height', size);
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '1.8');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.innerHTML = `<path d="${d}"/>`;
  return svg;
}

export const ICON_NAMES = Object.keys(ICONS);

/* ------------------------------------------------------------- components */

export function btn(label, opts = {}) {
  const { variant = '', size = '', ico, onclick, title, disabled, iconOnly } = opts;
  const node = el('button.btn', {
    class: [variant && `btn--${variant}`, size && `btn--${size}`, iconOnly && 'btn--icon']
      .filter(Boolean).join(' '),
    title: title || (iconOnly ? label : null),
    'aria-label': iconOnly ? label : null,
    disabled,
    onclick,
  }, [ico && icon(ico, size === 'sm' ? 13 : 15), !iconOnly && el('span', { text: label })]);
  return node;
}

export function badge(text, kind = '', { dot = false } = {}) {
  return el('span.badge', { class: [kind && `badge--${kind}`, dot && 'badge--dot'].filter(Boolean).join(' ') },
    [el('span', { text })]);
}

/** Signed change chip. `mode: 'pct' | 'pts' | 'raw'` */
export function deltaChip(value, { mode = 'pct', invert = false, suffix = '' } = {}) {
  if (value == null || !Number.isFinite(value)) return el('span.delta.delta--flat', [el('span', { text: '—' })]);
  const good = invert ? value < 0 : value > 0;
  const flat = Math.abs(value) < (mode === 'pts' ? 0.002 : 0.005);
  const cls = flat ? 'flat' : good ? 'up' : 'down';
  const n = mode === 'pts'
    ? `${value >= 0 ? '+' : '−'}${Math.abs(value * 100).toFixed(1)}pts`
    : mode === 'raw'
      ? `${value >= 0 ? '+' : '−'}${Math.abs(value)}${suffix}`
      : `${value >= 0 ? '+' : '−'}${Math.abs(value * 100).toFixed(1)}%`;
  return el(`span.delta.delta--${cls}`, [
    !flat && icon(value > 0 ? 'up' : 'down', 11),
    el('span', { text: n }),
  ]);
}

export function avatar(name, { size = '' } = {}) {
  const h = hueOf(name);
  return el('span.avatar', {
    class: size && `avatar--${size}`,
    style: {
      background: `color-mix(in srgb, var(--c${(h % 6) + 1}) 20%, transparent)`,
      color: `var(--c${(h % 6) + 1})`,
    },
    text: initials(name),
    title: name,
  });
}

export function meter(value, max = 100, colorVar = '--c1') {
  return el('span.meter', [
    el('i', { style: { width: `${Math.max(2, Math.min(100, (value / max) * 100))}%`, background: `var(${colorVar})` } }),
  ]);
}

export function card(title, { sub, actions, body, foot, flush } = {}) {
  return el('section.card', [
    (title || actions) && el('header.card__head', [
      el('div', [
        el('div.card__title', { text: title }),
        sub && el('div.card__sub', { text: sub }),
      ]),
      actions && el('div.row-2', [actions].flat()),
    ]),
    el('div.card__body', { class: flush ? 'card__body--flush' : '' }, [body]),
    foot && el('footer.card__foot', [foot]),
  ]);
}

export function empty(title, msg, { ico = 'search', action } = {}) {
  return el('div.empty', [
    el('div.empty__icon', [icon(ico, 20)]),
    el('div.empty__title', { text: title }),
    msg && el('div.empty__msg', { text: msg }),
    action,
  ]);
}

/**
 * The "what am I looking at" line. Every view gets one. Keep it to a sentence
 * or two, say what the page is FOR, and point at the single thing on it most
 * worth acting on.
 */
export function pageNote(text) {
  const node = el('div.page-note', [icon('info', 15)]);
  const span = el('span');
  span.innerHTML = esc(text).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
  node.appendChild(span);
  return node;
}

export function pageHead(title, sub, actions) {
  return el('div.page-head', [
    el('div.page-head__title', [el('h1', { text: title }), sub && el('p', { text: sub })]),
    actions && el('div.row-2.wrap', [actions].flat()),
  ]);
}

export function seg(options, active, onChange) {
  const wrap = el('div.seg');
  options.forEach(o => {
    const value = o.value ?? o;
    const label = o.label ?? o;
    wrap.appendChild(el('button.seg__btn', {
      class: value === active ? 'is-active' : '',
      text: label,
      onclick: () => {
        [...wrap.children].forEach(c => c.classList.remove('is-active'));
        wrap.children[options.findIndex(x => (x.value ?? x) === value)].classList.add('is-active');
        onChange(value);
      },
    }));
  });
  return wrap;
}

/**
 * Sortable, clickable table.
 * columns: [{ key, label, align, width, render(row), sort(row), className }]
 */
export function table(columns, rows, opts = {}) {
  const { onRowClick, sortKey, sortDir = 'desc', rowClass, emptyMsg = 'No matching records', max = 400 } = opts;

  let key = sortKey, dir = sortDir;
  const wrap = el('div.tablewrap');
  const tbl = el('table.table');
  const thead = el('thead');
  const tbody = el('tbody');

  const headRow = el('tr');
  columns.forEach(c => {
    const sortable = !!c.sort;
    const th = el('th', {
      class: [sortable && 'is-sortable', c.key === key && 'is-sorted', c.align === 'right' && 'right']
        .filter(Boolean).join(' '),
      style: c.width ? { width: c.width } : null,
      onclick: sortable ? () => {
        if (key === c.key) dir = dir === 'desc' ? 'asc' : 'desc';
        else { key = c.key; dir = 'desc'; }
        render();
      } : null,
    }, [
      el('span', { text: c.label }),
      sortable && el('span.sort', { text: c.key === key ? (dir === 'desc' ? '↓' : '↑') : '↕' }),
    ]);
    headRow.appendChild(th);
  });
  thead.appendChild(headRow);

  function render() {
    clear(tbody);
    const col = columns.find(c => c.key === key);
    let data = [...rows];
    if (col?.sort) {
      data.sort((a, b) => {
        const av = col.sort(a), bv = col.sort(b);
        const cmp = typeof av === 'string' ? av.localeCompare(bv) : (av || 0) - (bv || 0);
        return dir === 'desc' ? -cmp : cmp;
      });
    }
    if (!data.length) {
      tbody.appendChild(el('tr', [el('td', { colspan: columns.length },
        [empty('Nothing here', emptyMsg)])]));
      return;
    }
    data.slice(0, max).forEach(row => {
      const tr = el('tr', {
        class: [onRowClick && 'is-clickable', rowClass?.(row)].filter(Boolean).join(' '),
        onclick: onRowClick ? () => onRowClick(row) : null,
      });
      columns.forEach(c => {
        const content = c.render(row);
        tr.appendChild(el('td', {
          class: [c.align === 'right' && 'right', c.className].filter(Boolean).join(' '),
        }, [content instanceof Node ? content : el('span', { text: content ?? '—' })]));
      });
      tbody.appendChild(tr);
    });
    if (data.length > max) {
      tbody.appendChild(el('tr', [el('td.t-3.t-sm', { colspan: columns.length },
        [`Showing ${max} of ${data.length} — refine the filters to narrow this down`])]));
    }
  }

  render();
  wrap.appendChild(add(tbl, [thead, tbody]));
  wrap.refresh = (next) => { if (next) { rows = next; } render(); };
  return wrap;
}

/** Render **bold** markers from the narrator into real elements. */
export function richText(str) {
  const span = el('span');
  span.innerHTML = esc(str).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
  return span;
}

/* ------------------------------------------------------------- overlays */

export function overlay(node, { onClose } = {}) {
  const host = document.getElementById('overlays');
  const scrim = el('div.scrim', {
    onclick: (e) => { if (e.target === scrim) close(); },
  }, [node]);

  function close() {
    scrim.remove();
    document.removeEventListener('keydown', onKey);
    onClose?.();
  }
  function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }

  document.addEventListener('keydown', onKey);
  host.appendChild(scrim);
  return { close, scrim };
}

export function modal({ title, body, footer, onClose }) {
  let ref;
  const node = el('div.modal', [
    el('header.overlay__head', [
      el('h3', { text: title }),
      btn('Close', { iconOnly: true, ico: 'x', size: 'sm', onclick: () => ref.close() }),
    ]),
    el('div.overlay__body', [body]),
    footer && el('footer.overlay__foot', [footer].flat()),
  ]);
  ref = overlay(node, { onClose });
  return ref;
}

export function drawer({ title, body, onClose }) {
  let ref;
  const node = el('div.drawer', [
    el('header.overlay__head', [
      el('h3', { text: title }),
      btn('Close', { iconOnly: true, ico: 'x', size: 'sm', onclick: () => ref.close() }),
    ]),
    el('div.overlay__body', [body]),
  ]);
  ref = overlay(node, { onClose });
  return ref;
}

/**
 * Charts — hand-rolled SVG, zero dependencies.
 *
 * Built rather than imported for three reasons: total control of the brand
 * aesthetic, perfect theme switching (a charting library fights you on dark
 * mode forever), and no dependency risk. Roughly 100 lines of scale helpers
 * replace the parts of d3-scale that actually get used.
 *
 * Rules enforced here, not left to the caller:
 *   · categorical hues assigned in FIXED order, never cycled — a 7th series
 *     folds into "Other" rather than inventing a hue
 *   · ONE axis, always. No dual-scale charts exist in this codebase
 *   · thin marks, 4px rounded data-ends anchored to the baseline, 2px lines
 *   · 2px surface gap between adjacent fills so segments never bleed together
 *   · recessive grid and axes; text always wears text tokens, never series ink
 *   · legend whenever there are ≥2 series, plus a table view — which is also
 *     how the light-mode contrast obligation on aqua/magenta is discharged
 */

import { el, add, clear, btn, icon } from './dom.js';
import { moneyShort, numShort, num, pct, monthLabel } from './format.js';

const NS = 'http://www.w3.org/2000/svg';
export const SERIES = ['--c1', '--c2', '--c3', '--c4', '--c5', '--c6'];
export const seriesColor = (i) => i < SERIES.length ? `var(${SERIES[i]})` : 'var(--c-other)';

/* ------------------------------------------------------------------ scales */

export function linearScale(domain, range) {
  const [d0, d1] = domain, [r0, r1] = range;
  const span = d1 - d0 || 1;
  const fn = (v) => r0 + ((v - d0) / span) * (r1 - r0);
  fn.invert = (p) => d0 + ((p - r0) / (r1 - r0)) * span;
  fn.domain = domain;
  return fn;
}

export function bandScale(items, range, padding = 0.24) {
  const [r0, r1] = range;
  const step = (r1 - r0) / Math.max(1, items.length);
  const bw = step * (1 - padding);
  const fn = (i) => r0 + i * step + (step - bw) / 2;
  fn.bandwidth = bw;
  fn.step = step;
  return fn;
}

/** Human tick values — 1/2/5 × 10ⁿ. */
export function niceTicks(min, max, count = 4) {
  if (min === max) { min = Math.min(0, min); max = max || 1; }
  const span = max - min;
  const raw = span / count;
  const mag = Math.pow(10, Math.floor(Math.log10(raw)));
  const norm = raw / mag;
  const step = (norm >= 5 ? 10 : norm >= 2.5 ? 5 : norm >= 1.5 ? 2 : 1) * mag;
  const start = Math.floor(min / step) * step;
  const out = [];
  for (let v = start; v <= max + step * 0.5; v += step) out.push(+v.toFixed(10));
  return out;
}

const FORMATTERS = {
  money: moneyShort, number: numShort, count: num,
  pct: (v) => pct(v, 0), plain: (v) => String(v),
  x: (v) => `${(+v).toFixed(1)}×`,
};
const fmt = (kind) => typeof kind === 'function' ? kind : (FORMATTERS[kind] || FORMATTERS.number);

/* ------------------------------------------------------------------ tooltip */

let tip;
function tooltip() {
  if (tip) return tip;
  tip = el('div', {
    style: {
      position: 'fixed', zIndex: 90, pointerEvents: 'none', opacity: '0',
      background: 'var(--surface)', border: '1px solid var(--border-2)',
      borderRadius: 'var(--r-md)', boxShadow: 'var(--sh-3)',
      padding: '8px 10px', fontSize: 'var(--t-sm)', minWidth: '110px',
      transition: 'opacity 90ms ease', lineHeight: '1.45',
    },
  });
  document.body.appendChild(tip);
  return tip;
}

function showTip(evt, html) {
  const t = tooltip();
  t.innerHTML = html;
  t.style.opacity = '1';
  const r = t.getBoundingClientRect();
  const x = Math.min(innerWidth - r.width - 10, Math.max(10, evt.clientX + 14));
  const y = Math.max(10, evt.clientY - r.height - 12);
  t.style.left = x + 'px';
  t.style.top = y + 'px';
}
const hideTip = () => { if (tip) tip.style.opacity = '0'; };

const tipRow = (color, label, value) =>
  `<div style="display:flex;align-items:center;gap:7px;margin-top:3px">
     ${color ? `<i style="width:8px;height:8px;border-radius:2px;background:${color};flex:none"></i>` : ''}
     <span style="color:var(--text-3)">${label}</span>
     <b style="margin-left:auto;font-variant-numeric:tabular-nums">${value}</b>
   </div>`;

/* --------------------------------------------------------------- svg utils */

function svg(w, h) {
  const s = document.createElementNS(NS, 'svg');
  s.setAttribute('viewBox', `0 0 ${w} ${h}`);
  s.setAttribute('width', '100%');
  s.setAttribute('height', h);
  s.setAttribute('preserveAspectRatio', 'none');
  s.style.overflow = 'visible';
  s.style.display = 'block';
  return s;
}

function node(tag, attrs = {}) {
  const n = document.createElementNS(NS, tag);
  for (const [k, v] of Object.entries(attrs)) if (v != null) n.setAttribute(k, v);
  return n;
}

/** Path with rounded top corners — the 4px rounded data-end, baseline anchored. */
function roundedTopRect(x, y, w, h, r = 4) {
  const rr = Math.min(r, w / 2, Math.max(0, h));
  return `M${x},${y + h} L${x},${y + rr} Q${x},${y} ${x + rr},${y} L${x + w - rr},${y} Q${x + w},${y} ${x + w},${y + rr} L${x + w},${y + h} Z`;
}
function roundedRightRect(x, y, w, h, r = 4) {
  const rr = Math.min(r, h / 2, Math.max(0, w));
  return `M${x},${y} L${x + w - rr},${y} Q${x + w},${y} ${x + w},${y + rr} L${x + w},${y + h - rr} Q${x + w},${y + h} ${x + w - rr},${y + h} L${x},${y + h} Z`;
}

/** Catmull-Rom → cubic Bézier. Gentle smoothing; never enough to lie. */
function smoothPath(pts) {
  if (pts.length < 2) return '';
  if (pts.length === 2) return `M${pts[0][0]},${pts[0][1]} L${pts[1][0]},${pts[1][1]}`;
  let d = `M${pts[0][0]},${pts[0][1]}`;
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2;
    const t = 0.18;
    d += ` C${p1[0] + (p2[0] - p0[0]) * t},${p1[1] + (p2[1] - p0[1]) * t}` +
         ` ${p2[0] - (p3[0] - p1[0]) * t},${p2[1] - (p3[1] - p1[1]) * t}` +
         ` ${p2[0]},${p2[1]}`;
  }
  return d;
}

/* ------------------------------------------------------------------ figure */

/**
 * Wraps a chart with its legend and a table view. The table is not decoration:
 * it is the accessibility fallback and the relief that makes the light-mode
 * sub-3:1 series legal.
 */
function figure(chartEl, { legend, tableData, height } = {}) {
  const wrap = el('figure', { style: { margin: 0, position: 'relative' } });
  const body = el('div', [chartEl]);
  let showingTable = false;

  const toggle = tableData && el('button.btn.btn--sm', {
    style: { position: 'absolute', top: '-32px', right: '0' },
    title: 'Toggle table view',
    onclick: () => {
      showingTable = !showingTable;
      clear(body);
      body.appendChild(showingTable ? buildTable(tableData, height) : chartEl);
      toggle.querySelector('span').textContent = showingTable ? 'Chart' : 'Table';
    },
  }, [icon('reports', 13), el('span', { text: 'Table' })]);

  if (toggle) wrap.appendChild(toggle);
  wrap.appendChild(body);
  if (legend) wrap.appendChild(legend);
  return wrap;
}

function buildTable({ columns, rows }, height) {
  const wrap = el('div.tablewrap', { style: { maxHeight: (height || 240) + 'px', overflowY: 'auto' } });
  const t = el('table.table');
  t.appendChild(el('thead', [el('tr', columns.map((c, i) =>
    el('th', { class: i ? 'right' : '', text: c })))]));
  t.appendChild(el('tbody', rows.map(r =>
    el('tr', r.map((v, i) => el('td', { class: i ? 'right num' : '', text: String(v) }))))));
  wrap.appendChild(t);
  return wrap;
}

/** Legend. Present whenever there are ≥2 series — identity is never colour alone. */
export function legendFor(series) {
  if (series.length < 2) return null;
  return el('div.row-2.wrap', {
    style: { marginTop: '10px', gap: '14px', fontSize: 'var(--t-sm)' },
  }, series.map((s, i) => el('span.row-2', { style: { gap: '6px' } }, [
    el('i', {
      style: {
        width: '9px', height: '9px', borderRadius: '2.5px', flex: 'none',
        background: s.color || seriesColor(i),
      },
    }),
    el('span.t-2', { text: s.name }),
  ])));
}

/* ==========================================================================
   SPARKLINE — no axes, no legend, no tooltip. Pure shape, for KPI tiles.
   ========================================================================== */

export function sparkline(values, { height = 34, color = 'var(--c1)', fill = true } = {}) {
  const W = 240, H = height;
  const s = svg(W, H);
  if (values.length < 2) return s;

  const min = Math.min(...values), max = Math.max(...values);
  const x = linearScale([0, values.length - 1], [0, W]);
  const y = linearScale([min === max ? min - 1 : min, max], [H - 3, 3]);
  const pts = values.map((v, i) => [x(i), y(v)]);
  const d = smoothPath(pts);

  if (fill) {
    const gid = 'sg' + Math.random().toString(36).slice(2, 8);
    const defs = node('defs');
    const grad = node('linearGradient', { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 });
    grad.appendChild(node('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': .28 }));
    grad.appendChild(node('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': 0 }));
    defs.appendChild(grad);
    s.appendChild(defs);
    s.appendChild(node('path', { d: `${d} L${W},${H} L0,${H} Z`, fill: `url(#${gid})` }));
  }
  s.appendChild(node('path', { d, fill: 'none', stroke: color, 'stroke-width': 2, 'stroke-linecap': 'round' }));
  return s;
}

/* ==========================================================================
   LINE / AREA — crosshair + tooltip by default. One y-axis, always.
   ========================================================================== */

export function lineChart(series, opts = {}) {
  const {
    height = 240, format = 'money', area = series.length === 1,
    xLabel = (t) => monthLabel(t), yTicks = 4, showDots = 'ends',
  } = opts;

  const W = 800, H = height, pad = { t: 12, r: 16, b: 26, l: 54 };
  const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
  const f = fmt(format);

  const points = series[0]?.data || [];
  if (points.length < 2) return el('div.empty', [el('div.empty__msg', { text: 'Not enough data to plot' })]);

  const allVals = series.flatMap(s => s.data.map(d => d.value));
  const maxV = Math.max(...allVals, 0);
  const minV = Math.min(...allVals, 0);
  const ticks = niceTicks(minV, maxV, yTicks);

  const s = svg(W, H);
  const x = linearScale([0, points.length - 1], [pad.l, pad.l + iw]);
  const y = linearScale([ticks[0], ticks[ticks.length - 1]], [pad.t + ih, pad.t]);

  // Recessive grid.
  ticks.forEach(t => {
    s.appendChild(node('line', {
      x1: pad.l, x2: pad.l + iw, y1: y(t), y2: y(t),
      stroke: 'var(--grid)', 'stroke-width': 1,
    }));
    const lbl = node('text', {
      x: pad.l - 10, y: y(t) + 4, 'text-anchor': 'end',
      fill: 'var(--text-3)', 'font-size': 10.5,
    });
    lbl.textContent = f(t);
    s.appendChild(lbl);
  });

  // X labels — thinned so they never collide.
  const every = Math.ceil(points.length / 7);
  points.forEach((p, i) => {
    if (i % every && i !== points.length - 1) return;
    const lbl = node('text', {
      x: x(i), y: H - 8, 'text-anchor': 'middle',
      fill: 'var(--text-3)', 'font-size': 10.5,
    });
    lbl.textContent = xLabel(p.label ?? p.t);
    s.appendChild(lbl);
  });

  series.forEach((ser, si) => {
    const color = ser.color || seriesColor(si);
    const pts = ser.data.map((d, i) => [x(i), y(d.value)]);
    const d = smoothPath(pts);

    if (area) {
      const gid = 'ag' + si + Math.random().toString(36).slice(2, 7);
      const defs = node('defs');
      const grad = node('linearGradient', { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 });
      grad.appendChild(node('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': .22 }));
      grad.appendChild(node('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': .01 }));
      defs.appendChild(grad);
      s.appendChild(defs);
      s.appendChild(node('path', {
        d: `${d} L${pts[pts.length - 1][0]},${y(ticks[0])} L${pts[0][0]},${y(ticks[0])} Z`,
        fill: `url(#${gid})`,
      }));
    }

    // Projected and partial-period segments render dashed. A forecast must
    // never look measured, and the current month is not a real data point yet.
    const projectedFrom = ser.data.findIndex(p => p.projected || p.partial);
    if (projectedFrom > 0) {
      s.appendChild(node('path', {
        d: smoothPath(pts.slice(0, projectedFrom)), fill: 'none', stroke: color,
        'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
      }));
      s.appendChild(node('path', {
        d: smoothPath(pts.slice(projectedFrom - 1)), fill: 'none', stroke: color,
        'stroke-width': 2, 'stroke-dasharray': '5 4', 'stroke-linecap': 'round', opacity: .75,
      }));
    } else {
      s.appendChild(node('path', {
        d, fill: 'none', stroke: color, 'stroke-width': 2,
        'stroke-linecap': 'round', 'stroke-linejoin': 'round',
      }));
    }

    if (showDots === 'ends' || showDots === true) {
      const idx = showDots === true ? pts.map((_, i) => i) : [pts.length - 1];
      idx.forEach(i => {
        // 2px surface ring keeps overlapping marks separable.
        s.appendChild(node('circle', { cx: pts[i][0], cy: pts[i][1], r: 5, fill: 'var(--surface)' }));
        s.appendChild(node('circle', { cx: pts[i][0], cy: pts[i][1], r: 3.5, fill: color }));
      });
    }
  });

  // Crosshair + tooltip layer.
  const cross = node('line', {
    y1: pad.t, y2: pad.t + ih, stroke: 'var(--border-strong)',
    'stroke-width': 1, 'stroke-dasharray': '3 3', opacity: 0,
  });
  s.appendChild(cross);
  const markers = series.map((ser, si) => {
    const m = node('circle', { r: 5, fill: ser.color || seriesColor(si), opacity: 0,
      stroke: 'var(--surface)', 'stroke-width': 2 });
    s.appendChild(m);
    return m;
  });

  const hit = node('rect', { x: pad.l, y: pad.t, width: iw, height: ih, fill: 'transparent' });
  hit.style.cursor = 'crosshair';
  hit.addEventListener('mousemove', (e) => {
    const box = s.getBoundingClientRect();
    const rel = ((e.clientX - box.left) / box.width) * W;
    const i = Math.max(0, Math.min(points.length - 1, Math.round(x.invert(rel))));
    cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i));
    cross.setAttribute('opacity', 1);
    series.forEach((ser, si) => {
      const p = ser.data[i];
      if (!p) return;
      markers[si].setAttribute('cx', x(i));
      markers[si].setAttribute('cy', y(p.value));
      markers[si].setAttribute('opacity', 1);
    });
    showTip(e,
      `<div style="font-weight:600">${xLabel(points[i].label ?? points[i].t)}</div>` +
      series.map((ser, si) => tipRow(ser.color || seriesColor(si), ser.name || 'Value',
        f(ser.data[i]?.value ?? 0)
        + (ser.data[i]?.projected ? ' (projected)' : '')
        + (ser.data[i]?.partial ? ' (month to date)' : ''))).join(''));
  });
  hit.addEventListener('mouseleave', () => {
    cross.setAttribute('opacity', 0);
    markers.forEach(m => m.setAttribute('opacity', 0));
    hideTip();
  });
  s.appendChild(hit);

  return figure(s, {
    legend: legendFor(series),
    height,
    tableData: {
      columns: ['Period', ...series.map(x2 => x2.name || 'Value')],
      rows: points.map((p, i) => [xLabel(p.label ?? p.t), ...series.map(ser => f(ser.data[i]?.value ?? 0))]),
    },
  });
}

/* ==========================================================================
   VERTICAL BAR — magnitude by category.
   ========================================================================== */

export function barChart(data, opts = {}) {
  const {
    height = 240, format = 'money', color = 'var(--c1)',
    colorBy = null, highlight = null, onBarClick = null, labelFmt = (d) => d.label,
  } = opts;

  if (!data.length) return el('div.empty', [el('div.empty__msg', { text: 'No data' })]);

  const W = 800, H = height, pad = { t: 14, r: 10, b: 34, l: 54 };
  const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
  const f = fmt(format);

  const max = Math.max(...data.map(d => d.value), 0);
  const ticks = niceTicks(0, max, 4);
  const s = svg(W, H);
  const x = bandScale(data, [pad.l, pad.l + iw], 0.3);
  const y = linearScale([0, ticks[ticks.length - 1]], [pad.t + ih, pad.t]);

  ticks.forEach(t => {
    s.appendChild(node('line', {
      x1: pad.l, x2: pad.l + iw, y1: y(t), y2: y(t),
      stroke: 'var(--grid)', 'stroke-width': 1,
    }));
    const lbl = node('text', { x: pad.l - 10, y: y(t) + 4, 'text-anchor': 'end', fill: 'var(--text-3)', 'font-size': 10.5 });
    lbl.textContent = f(t);
    s.appendChild(lbl);
  });

  data.forEach((d, i) => {
    const h = Math.max(1, y(0) - y(d.value));
    const c = d.color || (colorBy ? seriesColor(i % SERIES.length) : color);
    const dim = highlight && d.label !== highlight;

    // 2px inset each side keeps adjacent bars from bleeding together.
    const g = node('path', {
      d: roundedTopRect(x(i) + 1, y(d.value), x.bandwidth - 2, h, 4),
      fill: c, opacity: dim ? 0.32 : 1,
    });
    g.style.transition = 'opacity 120ms ease';
    if (onBarClick) g.style.cursor = 'pointer';
    g.addEventListener('mouseenter', (e) => {
      g.setAttribute('opacity', 0.82);
      showTip(e, `<div style="font-weight:600">${d.label}</div>${tipRow(c, opts.valueLabel || 'Value', f(d.value))}${d.note ? `<div style="color:var(--text-3);margin-top:4px">${d.note}</div>` : ''}`);
    });
    g.addEventListener('mousemove', (e) => showTip(e,
      `<div style="font-weight:600">${d.label}</div>${tipRow(c, opts.valueLabel || 'Value', f(d.value))}${d.note ? `<div style="color:var(--text-3);margin-top:4px">${d.note}</div>` : ''}`));
    g.addEventListener('mouseleave', () => { g.setAttribute('opacity', dim ? 0.32 : 1); hideTip(); });
    if (onBarClick) g.addEventListener('click', () => onBarClick(d));
    s.appendChild(g);

    const lbl = node('text', {
      x: x(i) + x.bandwidth / 2, y: H - 10, 'text-anchor': 'middle',
      fill: 'var(--text-3)', 'font-size': 10.5,
    });
    const text = labelFmt(d);
    lbl.textContent = text.length > 11 && data.length > 6 ? text.slice(0, 10) + '…' : text;
    s.appendChild(lbl);
  });

  return figure(s, {
    height,
    tableData: { columns: ['Category', 'Value'], rows: data.map(d => [d.label, f(d.value)]) },
  });
}

/* ==========================================================================
   HORIZONTAL BAR — the right form when labels are long. Direct-labelled.
   ========================================================================== */

export function hBarChart(data, opts = {}) {
  const { format = 'money', rowHeight = 30, color = 'var(--c1)', colorBy = false, onBarClick } = opts;
  if (!data.length) return el('div.empty', [el('div.empty__msg', { text: 'No data' })]);

  const labelW = 130, valueW = 76;
  const W = 800, H = data.length * rowHeight + 8;
  const f = fmt(format);
  const max = Math.max(...data.map(d => d.value), 0) || 1;

  const s = svg(W, H);
  const trackX = labelW + 10;
  const trackW = W - trackX - valueW - 8;

  data.forEach((d, i) => {
    const cy = i * rowHeight + 6;
    const bh = rowHeight - 14;
    const c = d.color || (colorBy ? seriesColor(i % SERIES.length) : color);

    const lbl = node('text', {
      x: labelW, y: cy + bh / 2 + 4, 'text-anchor': 'end',
      fill: 'var(--text-2)', 'font-size': 11.5,
    });
    lbl.textContent = d.label.length > 20 ? d.label.slice(0, 19) + '…' : d.label;
    s.appendChild(lbl);

    s.appendChild(node('rect', {
      x: trackX, y: cy, width: trackW, height: bh, rx: 4, fill: 'var(--surface-3)', opacity: .55,
    }));

    const w = Math.max(3, (d.value / max) * trackW);
    const bar = node('path', { d: roundedRightRect(trackX, cy, w, bh, 4), fill: c });
    bar.style.transition = 'opacity 120ms ease';
    if (onBarClick) bar.style.cursor = 'pointer';
    const tipHtml = `<div style="font-weight:600">${d.label}</div>${tipRow(c, opts.valueLabel || 'Value', f(d.value))}${d.note ? `<div style="color:var(--text-3);margin-top:4px">${d.note}</div>` : ''}`;
    bar.addEventListener('mouseenter', (e) => { bar.setAttribute('opacity', .82); showTip(e, tipHtml); });
    bar.addEventListener('mousemove', (e) => showTip(e, tipHtml));
    bar.addEventListener('mouseleave', () => { bar.setAttribute('opacity', 1); hideTip(); });
    if (onBarClick) bar.addEventListener('click', () => onBarClick(d));
    s.appendChild(bar);

    // Direct value label — selective by design: on the row, not on every point.
    const val = node('text', {
      x: W - 4, y: cy + bh / 2 + 4, 'text-anchor': 'end',
      fill: 'var(--text)', 'font-size': 11.5, 'font-weight': 600,
    });
    val.textContent = f(d.value);
    s.appendChild(val);
  });

  return figure(s, {
    height: H,
    tableData: { columns: ['Category', 'Value'], rows: data.map(d => [d.label, f(d.value)]) },
  });
}

/* ==========================================================================
   STACKED BAR — parts of a whole, over time.
   ========================================================================== */

export function stackedBar(categories, series, opts = {}) {
  const { height = 240, format = 'money', xLabel = (t) => monthLabel(t) } = opts;
  if (!categories.length) return el('div.empty', [el('div.empty__msg', { text: 'No data' })]);

  const W = 800, H = height, pad = { t: 14, r: 10, b: 34, l: 54 };
  const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
  const f = fmt(format);

  const totals = categories.map((_, i) => series.reduce((s2, ser) => s2 + (ser.data[i] || 0), 0));
  const ticks = niceTicks(0, Math.max(...totals, 0), 4);

  const s = svg(W, H);
  const x = bandScale(categories, [pad.l, pad.l + iw], 0.32);
  const y = linearScale([0, ticks[ticks.length - 1]], [pad.t + ih, pad.t]);

  ticks.forEach(t => {
    s.appendChild(node('line', { x1: pad.l, x2: pad.l + iw, y1: y(t), y2: y(t), stroke: 'var(--grid)', 'stroke-width': 1 }));
    const lbl = node('text', { x: pad.l - 10, y: y(t) + 4, 'text-anchor': 'end', fill: 'var(--text-3)', 'font-size': 10.5 });
    lbl.textContent = f(t);
    s.appendChild(lbl);
  });

  categories.forEach((cat, i) => {
    let acc = 0;
    series.forEach((ser, si) => {
      const v = ser.data[i] || 0;
      if (v <= 0) return;
      const y0 = y(acc + v), y1 = y(acc);
      // 2px surface gap between segments — never let fills bleed together.
      const h = Math.max(1, y1 - y0 - 2);
      const c = ser.color || seriesColor(si);
      const isTop = acc + v >= totals[i] - 0.001;
      const rect = node('path', {
        d: isTop ? roundedTopRect(x(i), y0, x.bandwidth, h, 4)
                 : `M${x(i)},${y0} h${x.bandwidth} v${h} h${-x.bandwidth} Z`,
        fill: c,
      });
      const tipHtml = `<div style="font-weight:600">${xLabel(cat)}</div>` +
        series.map((s2, j) => tipRow(s2.color || seriesColor(j), s2.name, f(s2.data[i] || 0))).join('') +
        tipRow(null, 'Total', f(totals[i]));
      rect.addEventListener('mouseenter', (e) => { rect.setAttribute('opacity', .82); showTip(e, tipHtml); });
      rect.addEventListener('mousemove', (e) => showTip(e, tipHtml));
      rect.addEventListener('mouseleave', () => { rect.setAttribute('opacity', 1); hideTip(); });
      s.appendChild(rect);
      acc += v;
    });

    const lbl = node('text', { x: x(i) + x.bandwidth / 2, y: H - 10, 'text-anchor': 'middle', fill: 'var(--text-3)', 'font-size': 10.5 });
    lbl.textContent = xLabel(cat);
    s.appendChild(lbl);
  });

  return figure(s, {
    legend: legendFor(series),
    height,
    tableData: {
      columns: ['Period', ...series.map(s2 => s2.name), 'Total'],
      rows: categories.map((c, i) => [xLabel(c), ...series.map(s2 => f(s2.data[i] || 0)), f(totals[i])]),
    },
  });
}

/* ==========================================================================
   DONUT — only for parts-of-a-whole with ≤6 slices. Centre carries the total.
   ========================================================================== */

export function donut(data, opts = {}) {
  const { size = 190, format = 'money', centerLabel = 'Total', thickness = 26 } = opts;
  if (!data.length) return el('div.empty', [el('div.empty__msg', { text: 'No data' })]);

  const f = fmt(format);
  const total = data.reduce((s, d) => s + d.value, 0) || 1;
  const r = size / 2, ir = r - thickness;

  const s = svg(size, size);
  s.setAttribute('width', size);
  s.setAttribute('height', size);
  s.setAttribute('preserveAspectRatio', 'xMidYMid meet');

  let a0 = -Math.PI / 2;
  const GAP = 0.022;   // the 2px surface gap, expressed in radians

  data.forEach((d, i) => {
    const frac = d.value / total;
    const a1 = a0 + frac * Math.PI * 2;
    const s0 = a0 + GAP / 2, s1 = Math.max(s0 + 0.001, a1 - GAP / 2);
    const large = (s1 - s0) > Math.PI ? 1 : 0;
    const c = d.color || seriesColor(i);

    const p = node('path', {
      d: `M${r + Math.cos(s0) * r},${r + Math.sin(s0) * r}
          A${r},${r} 0 ${large} 1 ${r + Math.cos(s1) * r},${r + Math.sin(s1) * r}
          L${r + Math.cos(s1) * ir},${r + Math.sin(s1) * ir}
          A${ir},${ir} 0 ${large} 0 ${r + Math.cos(s0) * ir},${r + Math.sin(s0) * ir} Z`,
      fill: c,
    });
    const tipHtml = `<div style="font-weight:600">${d.label}</div>${tipRow(c, 'Value', f(d.value))}${tipRow(null, 'Share', pct(frac, 1))}`;
    p.addEventListener('mouseenter', (e) => { p.setAttribute('opacity', .82); showTip(e, tipHtml); });
    p.addEventListener('mousemove', (e) => showTip(e, tipHtml));
    p.addEventListener('mouseleave', () => { p.setAttribute('opacity', 1); hideTip(); });
    s.appendChild(p);
    a0 = a1;
  });

  const t1 = node('text', { x: r, y: r - 2, 'text-anchor': 'middle', fill: 'var(--text)', 'font-size': 17, 'font-weight': 650 });
  t1.textContent = f(total);
  const t2 = node('text', { x: r, y: r + 15, 'text-anchor': 'middle', fill: 'var(--text-3)', 'font-size': 10.5 });
  t2.textContent = centerLabel;
  s.appendChild(t1); s.appendChild(t2);

  return figure(el('div.center', [s]), {
    legend: legendFor(data.map((d, i) => ({ name: `${d.label} · ${f(d.value)}`, color: d.color || seriesColor(i) }))),
    tableData: { columns: ['Segment', 'Value', 'Share'], rows: data.map(d => [d.label, f(d.value), pct(d.value / total, 1)]) },
  });
}

/* ==========================================================================
   FUNNEL — stage counts plus step conversion. Drop is direct-labelled.
   ========================================================================== */

export function funnelChart(stages, opts = {}) {
  const { rowHeight = 46, onStageClick } = opts;
  if (!stages.length) return el('div.empty', [el('div.empty__msg', { text: 'No data' })]);

  const W = 800, H = stages.length * rowHeight + 8;
  const max = stages[0].count || 1;
  const labelW = 108, statsW = 150;
  const trackX = labelW + 10;
  const trackW = W - trackX - statsW;

  const s = svg(W, H);

  stages.forEach((st, i) => {
    const cy = i * rowHeight + 5;
    const bh = rowHeight - 16;
    const w = Math.max(4, (st.count / max) * trackW);
    const c = seriesColor(Math.min(i, SERIES.length - 1));
    const weak = i > 0 && st.conversion < 0.5;

    const lbl = node('text', { x: labelW, y: cy + bh / 2 + 4, 'text-anchor': 'end', fill: 'var(--text-2)', 'font-size': 12, 'font-weight': 500 });
    lbl.textContent = st.stage.charAt(0).toUpperCase() + st.stage.slice(1);
    s.appendChild(lbl);

    s.appendChild(node('rect', { x: trackX, y: cy, width: trackW, height: bh, rx: 4, fill: 'var(--surface-3)', opacity: .45 }));

    const bar = node('path', { d: roundedRightRect(trackX, cy, w, bh, 4), fill: c });
    if (onStageClick) bar.style.cursor = 'pointer';
    const tipHtml = `<div style="font-weight:600">${st.stage}</div>${tipRow(c, 'Reached', num(st.count))}${i ? tipRow(null, 'Step conversion', pct(st.conversion, 1)) + tipRow(null, 'Dropped', num(st.dropped)) : ''}`;
    bar.addEventListener('mouseenter', (e) => { bar.setAttribute('opacity', .82); showTip(e, tipHtml); });
    bar.addEventListener('mousemove', (e) => showTip(e, tipHtml));
    bar.addEventListener('mouseleave', () => { bar.setAttribute('opacity', 1); hideTip(); });
    if (onStageClick) bar.addEventListener('click', () => onStageClick(st));
    s.appendChild(bar);

    const cnt = node('text', { x: trackX + trackW + 10, y: cy + bh / 2 + 4, fill: 'var(--text)', 'font-size': 12, 'font-weight': 600 });
    cnt.textContent = num(st.count);
    s.appendChild(cnt);

    if (i > 0) {
      const conv = node('text', {
        x: W - 4, y: cy + bh / 2 + 4, 'text-anchor': 'end',
        fill: weak ? 'var(--sev-high)' : 'var(--text-3)', 'font-size': 11.5,
        'font-weight': weak ? 600 : 500,
      });
      conv.textContent = `${pct(st.conversion, 0)} · −${num(st.dropped)}`;
      s.appendChild(conv);
    }
  });

  return figure(s, {
    height: H,
    tableData: {
      columns: ['Stage', 'Reached', 'Conversion', 'Dropped'],
      rows: stages.map((st, i) => [st.stage, num(st.count), i ? pct(st.conversion, 1) : '—', i ? num(st.dropped) : '—']),
    },
  });
}

/* ==========================================================================
   RATING BARS — 5→1 star distribution. Sequential, one hue.
   ========================================================================== */

export function ratingBars(distribution, opts = {}) {
  const total = distribution.reduce((s, d) => s + d.count, 0) || 1;
  return el('div.stack-2', distribution.slice().reverse().map(d => {
    const share = d.count / total;
    return el('div.row-2', [
      el('span.t-sm.t-2.num', { style: { width: '26px' }, text: `${d.rating}★` }),
      el('span', {
        style: {
          flex: '1', height: '7px', borderRadius: 'var(--r-full)',
          background: 'var(--surface-3)', overflow: 'hidden',
        },
      }, [el('i', {
        style: {
          display: 'block', height: '100%', width: `${Math.max(1, share * 100)}%`,
          borderRadius: 'var(--r-full)',
          background: d.rating >= 4 ? 'var(--pos)' : d.rating === 3 ? 'var(--c-other)' : 'var(--neg)',
        },
      })]),
      el('span.t-sm.num.t-2', { style: { width: '54px', textAlign: 'right' }, text: `${num(d.count)}` }),
      el('span.t-xs.t-3.num', { style: { width: '38px', textAlign: 'right' }, text: pct(share, 0) }),
    ]);
  }));
}

export { showTip, hideTip };

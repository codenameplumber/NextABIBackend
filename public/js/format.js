/**
 * Formatting. BI lives or dies on number presentation — everything here is
 * tabular-safe and locale-aware, and currency defaults to NGN because the
 * product is aimed at Nigerian real estate operators.
 */

const CCY = 'NGN';
const SYM = '₦';

const nf = (opts) => new Intl.NumberFormat('en-NG', opts);

const _int = nf({ maximumFractionDigits: 0 });
const _1dp = nf({ minimumFractionDigits: 1, maximumFractionDigits: 1 });
const _2dp = nf({ minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Full currency, e.g. ₦12,450,000 */
export function money(n, { dp = 0 } = {}) {
  if (n == null || Number.isNaN(n)) return '—';
  return SYM + nf({ minimumFractionDigits: dp, maximumFractionDigits: dp }).format(n);
}

/** Compact currency for KPIs and axes, e.g. ₦12.4M, ₦1.8B */
export function moneyShort(n) {
  if (n == null || Number.isNaN(n)) return '—';
  const s = n < 0 ? '-' : '';
  const a = Math.abs(n);
  if (a >= 1e12) return `${s}${SYM}${_1dp.format(a / 1e12)}T`;
  if (a >= 1e9)  return `${s}${SYM}${_1dp.format(a / 1e9)}B`;
  if (a >= 1e6)  return `${s}${SYM}${_1dp.format(a / 1e6)}M`;
  if (a >= 1e3)  return `${s}${SYM}${_int.format(Math.round(a / 1e3))}K`;
  return `${s}${SYM}${_int.format(a)}`;
}

/** Split for KPI display: { value: '₦1.8', unit: 'B' } */
export function moneyParts(n) {
  const full = moneyShort(n);
  const m = full.match(/^(.*?)([KMBT])$/);
  return m ? { value: m[1], unit: m[2] } : { value: full, unit: '' };
}

export function num(n, dp = 0) {
  if (n == null || Number.isNaN(n)) return '—';
  return nf({ minimumFractionDigits: dp, maximumFractionDigits: dp }).format(n);
}

export function numShort(n) {
  if (n == null || Number.isNaN(n)) return '—';
  const a = Math.abs(n);
  if (a >= 1e9) return _1dp.format(n / 1e9) + 'B';
  if (a >= 1e6) return _1dp.format(n / 1e6) + 'M';
  if (a >= 1e4) return _int.format(Math.round(n / 1e3)) + 'K';
  return _int.format(n);
}

/** 0.284 -> "28.4%" */
export function pct(n, dp = 1) {
  if (n == null || Number.isNaN(n) || !Number.isFinite(n)) return '—';
  return nf({ minimumFractionDigits: dp, maximumFractionDigits: dp }).format(n * 100) + '%';
}

/** Percentage-point delta, always signed: "+12.4pts" */
export function pts(n, dp = 1) {
  if (n == null || !Number.isFinite(n)) return '—';
  const v = nf({ minimumFractionDigits: dp, maximumFractionDigits: dp }).format(Math.abs(n) * 100);
  return `${n >= 0 ? '+' : '−'}${v}pts`;
}

/** Signed relative change: 0.184 -> "+18.4%" */
export function delta(n, dp = 1) {
  if (n == null || !Number.isFinite(n)) return '—';
  const v = nf({ minimumFractionDigits: dp, maximumFractionDigits: dp }).format(Math.abs(n) * 100);
  return `${n >= 0 ? '+' : '−'}${v}%`;
}

/** "3.2×" — for multiples, used constantly by the detectors. */
export function mult(n, dp = 1) {
  if (n == null || !Number.isFinite(n)) return '—';
  return `${_1dp.format(n)}×`.replace('.0×', '×');
}

/* ------------------------------------------------------------------- dates */

const MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

export function date(ts) {
  if (!ts) return '—';
  const d = new Date(ts);
  return `${d.getDate()} ${MON[d.getMonth()]} ${d.getFullYear()}`;
}

export function dateShort(ts) {
  if (!ts) return '—';
  const d = new Date(ts);
  return `${d.getDate()} ${MON[d.getMonth()]}`;
}

export function monthLabel(ts) {
  const d = new Date(ts);
  return `${MON[d.getMonth()]} ${String(d.getFullYear()).slice(2)}`;
}

/** "12 days ago" / "in 3 days", relative to simulated now. */
export function ago(ts, nowMs) {
  if (!ts) return '—';
  const diff = nowMs - ts;
  const past = diff >= 0;
  const d = Math.floor(Math.abs(diff) / 864e5);
  let s;
  if (d === 0) s = 'today';
  else if (d === 1) s = past ? 'yesterday' : 'tomorrow';
  else if (d < 31) s = `${d} days`;
  else if (d < 365) s = `${Math.floor(d / 30)} mo`;
  else s = `${(d / 365).toFixed(1)} yr`;
  if (d === 0 || d === 1) return s;
  return past ? `${s} ago` : `in ${s}`;
}

export function days(ts, nowMs) { return Math.floor((nowMs - ts) / 864e5); }

/* -------------------------------------------------------------------- misc */

export function initials(name = '') {
  return name.trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
}

export function titleCase(s = '') {
  return s.replace(/[_-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

/** Deterministic pick from a palette, keyed by a string. */
export function hueOf(key = '') {
  let h = 0;
  for (let i = 0; i < key.length; i++) h = (h * 31 + key.charCodeAt(i)) >>> 0;
  return h % 8;
}

export function clamp(n, lo, hi) { return Math.min(hi, Math.max(lo, n)); }

/**
 * Pluralise. Generated prose that says "1 units of stock" reads as a template
 * with the seams showing, which is exactly the impression to avoid.
 *   plural(1, 'unit')            -> "1 unit"
 *   plural(3, 'unit')            -> "3 units"
 *   plural(1, 'company', 'companies')
 */
export function plural(n, one, many) {
  const word = Math.abs(n) === 1 ? one : (many || one + 's');
  return `${num(n)} ${word}`;
}

/** Just the word, when the number is formatted separately. */
export function pluralWord(n, one, many) {
  return Math.abs(n) === 1 ? one : (many || one + 's');
}

/** Escape for safe innerHTML interpolation. */
export function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export const CURRENCY = CCY;
export const SYMBOL = SYM;

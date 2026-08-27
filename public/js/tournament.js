/**
 * Tournament Mode.
 *
 * Not a slideshow. This drives the real application — it navigates real
 * routes, spotlights real elements, advances the real clock and, at the
 * climax, executes a real action whose effect you can watch land in the KPIs.
 *
 * Every step is skippable and the whole thing exits into free exploration,
 * because the worst thing a demo mode can do is make the product feel like it
 * only works along one path.
 *
 * The server-rendered build navigates between real pages, so the position has
 * to survive a page load. It lives in sessionStorage: close the tab and the
 * walkthrough is over, which is the right lifetime for it.
 */

import { el, icon } from './dom.js';

const KEY = 'nexabi.tour';

/**
 * Steps that only read are declared here in full. The two that MUTATE — the
 * follow-up sweep and the clock advance — name a Livewire action instead, so
 * the change goes through the same action bus as a user's own click and lands
 * in the action log like anything else.
 */
const STEPS = [
  {
    title: 'This is not a mockup',
    text: 'Everything on screen is computed from a simulated business: roughly 1,400 leads, '
        + '224 properties and 20 months of events. The dataset is generated from a fixed seed, '
        + 'so it is identical every run — and every number you are about to see is derived from '
        + 'it, not written into the interface.',
    route: '/app',
    target: '.kpi',
  },
  {
    title: 'The AI reads the business first',
    text: 'The briefing at the top is generated from the same computed figures the KPI tiles '
        + 'use, which is why a report can never contradict the dashboard. Detection, scoring '
        + 'and recommendation are all real arithmetic over your rows — only the prose sits '
        + 'behind a swappable provider.',
    route: '/app',
    target: '.hero',
  },
  {
    title: 'Findings, not charts',
    text: 'Each finding is produced by a detector running over the metrics layer. They are '
        + 'ranked by severity, confidence, role weight and impact — so a dramatic finding drawn '
        + 'from twelve records will not outrank a certain one drawn from four hundred.',
    route: '/app/insights',
    // .finding, not .insight — the card was rebuilt around what is happening,
    // what it means and what to do, and the class went with it. A stale
    // selector here does not error, it just spotlights nothing, which is the
    // worst way for a guided walkthrough to fail.
    target: '.finding',
  },
  {
    title: 'Every number is one click from its rows',
    text: 'The chips under each finding are drill-throughs. This is the rule that separates a '
        + 'plausible demo from a credible product: if you cannot click into a number, nobody '
        + 'believes it. Try one after the walkthrough.',
    route: '/app/insights',
    target: '.evidence',
  },
  {
    title: 'Watch the loop close',
    text: 'NexABI has found leads nobody has contacted in two weeks, carrying real pipeline. '
        + 'The next step applies the recommendation for real — and the finding will retire '
        + 'itself, because the condition it detected will no longer be true.',
    route: '/app/leads',
    target: '.kpi:nth-child(4)',
  },
  {
    title: 'Action, executed',
    text: 'The follow-up sweep just ran against the actual records. The stale count, the '
        + 'pipeline-at-risk figure and the insight feed all moved together — because nothing '
        + 'here is scripted. If the action had changed nothing, the finding would still be '
        + 'sitting there.',
    route: '/app/leads',
    target: '.kpi:nth-child(4)',
    act: { component: 'leads', method: 'sweepStale', args: [] },
  },
  {
    title: 'Adaptive means it notices on its own',
    text: 'The clock just advanced 30 days and the business moved with it — leads arrived, '
        + 'deals closed, instalments fell due, and neglected leads went cold. The detectors '
        + 're-ran without being asked, and findings appeared that nobody prompted. That is the '
        + 'difference between a dashboard and an intelligence layer.',
    route: '/app/insights',
    target: '.feed',
    act: { component: 'time-machine', method: 'advance', args: [30] },
  },
  {
    title: 'Ask it anything',
    text: 'Natural language over the same metrics layer. Answers come back as a card — headline '
        + 'figure, chart, contributing signals, drill-through — not a paragraph. When it cannot '
        + 'answer it says so and offers what it can, because a confidently wrong number is the '
        + 'only failure that would actually discredit this.',
    route: '/app/ask?q=What+should+I+worry+about%3F',
    target: '.ask-answer',
  },
  {
    title: 'Prove it yourself',
    text: 'Settings has a CSV import. Drop in leads and watch the pipeline figure move and new '
        + 'findings appear. Nothing is hardcoded, so nothing can be — you can verify that in '
        + 'about ten seconds without reading any source.',
    route: '/app/settings',
    target: '.drop-zone',
  },
  {
    title: 'Data, insight, recommendation, action',
    text: 'The action log holds an entry for every applied recommendation, each with a before '
        + 'and an after — the evidence the loop actually closed rather than just dismissing a '
        + 'card. That is the whole product: business data in, decisions out, and a record of '
        + 'what changed.',
    route: '/app/activity',
    target: '.log-item',
    last: true,
  },
];

/* ------------------------------------------------------------------ state */

const read = () => {
  try { return JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch { return null; }
};
const write = (v) => {
  try { sessionStorage.setItem(KEY, JSON.stringify(v)); } catch { /* private mode */ }
};
const forget = () => {
  try { sessionStorage.removeItem(KEY); } catch { /* private mode */ }
};

let spot = null;
let panel = null;

/* -------------------------------------------------------------- painting */

function paint(index) {
  const step = STEPS[index];
  if (!step) return stop();

  removeChrome();

  // A missing target is not a failure — the copy stands on its own, so the
  // step simply runs without the ring rather than aborting the walkthrough.
  const target = step.target && document.querySelector(step.target);

  if (target) {
    const r = target.getBoundingClientRect();
    spot = el('div.tour-spot', {
      style: {
        top: `${r.top + scrollY - 8}px`,
        left: `${r.left + scrollX - 8}px`,
        width: `${r.width + 16}px`,
        height: `${r.height + 16}px`,
      },
    });
    document.body.appendChild(spot);
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  panel = el('div.tour-panel', [
    el('div.tour-panel__step', [
      el('span', { text: `Step ${index + 1} of ${STEPS.length}` }),
      el('span.spacer'),
      el('div.tour-dots', STEPS.map((_, i) => el('i', { class: i === index ? 'is-on' : '' }))),
    ]),
    el('div.tour-panel__title', { text: step.title }),
    el('p.tour-panel__text', { text: step.text }),
    el('div.row-2', { style: { marginTop: '16px' } }, [
      el('button.btn.btn--primary', { onclick: () => go(index + 1) }, [
        el('span', { text: step.last ? 'Start exploring' : 'Next' }),
        icon(step.last ? 'check' : 'right', 14),
      ]),
      el('button.btn.btn--ghost', { onclick: stop }, [
        el('span', { text: 'Skip the walkthrough' }),
      ]),
    ]),
  ]);

  document.body.appendChild(panel);
}

function removeChrome() {
  spot?.remove();
  panel?.remove();
  spot = null;
  panel = null;
}

/* --------------------------------------------------------------- driving */

/**
 * Move to a step.
 *
 * If the step mutates, the Livewire call runs first and the navigation waits
 * for it — the point of the step is that you see the result, so arriving
 * before the action landed would show the old world.
 */
async function go(index) {
  const step = STEPS[index];
  if (!step) return stop();

  write({ index });
  removeChrome();

  if (step.act) {
    try {
      await callLivewire(step.act);
    } catch (err) {
      console.warn('[tournament] step action did not run:', err.message);
    }
  }

  const here = location.pathname + location.search;

  if (step.route && step.route !== here) {
    location.assign(step.route);   // resumes from sessionStorage on load
    return;
  }

  setTimeout(() => paint(index), 300);
}

/**
 * Reach into a Livewire component already on the page.
 *
 * Deliberately not a bespoke endpoint: the walkthrough presses the same button
 * the user would, so whatever happens is exactly what happens for them.
 */
function callLivewire({ component, method, args }) {
  const found = [...document.querySelectorAll('[wire\\:id]')]
    .map((n) => window.Livewire?.find(n.getAttribute('wire:id')))
    .find((c) => c && typeof c.name === 'string'
      && (c.name === component || c.name.endsWith(`.${component}`) || c.name.endsWith(component)));

  if (!found) {
    return Promise.reject(new Error(`no live "${component}" component on this page`));
  }

  return found.call(method, ...args);
}

export function start() {
  write({ index: 0 });
  go(0);
}

export function stop() {
  removeChrome();
  forget();
}

/** Picks up where it left off after a navigation. */
export function resume() {
  const state = read();
  if (!state) return;
  if (!STEPS[state.index]) return stop();

  // The mutation already ran before we navigated; just paint on arrival.
  setTimeout(() => paint(state.index), 450);
}

document.addEventListener('click', (e) => {
  if (e.target.closest('[data-tournament]')) {
    e.preventDefault();
    start();
  }
});

// Started from the command palette rather than from the sidebar.
document.addEventListener('livewire:init', () => {
  window.Livewire.on('start-tournament', () => start());
});

addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && read()) stop();
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', resume);
} else {
  resume();
}

window.NexabiTour = { start, stop };

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NexABI — Pitch Deck</title>
<meta name="description" content="NexABI: Adaptive Business Intelligence for real estate. Full project deck.">

<link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/deck.css') }}">

<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230A0A0B'/><path d='M28 72V28h11l22 30V28h11v44H61L39 42v30z' fill='%23E0A82E'/></svg>">
</head>
<body class="deck">

<div class="deck-bar">
  <b style="color:var(--brand-500)">NexABI</b>
  <span>Pitch deck · 30 slides</span>
  <span style="margin-left:auto">Print or save as PDF — each slide is one page at 16:9</span>
</div>

<!-- ═══ 01 — Cover ═══════════════════════════════════════════════════ -->
<section class="slide slide--glow">
  <div class="s-center">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:44px">
      <span style="width:52px;height:52px;border-radius:15px;background:var(--brand-500);display:grid;place-items:center">
        <svg viewBox="0 0 100 100" width="30" height="30"><path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="#171614"/></svg>
      </span>
      <div>
        <div style="font-size:30px;font-weight:660;letter-spacing:-.035em;line-height:1">NexABI</div>
        <div style="font-size:11px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--text-3);margin-top:5px">Adaptive Business Intelligence</div>
      </div>
    </div>

    <h1 class="s-title s-title--xl">Your data already knows what's wrong.</h1>

    <p class="s-sub" style="font-size:21px;max-width:58ch;margin-top:26px">
      An AI intelligence layer for real-estate businesses. It reads everything in
      one place, tells you in plain English what needs fixing today, and gives you
      the button that fixes it.
    </p>

    <div class="s-pills" style="margin-top:38px">
      <span class="s-pill s-pill--gold">Lagos, Nigeria · Real estate</span>
      <span class="s-pill">HTML · CSS · vanilla JS</span>
      <span class="s-pill">No framework, no build step, no backend</span>
    </div>
  </div>
  <div class="slide__brand"><i></i>NexABI</div>
  <div class="slide__num">01</div>
</section>

<!-- ═══ 02 — The problem ═════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The problem</span>
  <h2 class="s-title">Agencies do not lack data. They lack the story in it.</h2>

  <div class="s-body s-2col">
    <div>
      <p class="s-sub" style="margin-top:0">
        A typical Lagos agency runs on six disconnected systems. Sales in a
        spreadsheet, leads in a CRM, payments in another spreadsheet, feedback in
        WhatsApp. Nobody can see across them, so nobody sees the pattern.
      </p>
      <ul class="s-list" style="margin-top:26px">
        <li><span class="tick">✕</span><span><b>Leads go cold silently.</b> Nobody notices until the quarter closes.</span></li>
        <li><span class="tick">✕</span><span><b>Campaigns burn cash</b> for months before anyone compares them.</span></li>
        <li><span class="tick">✕</span><span><b>Stock gets mispriced</b> because demand is never measured per unit.</span></li>
        <li><span class="tick">✕</span><span><b>Receivables drift</b> until a top client is 90 days late.</span></li>
        <li><span class="tick">✕</span><span><b>Complaints repeat</b> and are only ever read one at a time.</span></li>
      </ul>
    </div>

    <div class="s-center">
      <div class="s-card">
        <div class="s-card__t">And when a dashboard does exist…</div>
        <div class="s-card__d">
          It tells you revenue fell. It does not tell you <b>why</b>, <b>what to do
          about it</b>, or <b>whether what you did worked</b>. The decision — the
          only part that changes the business — is left entirely to the reader.
        </div>
      </div>
      <div class="s-note s-note--gold" style="margin-top:16px">
        <i></i>
        <span>Reporting is a solved problem. <b>Deciding is not.</b></span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>The problem</div>
  <div class="slide__num">02</div>
</section>

<!-- ═══ 03 — The core idea ═══════════════════════════════════════════ -->
<section class="slide slide--glow">
  <span class="s-kicker">The core idea</span>
  <h2 class="s-title">Business data → AI analysis → insight → recommendation → action.</h2>

  <div class="s-body">
    <div class="s-4col" style="height:auto">
      <div class="s-card">
        <div class="s-card__n">01 / DATA</div>
        <div class="s-card__t">Bring it together</div>
        <div class="s-card__d">Sales, leads, properties, campaigns, payments and reviews stop living in six spreadsheets.</div>
      </div>
      <div class="s-card">
        <div class="s-card__n">02 / ANALYSIS</div>
        <div class="s-card__t">Find what's off</div>
        <div class="s-card__d">Seven kinds of detector watch every area continuously — money leaking, leads stalling, prices drifting.</div>
      </div>
      <div class="s-card">
        <div class="s-card__n">03 / INSIGHT</div>
        <div class="s-card__t">Say it plainly</div>
        <div class="s-card__d">Worst first, in sentences you could read out in a meeting, with every figure one click from its rows.</div>
      </div>
      <div class="s-card s-card--gold">
        <div class="s-card__n">04 / ACTION</div>
        <div class="s-card__t">Actually do it</div>
        <div class="s-card__d">Every recommendation is a button. Press it and the data changes.</div>
      </div>
    </div>

    <div class="s-note s-note--gold" style="margin-top:30px">
      <i></i>
      <span>
        Most BI tools stop at step 3. <b>Step 4 is the product</b> — and because it
        writes back to the data, step 2 re-runs and the finding retires itself.
      </span>
    </div>
  </div>
  <div class="slide__brand"><i></i>The core idea</div>
  <div class="slide__num">03</div>
</section>

<!-- ═══ 04 — The loop is a circuit ═══════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Why it is different</span>
  <h2 class="s-title s-title--sm">The loop is a circuit, not a script.</h2>

  <div class="s-body s-2col--wide" style="display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:34px">
    <div>
      <p class="s-sub" style="margin-top:0;font-size:16.5px">
        Accept <b>“Schedule follow-up for 263 stale leads”</b> and this happens, in order:
      </p>
      <ul class="s-list" style="margin-top:22px">
        <li><span class="tick">1</span><span>263 lead records are mutated — contact clocks reset</span></li>
        <li><span class="tick">2</span><span>The store version bumps, invalidating the metrics that depend on them</span></li>
        <li><span class="tick">3</span><span>Detectors re-run — the stagnation signal drops from <b>263 to 0</b></span></li>
        <li><span class="tick">4</span><span>The KPI ticks down; the insight card <b>retires itself</b></span></li>
        <li><span class="tick">5</span><span>The action log records a <b>before / after diff</b></span></li>
      </ul>
    </div>

    <div class="s-center">
      <div class="s-quote s-quote--sm">If the action doesn't change anything, the finding stays on the screen.</div>
      <p class="s-cite">
        Nothing is scripted. That is the difference between a demo that plays a
        sequence and a product that responds. It is also why the action for
        chasing payments models realistic recovery — some instalments clear,
        most don't, and the figure moves by what actually cleared.
      </p>
    </div>
  </div>
  <div class="slide__brand"><i></i>The loop</div>
  <div class="slide__num">04</div>
</section>

<!-- ═══ 05 — Two products ════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">What we built</span>
  <h2 class="s-title s-title--sm">Two audiences, two products, one set of records.</h2>

  <div class="s-body s-2col">
    <div class="s-card">
      <div class="s-card__n">FOR THE BUSINESS</div>
      <div class="s-card__t" style="font-size:24px">Agency console</div>
      <div class="s-card__d">
        The full intelligence layer across every business area, with findings
        ranked for whichever role is signed in — CEO, sales manager, finance lead
        or agent.
      </div>
      <div class="s-pills" style="margin-top:6px">
        <span class="s-pill">Dashboard</span><span class="s-pill">Insights</span>
        <span class="s-pill">Ask NexABI</span><span class="s-pill">Sales</span>
        <span class="s-pill">Leads</span><span class="s-pill">Properties</span>
        <span class="s-pill">Marketing</span><span class="s-pill">Customers</span>
        <span class="s-pill">Finance</span><span class="s-pill">Feedback</span>
        <span class="s-pill">Analytics</span><span class="s-pill">Reports</span>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card__n">FOR THE BUYER</div>
      <div class="s-card__t" style="font-size:24px">Client portal</div>
      <div class="s-card__d">
        A buyer does not need a BI tool. They need four answers: <b>what did I buy,
        what do I owe, when is it due, and who do I call.</b> So the portal has no
        KPI grid, no insight feed and no charts nobody asked for.
      </div>
      <div class="s-pills" style="margin-top:6px">
        <span class="s-pill">My property</span><span class="s-pill">Payments</span>
        <span class="s-pill">Documents</span><span class="s-pill">Support</span>
      </div>
      <div class="s-note" style="margin-top:auto">
        <i></i><span>Both read the <b>same rows</b> — so the buyer's schedule and the agency's receivables can never disagree.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>What we built</div>
  <div class="slide__num">05</div>
</section>

<!-- ═══ 06 — Dashboard ═══════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Agency console · 01</span>
  <h2 class="s-title s-title--sm">The dashboard is a reading order, not a wall of charts.</h2>

  <div class="s-body s-2col--narrow" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);gap:30px">
    <div>
      <ul class="s-list">
        <li><span class="tick">1</span><span><b>Where the business stands</b> — two sentences, not eight figures</span></li>
        <li><span class="tick">2</span><span><b>The one decision worth making today</b>, with its button attached</span></li>
        <li><span class="tick">3</span><span><b>The money</b> — four tiles, each with a plain-English definition</span></li>
        <li><span class="tick">4</span><span><b>Where the problems are</b> — every area, one glance</span></li>
        <li><span class="tick">5</span><span><b>The evidence</b> — charts, last, because they are the proof not the point</span></li>
      </ul>
      <div class="s-note" style="margin-top:22px">
        <i></i><span>A BI product that leads with charts is asking the user to do the analysis themselves.</span>
      </div>
    </div>
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/dashboard-dark.png" alt="NexABI dashboard">
      <div class="s-shot__cap">The dashboard — briefing, decision bar, KPIs with definitions</div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Dashboard</div>
  <div class="slide__num">06</div>
</section>

<!-- ═══ 07 — Health strip ════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Agency console · 02</span>
  <h2 class="s-title s-title--sm">Twelve modules, legible in one glance.</h2>

  <div class="s-body" style="display:flex;flex-direction:column;gap:24px">
    <div class="s-shot" style="flex:1;min-height:0;overflow:hidden">
      <img src="../tools/shots/dashboard-health.png" alt="Area health strip and evidence charts"
           style="object-fit:cover;object-position:top left;height:100%">
    </div>
    <p class="s-sub" style="margin-top:0;font-size:16px;max-width:none">
      Each tile shows the <b>worst open finding</b> in that area and a severity dot.
      Without this, twelve modules is a navigation problem; with it, it is a picture.
      Every tile is a link straight into the finding.
    </p>
  </div>
  <div class="slide__brand"><i></i>Where the problems are</div>
  <div class="slide__num">07</div>
</section>

<!-- ═══ 08 — Insights ════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Agency console · 03</span>
  <h2 class="s-title s-title--sm">Findings that explain themselves — and come with a button.</h2>

  <div class="s-body s-2col--narrow" style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:30px">
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/insights-dark.png" alt="AI Insights feed">
      <div class="s-shot__cap">Ranked findings with narrative, evidence chips and executable recommendations</div>
    </div>
    <div>
      <p class="s-sub" style="margin-top:0;font-size:16px">Each card carries four things:</p>
      <ul class="s-list" style="margin-top:18px">
        <li><span class="tick">✓</span><span><b>A claim</b> — from a detector, not a template</span></li>
        <li><span class="tick">✓</span><span><b>A narrative</b> with the real figures written into it</span></li>
        <li><span class="tick">✓</span><span><b>Evidence chips</b> — one click to the exact rows</span></li>
        <li><span class="tick">✓</span><span><b>Recommendations</b> with estimated impact and effort</span></li>
      </ul>
      <div class="s-note" style="margin-top:22px">
        <i></i><span>Ranked by <b>severity × confidence × role weight × log(impact)</b>. A dramatic finding drawn from twelve records never outranks a certain one drawn from four hundred.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>AI Insights</div>
  <div class="slide__num">08</div>
</section>

<!-- ═══ 09 — Evidence rule ═══════════════════════════════════════════ -->
<section class="slide slide--glow">
  <div class="s-center">
    <span class="s-kicker">The rule the product is built around</span>
    <div class="s-quote">An insight you cannot click into is an insight nobody believes.</div>
    <p class="s-cite" style="max-width:64ch">
      Every figure in every finding carries a drill target to the rows that
      produced it. This is enforced by the test suite, not by discipline: the
      engine test fails if any signal ships without evidence, or without at least
      one drill-through.
    </p>
    <div class="s-figs" style="margin-top:44px;border-top:1px solid var(--border)">
      <div class="s-fig"><div class="n">100%</div><div class="k">Of findings carry evidence</div></div>
      <div class="s-fig"><div class="n">100%</div><div class="k">Of findings drill to rows</div></div>
      <div class="s-fig"><div class="n">0</div><div class="k">Hardcoded insights</div></div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Evidence</div>
  <div class="slide__num">09</div>
</section>

<!-- ═══ 10 — Ask NexABI ══════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Agency console · 04</span>
  <h2 class="s-title s-title--sm">Ask it in your own words.</h2>

  <div class="s-body s-2col--narrow" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);gap:30px">
    <div>
      <p class="s-sub" style="margin-top:0;font-size:16px">
        “Why are we closing fewer deals?” “Which sources send the best leads?”
        “How much are we owed?”
      </p>
      <p class="s-sub" style="font-size:16px">
        Answers come back as a <b>card</b> — headline figure, delta, chart,
        contributing signals, drill-through — not a wall of text.
      </p>
      <div class="s-note" style="margin-top:20px">
        <i></i><span>Real-estate BI is a <b>bounded domain</b>. A router that classifies intent, extracts entities and dispatches to the metrics layer answers a genuinely large share of real questions.</span>
      </div>
      <div class="s-note s-note--gold" style="margin-top:14px">
        <i></i><span>When it cannot answer, <b>it says so</b> and offers what it can. A confidently wrong number is the one failure that would discredit the whole product.</span>
      </div>
    </div>
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/ask-dark.png" alt="Ask NexABI answer card">
      <div class="s-shot__cap">Natural language over live metrics — with the parsed intent shown, so you can see when it misreads you</div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Ask NexABI</div>
  <div class="slide__num">10</div>
</section>

<!-- ═══ 11 — Coverage ════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Agency console · 05</span>
  <h2 class="s-title s-title--sm">The AI is not a chatbot in a corner. It runs across everything.</h2>

  <div class="s-body">
    <table class="s-table">
      <thead><tr><th style="width:150px">Area</th><th>What NexABI does there</th><th style="width:210px">Detector it uses</th></tr></thead>
      <tbody>
        <tr><td>Sales</td><td>Revenue trends, funnel erosion, agent cohorts closing at half the rate of others</td><td class="mono">trend · funnel · cohort</td></tr>
        <tr><td>Leads</td><td>Qualified leads going cold, source quality, high-score leads left unworked</td><td class="mono">stagnation · cohort</td></tr>
        <tr><td>Properties</td><td>Demand per available unit, underpriced segments, capital stuck in dead stock</td><td class="mono">concentration · threshold</td></tr>
        <tr><td>Marketing</td><td>Campaigns spending heavily and converting far below the channel baseline</td><td class="mono">cohort</td></tr>
        <tr><td>Customers</td><td>Detractors carrying real revenue, lifetime value concentration</td><td class="mono">threshold</td></tr>
        <tr><td>Finance</td><td>Overdue receivables, ageing, accounts needing a call rather than a chase</td><td class="mono">threshold</td></tr>
        <tr><td>Feedback</td><td>Complaint themes clustering — one is noise, thirty is a defect</td><td class="mono">anomaly</td></tr>
      </tbody>
    </table>
  </div>
  <div class="slide__brand"><i></i>Coverage</div>
  <div class="slide__num">11</div>
</section>

<!-- ═══ 12 — Leads ═══════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Module deep-dive · Leads</span>
  <h2 class="s-title s-title--sm">Red rows are the ones quietly costing you money.</h2>

  <div class="s-body s-2col--narrow" style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:30px">
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/leads-light.png" alt="Leads module">
      <div class="s-shot__cap">Leads — conversion by source, funnel, and the full table with stale rows flagged</div>
    </div>
    <div>
      <ul class="s-list">
        <li><span class="tick">✓</span><span>Every drill target from a lead insight lands here <b>with filters pre-applied</b></span></li>
        <li><span class="tick">✓</span><span>Rows flagged red have had <b>no contact for 14+ days</b></span></li>
        <li><span class="tick">✓</span><span>The funnel counts the <b>deepest stage ever reached</b>, so lost leads still count</span></li>
        <li><span class="tick">✓</span><span>One button sweeps the entire stale backlog</span></li>
      </ul>
      <div class="s-note" style="margin-top:22px">
        <i></i><span>Dropping lost leads from a funnel flatters every conversion figure in the product. We don't.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Leads</div>
  <div class="slide__num">12</div>
</section>

<!-- ═══ 13 — Finance ═════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Module deep-dive · Finance</span>
  <h2 class="s-title s-title--sm">Honest about what an action can actually achieve.</h2>

  <div class="s-body s-2col--narrow" style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:30px">
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/finance-dark.png" alt="Finance module">
      <div class="s-shot__cap">Receivables ageing, collection ratio and the accounts at risk</div>
    </div>
    <div>
      <p class="s-sub" style="margin-top:0;font-size:16px">
        Sending a payment reminder does not conjure payment. So the action models
        <b>realistic recovery by age</b> — fresher debt clears more often, 90-day
        debt rarely does.
      </p>
      <p class="s-sub" style="font-size:16px">
        Some instalments clear, most don't, and the overdue figure moves by the
        amount that actually cleared.
      </p>
      <div class="s-note s-note--gold" style="margin-top:22px">
        <i></i><span>A tool that always reports success is a tool you stop believing on the second use.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Finance</div>
  <div class="slide__num">13</div>
</section>

<!-- ═══ 14 — Client portal ═══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The client portal</span>
  <h2 class="s-title s-title--sm">The buyer gets their own front door — with no charts in it.</h2>

  <div class="s-body s-2col" style="gap:26px">
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/client-home.png" alt="Client portal home">
      <div class="s-shot__cap">My property — status, the unit, progress and the purchase journey</div>
    </div>
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/client-pay-dark.png" alt="Client payments">
      <div class="s-shot__cap">Payments — one hero figure, the schedule, downloadable receipts</div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Client portal</div>
  <div class="slide__num">14</div>
</section>

<!-- ═══ 15 — Portal detail ═══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The client portal · detail</span>
  <h2 class="s-title s-title--sm">Derived, not decorated.</h2>

  <div class="s-body s-3col" style="height:auto">
    <div class="s-card">
      <div class="s-card__t">Documents wait on real things</div>
      <div class="s-card__d">
        The deed of assignment genuinely waits on full settlement in a Nigerian
        instalment purchase. So the portal shows <b>what each pending document is
        waiting on</b> rather than pretending everything is ready.
      </div>
    </div>
    <div class="s-card">
      <div class="s-card__t">Status follows the clock</div>
      <div class="s-card__d">
        A stored status goes stale the moment time moves. Payment state is
        <b>derived from the current date</b> everywhere, so an instalment can never
        be past due and still say “scheduled”.
      </div>
    </div>
    <div class="s-card">
      <div class="s-card__t">It says what happens next</div>
      <div class="s-card__d">
        Late? It explains there is no penalty, but handover cannot be scheduled
        until it clears — and offers to <b>restructure the plan</b>, framed as the
        routine request it is.
      </div>
    </div>
  </div>

  <div class="s-note s-note--gold" style="margin-top:26px">
    <i></i><span>The portal and the console are two renderings of <b>one set of rows</b>. They cannot disagree, because there is nothing to disagree with.</span>
  </div>
  <div class="slide__brand"><i></i>Client portal</div>
  <div class="slide__num">15</div>
</section>

<!-- ═══ 16 — Landing + auth ══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Brand surface</span>
  <h2 class="s-title s-title--sm">Marketing persuades. Product informs. Two registers, one brand.</h2>

  <div class="s-body s-2col" style="gap:26px">
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/landing-hero.png" alt="Landing page">
      <div class="s-shot__cap">Landing — editorial: serif display, ruled figures, real product imagery</div>
    </div>
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/login-dark.png" alt="Sign in">
      <div class="s-shot__cap">Sign in — the role split decides which product you land in</div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Landing &amp; auth</div>
  <div class="slide__num">16</div>
</section>

<!-- ═══ 17 — Colour doctrine ═════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Design system · colour</span>
  <h2 class="s-title s-title--sm">Gold on screen means the intelligence layer is speaking.</h2>

  <div class="s-body s-2col">
    <div>
      <p class="s-sub" style="margin-top:0;font-size:16px">
        Gold is the brand, and it is <b>reserved</b> — insight cards, recommendation
        buttons, the Ask surface, active navigation. It is never a severity colour
        and never a chart series.
      </p>
      <div class="s-note" style="margin-top:20px">
        <i></i><span>Amber is also the universal “warning” colour. If brand and warning share a hue, severity stops meaning anything — so severity gets its own ramp.</span>
      </div>
      <div style="display:grid;gap:11px;margin-top:24px">
        <div class="s-swatch"><i style="background:#E0A82E"></i><span><b style="color:var(--text)">Brand gold</b> — AI moments and emphasis only</span></div>
        <div class="s-swatch"><i style="background:#E5484D"></i><span>Critical</span></div>
        <div class="s-swatch"><i style="background:#F76808"></i><span>High</span></div>
        <div class="s-swatch"><i style="background:#3E63DD"></i><span>Medium</span></div>
        <div class="s-swatch"><i style="background:#8B8D98"></i><span>Low</span></div>
      </div>
    </div>

    <div>
      <div class="s-card" style="height:100%">
        <div class="s-card__t">Charts: validated, not eyeballed</div>
        <div class="s-card__d">
          Six categorical slots, assigned in <b>fixed order, never cycled</b>. Run
          through a colourblind-safety validator in <b>both</b> themes:
        </div>
        <div style="display:grid;gap:9px;margin-top:6px">
          <div class="s-swatch"><i style="background:#2a78d6"></i><span>blue</span></div>
          <div class="s-swatch"><i style="background:#eb6834"></i><span>orange</span></div>
          <div class="s-swatch"><i style="background:#1baf7a"></i><span>aqua</span></div>
          <div class="s-swatch"><i style="background:#4a3aa7"></i><span>violet</span></div>
          <div class="s-swatch"><i style="background:#e87ba4"></i><span>magenta</span></div>
          <div class="s-swatch"><i style="background:#008300"></i><span>green</span></div>
        </div>
        <div class="s-card__d" style="margin-top:auto">
          <b>Deliberately excludes yellow and red.</b> Yellow would collide with the
          brand gold; red with the critical token. A series must never be
          mistakable for a status.
        </div>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Colour</div>
  <div class="slide__num">17</div>
</section>

<!-- ═══ 18 — Typography ══════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Design system · type &amp; layout</span>
  <h2 class="s-title s-title--sm">A landing page that looks like a dashboard sells nothing.</h2>

  <div class="s-body">
    <table class="s-table">
      <thead><tr><th style="width:190px"></th><th>Landing &amp; auth</th><th>Console &amp; portal</th></tr></thead>
      <tbody>
        <tr><td>Display type</td><td>Fraunces serif, up to 72px</td><td>None — the serif is opt-in and can never leak in</td></tr>
        <tr><td>UI type</td><td>IBM Plex Sans</td><td>IBM Plex Sans at 13px, tabular numerals throughout</td></tr>
        <tr><td>Density</td><td>Generous, ruled figures, whitespace</td><td>Dense and scannable — that is what a BI tool is for</td></tr>
        <tr><td>Imagery</td><td>Real product screenshots</td><td>Charts and tables</td></tr>
        <tr><td>Job</td><td>Persuade</td><td>Inform</td></tr>
      </tbody>
    </table>

    <div class="s-3col" style="margin-top:26px;height:auto">
      <div class="s-card"><div class="s-card__t">No decorative borders</div><div class="s-card__d">Severity is carried by a badge with a dot <b>and</b> a word. A coloured spine as well was decoration doing a job already done.</div></div>
      <div class="s-card"><div class="s-card__t">Every page explains itself</div><div class="s-card__d">All 18 screens open with one plain sentence saying what the page is for and what on it is worth acting on.</div></div>
      <div class="s-card"><div class="s-card__t">Fonts self-hosted</div><div class="s-card__d">SIL OFL, no CDN. The naira sign lives in the latin-ext subset — without it every price falls back to a different face.</div></div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Type &amp; layout</div>
  <div class="slide__num">18</div>
</section>

<!-- ═══ 19 — Architecture ════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Architecture</span>
  <h2 class="s-title s-title--sm">The tagline, made executable.</h2>

  <div class="s-body">
    <div class="s-layers">
      <div class="s-layer"><span class="s-layer__n">L0</span><span class="s-layer__t">Seed<span>generated fixtures — the simulated business</span></span><span class="s-layer__tag"></span></div>
      <div class="s-layer"><span class="s-layer__n">L1</span><span class="s-layer__t">Repository<span>every read goes through here</span></span><span class="s-layer__tag">swaps to REST</span></div>
      <div class="s-layer"><span class="s-layer__n">L2</span><span class="s-layer__t">Metrics<span>pure functions → KPIs, series, cohorts</span></span><span class="s-layer__tag">Analysis</span></div>
      <div class="s-layer"><span class="s-layer__n">L3</span><span class="s-layer__t">Detectors<span>rules over metrics → signals</span></span><span class="s-layer__tag">Analysis</span></div>
      <div class="s-layer"><span class="s-layer__n">L4</span><span class="s-layer__t">Insights<span>score, dedupe, rank, cap</span></span><span class="s-layer__tag">Insight</span></div>
      <div class="s-layer"><span class="s-layer__n">L5</span><span class="s-layer__t">Playbooks<span>insight → executable action descriptors</span></span><span class="s-layer__tag">Recommendation</span></div>
      <div class="s-layer s-layer--live"><span class="s-layer__n">L6</span><span class="s-layer__t">Action bus<span>execute → mutate → log → bump version</span></span><span class="s-layer__tag">Action</span></div>
      <div class="s-layer"><span class="s-layer__n">L7</span><span class="s-layer__t">Views<span>subscribe, render, dispatch</span></span><span class="s-layer__tag"></span></div>
    </div>
    <div class="s-note" style="margin-top:20px">
      <i></i><span>Layers 2–6 are <b>pure functions over data</b>. They lift server-side verbatim the day there is a backend — nothing in them knows where the data came from.</span>
    </div>
  </div>
  <div class="slide__brand"><i></i>Architecture</div>
  <div class="slide__num">19</div>
</section>

<!-- ═══ 20 — The AI seam ═════════════════════════════════════════════ -->
<section class="slide slide--glow">
  <span class="s-kicker">The honest part</span>
  <h2 class="s-title s-title--sm">The intelligence is real. Only the wording is generated.</h2>

  <div class="s-body s-2col">
    <div>
      <table class="s-table">
        <thead><tr><th>Step</th><th style="width:110px">Real?</th></tr></thead>
        <tbody>
          <tr><td>Aggregation, trends, cohorts, funnels</td><td style="color:var(--pos);font-weight:600">Yes</td></tr>
          <tr><td>Detection — rules and statistics</td><td style="color:var(--pos);font-weight:600">Yes</td></tr>
          <tr><td>Scoring, ranking, dedupe</td><td style="color:var(--pos);font-weight:600">Yes</td></tr>
          <tr><td>Recommendation → executable action</td><td style="color:var(--pos);font-weight:600">Yes</td></tr>
          <tr><td>Turning a finding into a paragraph</td><td style="color:var(--brand-500);font-weight:600">Templated</td></tr>
        </tbody>
      </table>
    </div>
    <div class="s-center">
      <p class="s-sub" style="margin-top:0;font-size:16.5px">
        “47 leads have had no contact in 14+ days, carrying ₦8.2B of pipeline” is
        <b>a filter and a sum</b>. It does not need a model — it needs a detector.
      </p>
      <p class="s-sub" style="font-size:16.5px">
        What genuinely needs a model is <b>phrasing</b>. So phrasing is the only
        thing behind the seam.
      </p>
      <div class="s-note s-note--gold" style="margin-top:20px">
        <i></i><span>Swapping to a hosted model changes <b>one file</b>. Layers 2–7 only ever receive a string; they never learn where it came from.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>The AI seam</div>
  <div class="slide__num">20</div>
</section>

<!-- ═══ 21 — Data ════════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Data</span>
  <h2 class="s-title s-title--sm">Generated, not authored — and identical every single run.</h2>

  <div class="s-body s-2col">
    <div>
      <p class="s-sub" style="margin-top:0;font-size:16px">
        A seeded simulation of ~20 months of a Lagos agency. Byte-identical on
        every machine, so the demo never surprises you and a bug is always
        reproducible.
      </p>
      <div class="s-figs" style="border-top:0;padding-top:14px;grid-template-columns:repeat(2,1fr);gap:20px">
        <div class="s-fig"><div class="n">1,420</div><div class="k">Leads</div></div>
        <div class="s-fig"><div class="n">224</div><div class="k">Properties</div></div>
        <div class="s-fig"><div class="n">~620</div><div class="k">Deals</div></div>
        <div class="s-fig"><div class="n">10,500</div><div class="k">Recorded events</div></div>
      </div>
    </div>
    <div class="s-center">
      <div class="s-card s-card--gold">
        <div class="s-card__t">Planted narratives</div>
        <div class="s-card__d">
          The generator is biased so the data genuinely <b>contains</b> the
          conditions the detectors will find. The detectors are <b>not told</b>
          about any of them.
        </div>
        <div class="s-card__d">
          Delete the failing campaign and its insight disappears on its own.
          Import a spreadsheet and new ones appear. That is the difference between
          a computed product and a decorated one.
        </div>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Data</div>
  <div class="slide__num">21</div>
</section>

<!-- ═══ 22 — Planted narratives ══════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Data · the seven planted conditions</span>
  <h2 class="s-title s-title--sm">Written into the data. Rediscovered independently.</h2>

  <div class="s-body">
    <table class="s-table">
      <thead><tr><th style="width:290px">Planted into the dataset</th><th style="width:170px">Found by</th><th>What appears on screen</th></tr></thead>
      <tbody>
        <tr><td>47 stale high-budget leads</td><td class="mono">stagnation</td><td>“263 open leads have gone untouched for 14+ days”</td></tr>
        <tr><td>A heavy-spend, low-conversion campaign</td><td class="mono">cohort</td><td>“Meta — Lagos Mainland Push is converting 4.3× worse”</td></tr>
        <tr><td>3-bed Lekki flats: 4× demand, 4 units</td><td class="mono">concentration</td><td>“…drawing 4.3× normal demand on 4 units of stock”</td></tr>
        <tr><td>Overdue instalments incl. a top-10 account</td><td class="mono">threshold</td><td>“24 accounts are carrying overdue instalments”</td></tr>
        <tr><td>“Response time” complaints clustering</td><td class="mono">anomaly</td><td>“…now your most-cited complaint — 30 mentions in 60 days”</td></tr>
        <tr><td>Two agent cohorts, divergent close rates</td><td class="mono">cohort</td><td>“7 agents are closing at less than half the rate…”</td></tr>
        <tr><td>Viewing → negotiating conversion decaying</td><td class="mono">funnel</td><td>“…has fallen 50 points over 12 weeks”</td></tr>
      </tbody>
    </table>
    <div class="s-note" style="margin-top:20px">
      <i></i><span>Every one of these is <b>listed in-app under Settings</b>, so the whole arrangement is auditable by anyone who wants to check it.</span>
    </div>
  </div>
  <div class="slide__brand"><i></i>Planted narratives</div>
  <div class="slide__num">22</div>
</section>

<!-- ═══ 23 — Detectors ═══════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The engine</span>
  <h2 class="s-title s-title--sm">Seven archetypes cover essentially all of business intelligence.</h2>

  <div class="s-body">
    <div class="s-4col" style="height:auto">
      <div class="s-card"><div class="s-card__n">THRESHOLD</div><div class="s-card__d">A value crosses a line you set.</div></div>
      <div class="s-card"><div class="s-card__n">TREND</div><div class="s-card__d">Least-squares slope over a window — is it drifting?</div></div>
      <div class="s-card"><div class="s-card__n">ANOMALY</div><div class="s-card__d">Z-score against the trailing mean — is this unusual?</div></div>
      <div class="s-card"><div class="s-card__n">COHORT</div><div class="s-card__d">Segment A versus segment B, effect size gated.</div></div>
    </div>
    <div class="s-4col" style="height:auto;margin-top:18px">
      <div class="s-card"><div class="s-card__n">STAGNATION</div><div class="s-card__d">Time since last event exceeds a threshold.</div></div>
      <div class="s-card"><div class="s-card__n">FUNNEL</div><div class="s-card__d">Stage conversion below its own baseline.</div></div>
      <div class="s-card"><div class="s-card__n">CONCENTRATION</div><div class="s-card__d">Too much depending on too few.</div></div>
      <div class="s-card s-card--gold"><div class="s-card__n">RETURNING ZERO</div><div class="s-card__d">A first-class outcome. If the business is healthy, NexABI says nothing.</div></div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Detectors</div>
  <div class="slide__num">23</div>
</section>

<!-- ═══ 24 — Statistical care ════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The engine · rigour</span>
  <h2 class="s-title s-title--sm">The failure modes we designed against.</h2>

  <div class="s-body">
    <table class="s-table">
      <thead><tr><th style="width:250px">The trap</th><th>What we do instead</th></tr></thead>
      <tbody>
        <tr><td>Thin denominators</td><td>14 leads and 0 closes reports “50× worse” — precise-looking and meaningless. <b>Laplace smoothing</b> pulls small samples toward the prior.</td></tr>
        <tr><td>Immature cohorts</td><td>Leads captured last week haven't had time to convert. Including them <b>manufactures a decline in every dataset</b>, so recent weeks are excluded from funnel trends.</td></tr>
        <tr><td>Weekly noise</td><td>A rate built on 5–12 leads swings 0–100% on chance alone. Trends are fitted to a <b>smoothed series</b>, and the raw one is what gets charted.</td></tr>
        <tr><td>Survivor bias</td><td>A funnel that drops lost leads flatters every conversion figure. We rank on the <b>deepest stage ever reached</b>.</td></tr>
        <tr><td>Everything is critical</td><td>Nine of eleven findings marked critical means the label means nothing. Bands are <b>deliberately hard to reach</b> at the top.</td></tr>
        <tr><td>Confident wrongness</td><td>Confidence is <b>gated on sample size</b> and always shown. Unanswerable questions are refused, not guessed.</td></tr>
      </tbody>
    </table>
  </div>
  <div class="slide__brand"><i></i>Rigour</div>
  <div class="slide__num">24</div>
</section>

<!-- ═══ 25 — Demo controls ═══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Prove it yourself</span>
  <h2 class="s-title s-title--sm">Four controls that let anyone test the claim in seconds.</h2>

  <div class="s-body s-2col" style="gap:20px">
    <div class="s-card">
      <div class="s-card__n">⏱ TIME MACHINE</div>
      <div class="s-card__t">Move the clock, the business moves</div>
      <div class="s-card__d">
        Advancing time doesn't just change the date — it <b>simulates the period</b>.
        Leads arrive, deals close, instalments fall due, leads go cold. New findings
        appear that nobody prompted. This is the literal demonstration of
        <b>“Adaptive”</b>.
      </div>
    </div>
    <div class="s-card">
      <div class="s-card__n">📥 CSV IMPORT</div>
      <div class="s-card__t">Bring your own data</div>
      <div class="s-card__d">
        Drop a spreadsheet of leads into Settings and watch the pipeline figure move
        and new findings appear. Parsed entirely in the browser — nothing is
        uploaded. <b>Takes about ten seconds to disprove “it's hardcoded”.</b>
      </div>
    </div>
    <div class="s-card">
      <div class="s-card__n">👤 ROLE SWITCHER</div>
      <div class="s-card__t">Same data, different lens</div>
      <div class="s-card__d">
        CEO, sales manager, finance lead, agent. Switching <b>re-ranks</b> the feed —
        it never hides anything, because a finance risk does not stop existing when
        a sales manager logs in.
      </div>
    </div>
    <div class="s-card s-card--gold">
      <div class="s-card__n">🏆 TOURNAMENT MODE</div>
      <div class="s-card__t">A guided run, not a slideshow</div>
      <div class="s-card__d">
        Ten steps that drive the <b>real application</b> — navigating real routes,
        executing a real action, advancing the real clock. Skippable, and it exits
        into free exploration.
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Demo controls</div>
  <div class="slide__num">25</div>
</section>

<!-- ═══ 26 — Engineering ═════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Engineering</span>
  <h2 class="s-title s-title--sm">91 automated checks, and they have caught real bugs.</h2>

  <div class="s-body s-2col">
    <div>
      <div class="s-card" style="margin-bottom:16px">
        <div class="s-card__t">Engine suite · 48 checks</div>
        <div class="s-card__d">
          Runs headless, no browser. Asserts each planted narrative is
          independently rediscovered, every insight is executable, accepting a
          recommendation <b>changes the data and retires the insight</b>, and
          unanswerable questions are refused.
        </div>
      </div>
      <div class="s-card">
        <div class="s-card__t">Browser suite · 43 checks</div>
        <div class="s-card__d">
          Drives 7 static pages and 14 console routes over the DevTools Protocol.
          Fails on any console error, horizontal overflow, or missing webfont.
          Writes the screenshots this deck is built from.
        </div>
      </div>
    </div>

    <div>
      <p class="s-sub" style="margin-top:0;font-size:15.5px">Bugs the suite has caught — each one now has a permanent guard:</p>
      <ul class="s-list" style="margin-top:16px;gap:11px">
        <li><span class="tick">✓</span><span style="font-size:14.5px"><b>Jekyll silently dropped a file.</b> One underscore-prefixed module took the whole Business section down when deployed, while working perfectly offline.</span></li>
        <li><span class="tick">✓</span><span style="font-size:14.5px"><b>A form that wasn't a form.</b> An invalid element name produced a fake select and textarea. Browsers don't throw on that.</span></li>
        <li><span class="tick">✓</span><span style="font-size:14.5px"><b>Stale payment status.</b> An instalment showed “Scheduled” and “21 days ago” at once.</span></li>
        <li><span class="tick">✓</span><span style="font-size:14.5px"><b>Empty weekly buckets.</b> A week key returning a day number silently emptied every funnel trend.</span></li>
      </ul>
    </div>
  </div>
  <div class="slide__brand"><i></i>Engineering</div>
  <div class="slide__num">26</div>
</section>

<!-- ═══ 27 — Real vs simulated ═══════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Full disclosure</span>
  <h2 class="s-title s-title--sm">What is real, and what is standing in for something.</h2>

  <div class="s-body">
    <table class="s-table">
      <thead><tr><th style="width:230px">Capability</th><th style="width:200px">In this build</th><th>With a backend</th></tr></thead>
      <tbody>
        <tr><td>Analysis &amp; detection</td><td style="color:var(--pos);font-weight:600">Fully real</td><td>Lifts server-side unchanged</td></tr>
        <tr><td>Recommendations &amp; actions</td><td style="color:var(--pos);font-weight:600">Fully real</td><td>Same code, real writes</td></tr>
        <tr><td>Prose generation</td><td style="color:var(--brand-500);font-weight:600">Templated</td><td>Swap one file for a hosted model</td></tr>
        <tr><td>Authentication</td><td>Role selection only</td><td>Auth service — the form is honest about this</td></tr>
        <tr><td>Persistence</td><td>localStorage + mutation replay</td><td>Postgres</td></tr>
        <tr><td>Live ingestion</td><td>CSV drop</td><td>Connectors and webhooks</td></tr>
        <tr><td>Scheduled jobs</td><td>Time machine</td><td>Cron workers</td></tr>
      </tbody>
    </table>
    <div class="s-note s-note--gold" style="margin-top:22px">
      <i></i><span>Only <b>two files</b> know where anything comes from: the repository layer and the AI provider. Everything else is portable by construction.</span>
    </div>
  </div>
  <div class="slide__brand"><i></i>Disclosure</div>
  <div class="slide__num">27</div>
</section>

<!-- ═══ 28 — Migration ═══════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">What happens next</span>
  <h2 class="s-title s-title--sm">The engine is backend-ready. Only the data source is local.</h2>

  <div class="s-body s-3col" style="height:auto">
    <div class="s-card">
      <div class="s-card__n">PHASE 1</div>
      <div class="s-card__t">Real data in</div>
      <div class="s-card__d">Replace the repository layer with REST calls. Connectors for the CRMs and spreadsheets agencies already use. <b>Layers 2–7 untouched.</b></div>
    </div>
    <div class="s-card">
      <div class="s-card__n">PHASE 2</div>
      <div class="s-card__t">Live language</div>
      <div class="s-card__d">Swap the AI provider for a server route calling a hosted model. It receives <b>already-computed facts</b>, never raw rows — so it is fast, cheap, and cannot invent a figure.</div>
    </div>
    <div class="s-card">
      <div class="s-card__n">PHASE 3</div>
      <div class="s-card__t">Always on</div>
      <div class="s-card__d">Detectors move to scheduled workers. Findings arrive by email and WhatsApp instead of waiting for someone to open a tab.</div>
    </div>
  </div>

  <div class="s-note" style="margin-top:26px">
    <i></i><span>The API key cannot live in client JavaScript. The live provider proxies through a server route by design — that constraint is in the architecture already, not bolted on later.</span>
  </div>
  <div class="slide__brand"><i></i>Roadmap</div>
  <div class="slide__num">28</div>
</section>

<!-- ═══ 29 — By the numbers ══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">By the numbers</span>
  <h2 class="s-title s-title--sm">What was built.</h2>

  <div class="s-body s-center">
    <div class="s-figs" style="border-top:0;grid-template-columns:repeat(4,1fr);gap:30px">
      <div class="s-fig"><div class="n">18</div><div class="k">Screens across two products</div></div>
      <div class="s-fig"><div class="n">7</div><div class="k">Detector archetypes</div></div>
      <div class="s-fig"><div class="n">18</div><div class="k">Executable action types</div></div>
      <div class="s-fig"><div class="n">91</div><div class="k">Automated checks, all passing</div></div>
    </div>
    <div class="s-figs" style="margin-top:34px;grid-template-columns:repeat(4,1fr);gap:30px">
      <div class="s-fig"><div class="n">8</div><div class="k">Chart types, hand-rolled in SVG</div></div>
      <div class="s-fig"><div class="n">0</div><div class="k">Runtime dependencies</div></div>
      <div class="s-fig"><div class="n">0</div><div class="k">Build steps</div></div>
      <div class="s-fig"><div class="n">0</div><div class="k">Hardcoded insights</div></div>
    </div>
    <div class="s-note" style="margin-top:38px">
      <i></i><span>Runs from any static host. <code style="font-family:var(--mono);font-size:13px;color:var(--brand-500)">npx serve .</code> and open the address — that is the whole setup.</span>
    </div>
  </div>
  <div class="slide__brand"><i></i>By the numbers</div>
  <div class="slide__num">29</div>
</section>

<!-- ═══ 30 — Close ═══════════════════════════════════════════════════ -->
<section class="slide slide--glow">
  <div class="s-center">
    <span class="s-kicker">In one sentence</span>
    <div class="s-quote" style="font-size:46px;max-width:26ch">
      NexABI turns scattered business data into decisions — and proves the decision worked.
    </div>
    <p class="s-cite" style="max-width:62ch;font-size:16px">
      Reporting tells you what happened. NexABI tells you what is wrong, what to do
      about it, and whether doing it worked — then takes the warning down by itself.
    </p>

    <div class="s-pills" style="margin-top:40px">
      <span class="s-pill s-pill--gold">Agency console</span>
      <span class="s-pill s-pill--gold">Client portal</span>
      <span class="s-pill">Landing &amp; auth</span>
      <span class="s-pill">Tournament mode</span>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:52px">
      <span style="width:34px;height:34px;border-radius:10px;background:var(--brand-500);display:grid;place-items:center">
        <svg viewBox="0 0 100 100" width="20" height="20"><path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="#171614"/></svg>
      </span>
      <div>
        <div style="font-size:19px;font-weight:650;letter-spacing:-.03em;line-height:1.1">NexABI</div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--text-3);margin-top:3px">Adaptive Business Intelligence</div>
      </div>
    </div>
  </div>
  <div class="slide__num">30</div>
</section>

</body>
</html>

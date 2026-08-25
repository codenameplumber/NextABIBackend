<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NexABI — Investor Deck</title>
<meta name="description" content="NexABI: software that tells estate agencies what is losing them money, and fixes it.">

<link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/deck.css') }}">

<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230A0A0B'/><path d='M28 72V28h11l22 30V28h11v44H61L39 42v30z' fill='%23E0A82E'/></svg>">
</head>
<body class="deck">

<div class="deck-bar">
  <b style="color:var(--brand-500)">NexABI</b>
  <span>Investor deck · 16 slides</span>
  <span style="margin-left:auto">Orange dashed blocks are yours to fill before presenting</span>
</div>

<!-- ═══ 01 — Cover ═══════════════════════════════════════════════════ -->
<section class="slide slide--glow">
  <div class="s-center">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:52px">
      <span style="width:52px;height:52px;border-radius:15px;background:var(--brand-500);display:grid;place-items:center">
        <svg viewBox="0 0 100 100" width="30" height="30"><path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="#171614"/></svg>
      </span>
      <div>
        <div style="font-size:30px;font-weight:660;letter-spacing:-.035em;line-height:1">NexABI</div>
        <div style="font-size:11px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--text-3);margin-top:5px">Adaptive Business Intelligence</div>
      </div>
    </div>

    <h1 class="s-title" style="font-size:58px;max-width:19ch">
      Estate agencies lose money in ways they cannot see.
    </h1>

    <p class="s-sub" style="font-size:23px;max-width:52ch;margin-top:28px;color:var(--text)">
      NexABI finds it, tells them in plain English, and fixes it in one click.
    </p>

    <div class="s-pills" style="margin-top:46px">
      <span class="s-pill s-pill--gold">Working product, not a mockup</span>
      <span class="s-pill">Lagos first, Nigeria next</span>
      <span class="s-pill">B2B SaaS · monthly subscription</span>
    </div>
  </div>
  <div class="slide__brand"><i></i>NexABI</div>
  <div class="slide__num">01</div>
</section>

<!-- ═══ 02 — The problem, in money ════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The problem</span>
  <h2 class="s-title s-title--sm">A mid-size agency leaks more each year than the software to stop it costs.</h2>

  <div class="s-body s-2col">
    <div>
      <p class="s-sub" style="margin-top:0;font-size:16.5px">
        Take an agency closing <b>₦4B a year</b> with 18 agents — an ordinary Lekki
        or Ikoyi firm, not a giant. Here is what routinely goes missing, and none
        of it shows up on a P&amp;L until the year is over:
      </p>

      <div class="s-math" style="margin-top:22px">
        <div class="s-math__row">
          <span><b>Qualified leads that go cold</b><br>
            <span style="font-size:13px;color:var(--text-4)">nobody called them for a fortnight</span></span>
          <span class="s-math__v">₦180M</span>
        </div>
        <div class="s-math__row">
          <span><b>Ad spend on channels that never close</b><br>
            <span style="font-size:13px;color:var(--text-4)">discovered at the annual review, if at all</span></span>
          <span class="s-math__v">₦40M</span>
        </div>
        <div class="s-math__row">
          <span><b>Stock priced below its own demand</b><br>
            <span style="font-size:13px;color:var(--text-4)">nobody measures inquiries per available unit</span></span>
          <span class="s-math__v">₦95M</span>
        </div>
        <div class="s-math__row s-math__row--total">
          <span><b>Recoverable in year one</b></span>
          <span class="s-math__v">₦315M</span>
        </div>
      </div>
      <span class="s-assume">Illustrative model — swap the inputs for a real customer's figures in diligence</span>
    </div>

    <div class="s-center">
      <div class="s-hero-stat">7.9<span>%</span></div>
      <p class="s-sub" style="font-size:19px;max-width:34ch;margin-top:18px">
        of annual revenue, leaking quietly, in a business that already has all the
        data needed to spot it.
      </p>
      <div class="s-note s-note--gold" style="margin-top:30px">
        <i></i>
        <span>NexABI costs <b>₦2.2M a year</b>. It has to recover <b>0.7%</b> of that leak to pay for itself.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>The problem</div>
  <div class="slide__num">02</div>
</section>

<!-- ═══ 03 — Why it persists ═════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Why it hasn't been solved</span>
  <h2 class="s-title s-title--sm">Everyone has the data. Nobody looks across it.</h2>

  <div class="s-body">
    <div class="s-3col" style="height:auto">
      <div class="s-card">
        <div class="s-card__t">Six systems, no seam</div>
        <div class="s-card__d">
          Sales in a spreadsheet. Leads in a CRM nobody updates. Payments in a
          second spreadsheet. Complaints in WhatsApp. Each one is fine alone.
          <b>The money is lost between them.</b>
        </div>
      </div>
      <div class="s-card">
        <div class="s-card__t">Dashboards report, they don't decide</div>
        <div class="s-card__d">
          Power BI will tell you revenue fell. It will not tell you why, what to do,
          or whether it worked. <b>The hard part is left to the reader</b> — and the
          reader is busy selling.
        </div>
      </div>
      <div class="s-card">
        <div class="s-card__t">Nobody has a data analyst</div>
        <div class="s-card__d">
          A 20-person agency will never hire one. So the insight that would have
          saved ₦180M is technically available and <b>practically invisible</b>.
        </div>
      </div>
    </div>

    <div class="s-note s-note--gold" style="margin-top:30px">
      <i></i>
      <span>
        The gap is not reporting software. It is <b>the analyst nobody can afford</b> —
        the person who reads across everything each morning and says
        “call these 47 people today”.
      </span>
    </div>
  </div>
  <div class="slide__brand"><i></i>Why it persists</div>
  <div class="slide__num">03</div>
</section>

<!-- ═══ 04 — The solution ════════════════════════════════════════════ -->
<section class="slide slide--glow">
  <span class="s-kicker">The solution</span>
  <h2 class="s-title" style="max-width:24ch">NexABI is that analyst, for ₦180,000 a month.</h2>

  <div class="s-body">
    <div class="s-4col" style="height:auto">
      <div class="s-card">
        <div class="s-card__n">01</div>
        <div class="s-card__t">It reads everything</div>
        <div class="s-card__d">Sales, leads, properties, campaigns, payments and reviews — in one place, continuously.</div>
      </div>
      <div class="s-card">
        <div class="s-card__n">02</div>
        <div class="s-card__t">It spots what's wrong</div>
        <div class="s-card__d">Money leaking, leads stalling, stock mispriced, clients going quiet — before it shows up in the numbers.</div>
      </div>
      <div class="s-card">
        <div class="s-card__n">03</div>
        <div class="s-card__t">It says it in English</div>
        <div class="s-card__d">“47 leads worth ₦8.2B haven't been called in two weeks.” No charts to interpret, no training needed.</div>
      </div>
      <div class="s-card s-card--gold">
        <div class="s-card__n">04</div>
        <div class="s-card__t">It fixes it</div>
        <div class="s-card__d">One button assigns the follow-ups, pauses the campaign, reprices the stock. Then it checks whether it worked.</div>
      </div>
    </div>

    <div class="s-note" style="margin-top:28px">
      <i></i>
      <span>
        Step 4 is what nobody else does. <b>Every competitor stops at step 3</b> and
        hands the customer a chart.
      </span>
    </div>
  </div>
  <div class="slide__brand"><i></i>The solution</div>
  <div class="slide__num">04</div>
</section>

<!-- ═══ 05 — Product ═════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The product</span>
  <h2 class="s-title s-title--sm">It opens on the decision, not the dashboard.</h2>

  <div class="s-body s-2col--narrow" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.55fr);gap:30px">
    <div>
      <ul class="s-list">
        <li><span class="tick">✓</span><span><b>“Start here”</b> — the single most valuable thing to do today, with the button attached</span></li>
        <li><span class="tick">✓</span><span><b>Every figure is clickable</b> — one tap to the exact records behind it</span></li>
        <li><span class="tick">✓</span><span><b>Ask it anything</b> — “which sources send the best leads?” — and get a straight answer</span></li>
        <li><span class="tick">✓</span><span><b>Buyers get their own portal</b> — what they owe, when it's due, who to call</span></li>
      </ul>
      <div class="s-note" style="margin-top:24px">
        <i></i><span>An agency owner who has never opened a BI tool can use this on day one. <b>That is the design constraint</b>, not a nice-to-have.</span>
      </div>
    </div>
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/dashboard-dark.png" alt="NexABI dashboard">
      <div class="s-shot__cap">The working product — every number computed live from the customer's own data</div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Product</div>
  <div class="slide__num">05</div>
</section>

<!-- ═══ 06 — Product in use ══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The product · in use</span>
  <h2 class="s-title s-title--sm">One product for the business. One for their buyer.</h2>

  <div class="s-body s-3col" style="gap:20px">
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/insights-dark.png" alt="Findings">
      <div class="s-shot__cap">Findings, worst first — each with a button that fixes it</div>
    </div>
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/ask-dark.png" alt="Ask">
      <div class="s-shot__cap">Ask in plain English, get a straight answer</div>
    </div>
    <div class="s-shot s-shot--fit">
      <img src="../tools/shots/client-home.png" alt="Client portal">
      <div class="s-shot__cap">The buyer's portal — a second product, same data</div>
    </div>
  </div>

  <div class="s-note s-note--gold" style="margin-top:22px">
    <i></i>
    <span>The buyer portal is not a feature. It is <b>a second reason to renew</b> — once an agency's clients log in to check payments, switching costs jump.</span>
  </div>
  <div class="slide__brand"><i></i>Product</div>
  <div class="slide__num">06</div>
</section>

<!-- ═══ 07 — Why we win ══════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Competition</span>
  <h2 class="s-title s-title--sm">Everyone else sells storage or charts. We sell the decision.</h2>

  <div class="s-body">
    <table class="s-matrix">
      <thead>
        <tr>
          <th style="width:230px"></th>
          <th>Spreadsheets</th>
          <th>CRM<br><span style="font-weight:400;text-transform:none;letter-spacing:0">Zoho, HubSpot</span></th>
          <th>Generic BI<br><span style="font-weight:400;text-transform:none;letter-spacing:0">Power BI, Tableau</span></th>
          <th class="is-us">NexABI</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>Stores the data</td><td class="yes">Yes</td><td class="yes">Yes</td><td class="yes">Yes</td><td class="is-us yes">Yes</td></tr>
        <tr><td>Reads across every area</td><td class="no">No</td><td class="no">Leads only</td><td class="yes">Yes</td><td class="is-us yes">Yes</td></tr>
        <tr><td>Finds problems unprompted</td><td class="no">No</td><td class="no">No</td><td class="no">You build the alert</td><td class="is-us yes">Yes</td></tr>
        <tr><td>Explains it in plain English</td><td class="no">No</td><td class="no">No</td><td class="no">No</td><td class="is-us yes">Yes</td></tr>
        <tr><td>Fixes it in one click</td><td class="no">No</td><td class="no">No</td><td class="no">No</td><td class="is-us yes">Yes</td></tr>
        <tr><td>Checks whether the fix worked</td><td class="no">No</td><td class="no">No</td><td class="no">No</td><td class="is-us yes">Yes</td></tr>
        <tr><td>Needs an analyst to run it</td><td class="no">—</td><td class="no">—</td><td class="no">Yes</td><td class="is-us yes">No</td></tr>
        <tr><td>Built for Nigerian property</td><td class="no">No</td><td class="no">No</td><td class="no">No</td><td class="is-us yes">Yes</td></tr>
      </tbody>
    </table>
    <div class="s-note" style="margin-top:20px">
      <i></i><span>Instalment plans, C&nbsp;of&nbsp;O timelines, naira pricing, WhatsApp-first agents. <b>A global tool will not build this for a market this size</b> — that window is the opportunity.</span>
    </div>
  </div>
  <div class="slide__brand"><i></i>Competition</div>
  <div class="slide__num">07</div>
</section>

<!-- ═══ 08 — Market ══════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Market</span>
  <h2 class="s-title s-title--sm">Built bottom-up, so you can argue with every input.</h2>

  <div class="s-body s-2col">
    <div>
      <div class="s-math">
        <div class="s-math__row">
          <span>Registered estate firms in Lagos</span>
          <span class="s-math__v">~5,000</span>
        </div>
        <div class="s-math__op">× firms with 5+ agents (our floor) — 30%</div>
        <div class="s-math__row">
          <span><b>Serviceable, Lagos</b></span>
          <span class="s-math__v">1,500</span>
        </div>
        <div class="s-math__op">× average ₦180,000/month × 12</div>
        <div class="s-math__row s-math__row--total">
          <span><b>SAM — Lagos alone</b></span>
          <span class="s-math__v">₦3.2B/yr</span>
        </div>
      </div>

      <div class="s-math" style="margin-top:18px">
        <div class="s-math__row">
          <span>Abuja, Port Harcourt, Ibadan &amp; Ghana</span>
          <span class="s-math__v">≈ 2.1×</span>
        </div>
        <div class="s-math__row s-math__row--total">
          <span><b>SAM — West Africa</b></span>
          <span class="s-math__v">₦6.7B/yr</span>
        </div>
      </div>

      <span class="s-assume">Firm counts to be verified against LASRERA and REDAN registers</span>
    </div>

    <div class="s-center">
      <div class="s-card">
        <div class="s-card__t">Why this market, now</div>
        <div class="s-card__d">
          <b>Lagos mandated agency registration</b> — for the first time there is a
          list of who our customers are and how to reach them.
        </div>
        <div class="s-card__d">
          <b>Instalment selling is now the norm.</b> That creates receivables, and
          receivables create the exact pain we solve.
        </div>
        <div class="s-card__d">
          <b>Agencies already pay for software</b> — listing portals, CRMs. The
          budget line exists; we are competing for it, not creating it.
        </div>
      </div>
      <div class="s-note" style="margin-top:18px">
        <i></i><span>Capturing <b>3%</b> of Lagos alone is <b>₦96M ARR</b>. This does not need to be a category winner to be a real business.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Market</div>
  <div class="slide__num">08</div>
</section>

<!-- ═══ 09 — Business model ══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Business model</span>
  <h2 class="s-title s-title--sm">Monthly subscription, priced per agency, tiered by team size.</h2>

  <div class="s-body">
    <div class="s-tiers">
      <div class="s-tier">
        <div class="s-tier__n">Starter</div>
        <div class="s-tier__p">₦75,000<span> /mo</span></div>
        <div class="s-tier__w">Up to 5 agents</div>
        <ul class="s-tier__l">
          <li>Full intelligence layer</li>
          <li>Client portal</li>
          <li>CSV import</li>
          <li>Email support</li>
        </ul>
      </div>
      <div class="s-tier s-tier--hero">
        <div class="s-tier__n">Growth · most agencies</div>
        <div class="s-tier__p">₦180,000<span> /mo</span></div>
        <div class="s-tier__w">Up to 20 agents</div>
        <ul class="s-tier__l">
          <li>Everything in Starter</li>
          <li>Role-based views</li>
          <li>Scheduled reports</li>
          <li>WhatsApp alerts</li>
          <li>Onboarding &amp; data migration</li>
        </ul>
      </div>
      <div class="s-tier">
        <div class="s-tier__n">Enterprise</div>
        <div class="s-tier__p">₦450,000<span> /mo</span></div>
        <div class="s-tier__w">Unlimited agents</div>
        <ul class="s-tier__l">
          <li>Everything in Growth</li>
          <li>Multi-branch</li>
          <li>API &amp; integrations</li>
          <li>Dedicated success manager</li>
        </ul>
      </div>
    </div>

    <div class="s-3col" style="margin-top:26px;height:auto">
      <div class="s-card"><div class="s-card__t">Annual prepay −15%</div><div class="s-card__d">Improves cash and cuts churn in a market where monthly cards fail often.</div></div>
      <div class="s-card"><div class="s-card__t">Setup fee ₦250,000</div><div class="s-card__d">Data migration. Covers onboarding cost and filters out tyre-kickers.</div></div>
      <div class="s-card"><div class="s-card__t">Expansion later</div><div class="s-card__d">Per-seat agent app, and a transaction fee on payments collected through the portal.</div></div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Business model</div>
  <div class="slide__num">09</div>
</section>

<!-- ═══ 10 — Unit economics ══════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Unit economics</span>
  <h2 class="s-title s-title--sm">The maths works at small numbers, which is the point.</h2>

  <div class="s-body s-2col">
    <div>
      <div class="s-math">
        <div class="s-math__row"><span>Average contract value (blended)</span><span class="s-math__v">₦180k/mo</span></div>
        <div class="s-math__row"><span>Gross margin <span style="font-size:12.5px;color:var(--text-4)">(hosting + support)</span></span><span class="s-math__v">86%</span></div>
        <div class="s-math__row"><span>Cost to acquire <span style="font-size:12.5px;color:var(--text-4)">(direct sales, 2 demos)</span></span><span class="s-math__v">₦420k</span></div>
        <div class="s-math__row s-math__row--total"><span><b>Payback period</b></span><span class="s-math__v">2.7 months</span></div>
      </div>

      <div class="s-math" style="margin-top:16px">
        <div class="s-math__row"><span>Expected life <span style="font-size:12.5px;color:var(--text-4)">(3% monthly churn)</span></span><span class="s-math__v">33 months</span></div>
        <div class="s-math__row"><span>Lifetime gross profit</span><span class="s-math__v">₦5.1M</span></div>
        <div class="s-math__row s-math__row--total"><span><b>LTV : CAC</b></span><span class="s-math__v">12 : 1</span></div>
      </div>
      <span class="s-assume">Churn and CAC are projections — the first 20 customers exist to prove or break them</span>
    </div>

    <div class="s-center">
      <div class="s-card s-card--gold">
        <div class="s-card__t">Why churn should be low</div>
        <div class="s-card__d">
          <b>It writes to their data.</b> Follow-ups, reassignments and price changes
          made in NexABI are real records. Leaving means losing operating history,
          not just a login.
        </div>
        <div class="s-card__d">
          <b>Their buyers use it too.</b> Once clients check payments in the portal,
          switching means telling every customer to go somewhere else.
        </div>
      </div>
      <div class="s-note" style="margin-top:18px">
        <i></i><span>Break-even at roughly <b>45 customers</b> — 3% of serviceable Lagos.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Unit economics</div>
  <div class="slide__num">10</div>
</section>

<!-- ═══ 11 — Go to market ════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Go to market</span>
  <h2 class="s-title s-title--sm">Land the first 50 by showing them their own leak.</h2>

  <div class="s-body">
    <div class="s-4col" style="height:auto">
      <div class="s-card">
        <div class="s-card__n">STEP 1</div>
        <div class="s-card__t">Free leak audit</div>
        <div class="s-card__d">They export a lead sheet, we import it live in the meeting. NexABI names the cold leads and the money attached. <b>This is the whole pitch.</b></div>
      </div>
      <div class="s-card">
        <div class="s-card__n">STEP 2</div>
        <div class="s-card__t">30-day pilot</div>
        <div class="s-card__d">One branch, real data, paid setup. Success is one number: <b>did the follow-up backlog fall?</b></div>
      </div>
      <div class="s-card">
        <div class="s-card__n">STEP 3</div>
        <div class="s-card__t">Expand inside the firm</div>
        <div class="s-card__d">Branch by branch, then the client portal, which pulls their buyers in and raises the switching cost.</div>
      </div>
      <div class="s-card s-card--gold">
        <div class="s-card__n">STEP 4</div>
        <div class="s-card__t">Referral loop</div>
        <div class="s-card__d">Estate agencies are a small, talkative market. <b>One credible reference sells the next three.</b></div>
      </div>
    </div>

    <div class="s-2col" style="margin-top:26px;height:auto">
      <div class="s-card">
        <div class="s-card__t">Channels that actually reach them</div>
        <div class="s-card__d">
          REDAN and LASRERA member lists · developer launch events · the estate-surveyor
          associations · WhatsApp broker groups. <b>Direct sales, not paid ads</b> —
          this is a market you walk into.
        </div>
      </div>
      <div class="s-card">
        <div class="s-card__t">Why the audit converts</div>
        <div class="s-card__d">
          Most software demos show a product. Ours shows the prospect
          <b>their own money, sitting in their own spreadsheet, uncollected.</b>
          That is a different conversation.
        </div>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Go to market</div>
  <div class="slide__num">11</div>
</section>

<!-- ═══ 12 — Traction ════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Where we are</span>
  <h2 class="s-title s-title--sm">The product is built and working. Not a prototype, not slides.</h2>

  <div class="s-body s-2col">
    <div>
      <ul class="s-list">
        <li><span class="tick">✓</span><span><b>Two full products shipped</b> — the agency console and the buyer portal, 18 screens</span></li>
        <li><span class="tick">✓</span><span><b>The intelligence layer works end to end</b> — it finds problems, explains them, and the fix changes real records</span></li>
        <li><span class="tick">✓</span><span><b>Import your own data and it responds</b> — nothing is staged for the demo</span></li>
        <li><span class="tick">✓</span><span><b>91 automated tests, all passing</b> — this is engineered, not assembled</span></li>
      </ul>

      <div class="s-todo" style="margin-top:26px">
        <div class="s-todo__tag">Fill before presenting</div>
        <div class="s-todo__t">Customer evidence</div>
        <div class="s-todo__d">
          Any letters of intent, pilot agreements, waitlist signups, or named
          agencies who have seen a demo. Even <b>three agencies saying “yes if it
          does X”</b> changes this slide from a claim to a signal.
        </div>
      </div>
    </div>

    <div class="s-center">
      <div class="s-figs" style="border-top:0;grid-template-columns:repeat(2,1fr);gap:26px">
        <div class="s-fig"><div class="n">18</div><div class="k">Screens live across two products</div></div>
        <div class="s-fig"><div class="n">18</div><div class="k">One-click fixes it can perform</div></div>
        <div class="s-fig"><div class="n">91</div><div class="k">Automated tests passing</div></div>
        <div class="s-fig"><div class="n">0</div><div class="k">Fake or hardcoded findings</div></div>
      </div>
      <div class="s-note s-note--gold" style="margin-top:26px">
        <i></i><span>You can open it right now and break it. <b>That is unusual at this stage</b> and it is the strongest thing we have.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Traction</div>
  <div class="slide__num">12</div>
</section>

<!-- ═══ 13 — Roadmap ═════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Roadmap · next 12 months</span>
  <h2 class="s-title s-title--sm">From working product to repeatable business.</h2>

  <div class="s-body">
    <div class="s-time">
      <div class="s-time__col">
        <div class="s-time__q">Q1</div>
        <div class="s-time__t">Make it real</div>
        <div class="s-time__d">Backend, accounts, live data connectors for the CRMs and sheets agencies already use.</div>
        <div class="s-time__m">Milestone · <b>5 paying pilots</b></div>
      </div>
      <div class="s-time__col">
        <div class="s-time__q">Q2</div>
        <div class="s-time__t">Prove retention</div>
        <div class="s-time__d">WhatsApp alerts so findings reach people who never open a browser. Agent mobile view.</div>
        <div class="s-time__m">Milestone · <b>20 customers, churn under 4%</b></div>
      </div>
      <div class="s-time__col">
        <div class="s-time__q">Q3</div>
        <div class="s-time__t">Make selling repeatable</div>
        <div class="s-time__d">Two-person sales team, the leak audit productised, onboarding under a week.</div>
        <div class="s-time__m">Milestone · <b>50 customers · ₦108M ARR</b></div>
      </div>
      <div class="s-time__col">
        <div class="s-time__q">Q4</div>
        <div class="s-time__t">Expand</div>
        <div class="s-time__d">Abuja and Port Harcourt. Payments through the portal opens a transaction revenue line.</div>
        <div class="s-time__m">Milestone · <b>90 customers · break-even</b></div>
      </div>
    </div>

    <div class="s-note" style="margin-top:30px">
      <i></i>
      <span>
        The engine is already built and is portable by design — <b>Q1 is
        infrastructure, not invention.</b> That is why the first milestone is
        customers rather than features.
      </span>
    </div>
  </div>
  <div class="slide__brand"><i></i>Roadmap</div>
  <div class="slide__num">13</div>
</section>

<!-- ═══ 14 — Team ════════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">Team</span>
  <h2 class="s-title s-title--sm">Who is building this.</h2>

  <div class="s-body">
    <div class="s-todo" style="height:100%;display:flex;flex-direction:column;justify-content:center">
      <div class="s-todo__tag">This slide is yours — I cannot write it for you</div>
      <div class="s-todo__t" style="font-size:24px">Founders, and why you specifically</div>
      <div class="s-todo__d" style="max-width:80ch;font-size:15.5px">
        Investors back people before products at this stage. For each founder,
        answer in one line each:
      </div>
      <ul class="s-list" style="margin-top:22px;max-width:80ch">
        <li><span class="tick">1</span><span><b>Name, role, and what you own</b> — product, engineering, sales</span></li>
        <li><span class="tick">2</span><span><b>Why you understand this market</b> — time spent in Nigerian property, or building for SMEs here</span></li>
        <li><span class="tick">3</span><span><b>What you have shipped before</b> — anything real that people used</span></li>
        <li><span class="tick">4</span><span><b>The unfair advantage</b> — a relationship, a dataset, a distribution channel others lack</span></li>
      </ul>
      <div class="s-note" style="margin-top:26px;background:transparent;border-color:var(--sev-high)">
        <i style="background:var(--sev-high)"></i>
        <span>If there are gaps — no sales lead, no property insider — <b>name them and say who you are hiring with the money.</b> That reads as clarity, not weakness.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>Team</div>
  <div class="slide__num">14</div>
</section>

<!-- ═══ 15 — The ask ═════════════════════════════════════════════════ -->
<section class="slide">
  <span class="s-kicker">The ask</span>
  <h2 class="s-title s-title--sm">What we are raising, and what it buys.</h2>

  <div class="s-body s-2col">
    <div>
      <div class="s-todo">
        <div class="s-todo__tag">Set the number</div>
        <div class="s-todo__t" style="font-size:26px">₦____ for ___% </div>
        <div class="s-todo__d">
          A pre-seed for this stage in Lagos typically lands between
          <b>₦40M and ₦120M</b>. Pick the number that funds 18 months to the
          milestone below — <b>not the biggest number you think you can get.</b>
        </div>
      </div>

      <div class="s-note" style="margin-top:20px">
        <i></i>
        <span>
          State the milestone the money reaches: <b>“50 paying customers and
          ₦108M ARR by Q3”</b>. An ask without a milestone attached is a number
          without a reason.
        </span>
      </div>
    </div>

    <div>
      <p class="s-sub" style="margin-top:0;font-size:15.5px">Illustrative allocation — adjust to your number:</p>
      <div class="s-bars" style="margin-top:20px">
        <div>
          <div class="s-bar__top"><b>Engineering — backend &amp; connectors</b><span>40%</span></div>
          <div class="s-bar__track"><div class="s-bar__fill" style="width:40%"></div></div>
        </div>
        <div>
          <div class="s-bar__top"><b>Sales &amp; onboarding — 2 hires</b><span>30%</span></div>
          <div class="s-bar__track"><div class="s-bar__fill" style="width:30%"></div></div>
        </div>
        <div>
          <div class="s-bar__top"><b>Infrastructure &amp; AI costs</b><span>15%</span></div>
          <div class="s-bar__track"><div class="s-bar__fill" style="width:15%"></div></div>
        </div>
        <div>
          <div class="s-bar__top"><b>Marketing &amp; the audit programme</b><span>10%</span></div>
          <div class="s-bar__track"><div class="s-bar__fill" style="width:10%"></div></div>
        </div>
        <div>
          <div class="s-bar__top"><b>Legal, compliance, buffer</b><span>5%</span></div>
          <div class="s-bar__track"><div class="s-bar__fill" style="width:5%"></div></div>
        </div>
      </div>
      <div class="s-note s-note--gold" style="margin-top:24px">
        <i></i><span>Weighted to <b>sales, not building</b>. The product exists — the risk now is distribution, and the money should go where the risk is.</span>
      </div>
    </div>
  </div>
  <div class="slide__brand"><i></i>The ask</div>
  <div class="slide__num">15</div>
</section>

<!-- ═══ 16 — Close ═══════════════════════════════════════════════════ -->
<section class="slide slide--glow">
  <div class="s-center">
    <span class="s-kicker">Why this wins</span>
    <div class="s-quote" style="font-size:44px;max-width:25ch">
      Every agency already owns the data that would save them. Nobody has ever read it for them.
    </div>
    <p class="s-cite" style="max-width:64ch;font-size:16px">
      NexABI is the analyst a 20-person agency can finally afford — one that reads
      everything every morning, says what is wrong in plain English, fixes it, and
      then checks whether the fix worked.
    </p>

    <div class="s-figs" style="margin-top:44px;border-top:1px solid var(--border);grid-template-columns:repeat(3,1fr)">
      <div class="s-fig"><div class="n">₦3.2B</div><div class="k">Serviceable market, Lagos alone</div></div>
      <div class="s-fig"><div class="n">12:1</div><div class="k">Projected LTV to CAC</div></div>
      <div class="s-fig"><div class="n">45</div><div class="k">Customers to break even</div></div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:46px">
      <span style="width:34px;height:34px;border-radius:10px;background:var(--brand-500);display:grid;place-items:center">
        <svg viewBox="0 0 100 100" width="20" height="20"><path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="#171614"/></svg>
      </span>
      <div>
        <div style="font-size:19px;font-weight:650;letter-spacing:-.03em;line-height:1.1">NexABI</div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--text-3);margin-top:3px">Adaptive Business Intelligence</div>
      </div>
    </div>
  </div>
  <div class="slide__num">16</div>
</section>

</body>
</html>

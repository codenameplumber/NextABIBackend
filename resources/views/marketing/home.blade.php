{{--
    Landing page.

    Ported verbatim from the reviewed static build: same markup, same editorial
    stylesheet, same reveal/counter script. Only the links changed — static file
    paths became named routes.
--}}
<x-layouts.marketing
    title="NexABI — Adaptive Business Intelligence for real estate"
    description="NexABI brings scattered real-estate data together, finds what is going wrong, recommends what to do about it, and lets you do it. Data to insight to action, in one loop.">

<div class="site-wrap">

<!-- ══ Header ═══════════════════════════════════════════════════════════ -->
<header class="site-head">
  <a class="brand" href="{{ route('home') }}">
    <span class="brand-mark">
      <svg viewBox="0 0 100 100" width="19" height="19" aria-hidden="true">
        <path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="currentColor"/>
      </svg>
    </span>
    <span class="brand-text">
      <span class="brand-name">NexABI</span>
      <span class="brand-tag">Adaptive Business Intelligence</span>
    </span>
  </a>

  <nav class="site-nav">
    <a href="#loop">The loop</a>
    <a href="#product">Product</a>
    <a href="#portals">Who it's for</a>
    <a href="#proof">How it works</a>
  </nav>

  <div class="spacer"></div>
  <button class="btn btn--icon btn--sm" data-theme-toggle aria-label="Toggle theme"></button>
  <a class="btn btn--ghost btn--sm" href="{{ route('login') }}">Sign in</a>
  <a class="btn btn--primary btn--sm" href="{{ route('register') }}">Get started</a>
</header>

<!-- ══ Hero ═════════════════════════════════════════════════════════════ -->
<section class="hero grain">
  <div class="container">
    <div class="ed-split">
      <div>
        <span class="kicker">Lagos, Nigeria · Real estate</span>

        <h1 class="display mt-16">
          Your data already<br>knows what's wrong.
        </h1>

        <p class="lede mt-20 measure">
          NexABI reads your whole business in one place — sales, leads, properties,
          marketing, payments — and tells you in plain English what needs fixing
          today. Then it gives you the button that fixes it.
        </p>
        <p class="lede mt-12 measure" style="color:var(--text-3)">
          The leads going cold. The campaign burning cash. The flats you priced too
          low. You already have the numbers — this finds the story in them.
        </p>

        <div class="row wrap g-12 mt-24">
          <a class="btn btn--primary btn--lg" href="{{ route('app.dashboard') }}">
            Open the console <span data-icon="arrow" data-size="16"></span>
          </a>
          <a class="btn btn--ghost btn--lg" href="{{ route('portal.overview') }}">Open the client portal</a>
        </div>

        <div class="pill-row mt-24">
          <span class="pill"><span data-icon="check" data-size="13"></span>No sign-up needed</span>
          <span class="pill"><span data-icon="check" data-size="13"></span>Live seeded data</span>
          <span class="pill"><span data-icon="check" data-size="13"></span>Click any number to check it</span>
        </div>
      </div>

      <figure style="margin:0">
        <div class="shot shot--tilt">
          <span class="shot-tag"><span class="pulse"></span>Live console</span>
          <img src="{{ asset('img') }}/shot-dashboard.png"
               alt="The NexABI console showing an AI briefing, four KPI tiles and a ranked insight feed"
               width="1568" height="897" loading="eager">
          <div class="shot-cap">
            <span data-icon="insight" data-size="14" style="color:var(--brand-500)"></span>
            The dashboard, rendered from the live dataset
          </div>
        </div>
        <figcaption class="fig-cap">
          This is the real thing, not a picture of one. Every number is worked out
          when the page opens, from 1,400 leads and 224 properties.
        </figcaption>
      </figure>
    </div>

    <!-- Ruled figures, not a row of boxes -->
    <div class="ed-figures mt-40">
      <div>
        <div class="n"><span data-count="1420">0</span></div>
        <div class="k">Leads under management</div>
      </div>
      <div>
        <div class="n">₦<span data-count="70" data-dp="1">0</span>B</div>
        <div class="k">Open pipeline tracked</div>
      </div>
      <div>
        <div class="n"><span data-count="7">0</span></div>
        <div class="k">Kinds of problem it looks for</div>
      </div>
      <div>
        <div class="n"><span data-count="13">0</span></div>
        <div class="k">Business areas covered</div>
      </div>
    </div>
  </div>
</section>

<!-- ══ The loop ═════════════════════════════════════════════════════════ -->
<section class="section" id="loop">
  <div class="container">
    <span class="kicker" data-reveal>The core idea</span>
    <h2 class="headline mt-16 measure-tight" data-reveal>
      Most tools stop at the chart. The decision is the product.
    </h2>
    <p class="lede mt-16 measure" data-reveal>
      A dashboard tells you revenue fell. It does not tell you why, what to do
      about it, or whether what you did worked. NexABI does all four — and once
      you have fixed something, it takes the warning down by itself.
    </p>

    <div class="loop mt-32" data-reveal>
      <div class="loop-step">
        <div class="loop-step__n">01 / DATA</div>
        <div class="loop-step__t">Bring it together</div>
        <div class="loop-step__d">
          Sales, leads, properties, campaigns, payments and reviews stop living in
          six different spreadsheets.
        </div>
      </div>
      <div class="loop-step">
        <div class="loop-step__n">02 / ANALYSIS</div>
        <div class="loop-step__t">Find what's off</div>
        <div class="loop-step__d">
          It watches every area for things going wrong — money leaking, leads
          stalling, prices drifting — and keeps watching.
        </div>
      </div>
      <div class="loop-step">
        <div class="loop-step__n">03 / INSIGHT</div>
        <div class="loop-step__t">Say it plainly</div>
        <div class="loop-step__d">
          Worst first, written as sentences you could read out in a meeting. Click
          any number to see the records behind it.
        </div>
      </div>
      <div class="loop-step loop-step--live">
        <div class="loop-step__n">04 / ACTION</div>
        <div class="loop-step__t">Actually do it</div>
        <div class="loop-step__d">
          Every suggestion is a button. Press it and the numbers move, the problem
          drops off the list, and the log remembers what changed.
        </div>
      </div>
    </div>

    <div class="callout mt-40" data-reveal>
      <p>If the action doesn't change anything,<br>the finding stays on the screen.</p>
      <cite>The loop is a circuit, not a script — which is the whole point.</cite>
    </div>
  </div>
</section>

<!-- ══ Product ══════════════════════════════════════════════════════════ -->
<section class="section" id="product">
  <div class="container">
    <span class="kicker" data-reveal>The console</span>
    <h2 class="headline mt-16 measure-tight" data-reveal>
      One place for every part of the business.
    </h2>

    <div class="ed-split mt-32" data-reveal>
      <figure style="margin:0">
        <div class="shot">
          <img src="{{ asset('img') }}/shot-insights.png"
               alt="The AI Insights feed: ranked findings, each with a narrative, evidence chips and executable recommendations"
               width="1568" height="897" loading="lazy">
          <div class="shot-cap">
            <span data-icon="insight" data-size="14" style="color:var(--brand-500)"></span>
            AI Insights — ranked, explained, actionable
          </div>
        </div>
        <figcaption class="fig-cap">
          Severity × confidence × role weight × impact. A dramatic finding drawn
          from twelve records never outranks a certain one drawn from four hundred.
        </figcaption>
      </figure>

      <div>
        <h3 class="display--sm">Click any number, see where it came from.</h3>
        <p class="lede mt-16">
          The small tags under each finding open the exact records behind it. If you
          cannot check a number you will not act on it — so every number is
          checkable.
        </p>
        <ul class="portal__list mt-24" style="font-size:var(--t-md)">
          <li><span data-icon="check" data-size="15"></span><span><b style="color:var(--text)">Plain-language narration</b> — the numbers are computed, the sentence is written around them</span></li>
          <li><span data-icon="check" data-size="15"></span><span><b style="color:var(--text)">Confidence shown, always</b> — gated on sample size, never hidden</span></li>
          <li><span data-icon="check" data-size="15"></span><span><b style="color:var(--text)">Executable recommendations</b> — with estimated impact and effort</span></li>
          <li><span data-icon="check" data-size="15"></span><span><b style="color:var(--text)">A before/after log</b> — proof the action changed something real</span></li>
        </ul>
      </div>
    </div>

    <div class="ed-split mt-48" data-reveal>
      <div>
        <h3 class="display--sm">Ask it in your own words.</h3>
        <p class="lede mt-16">
          “Why are we closing fewer deals?” “Which sources send the best leads?”
          “How much are we owed?” You get the figure, a chart, and a way to check
          it — not a wall of text.
        </p>
        <p class="lede mt-16">
          And if it cannot answer, it says so rather than guessing.
        </p>
        <a class="btn btn--ghost mt-24" href="{{ route('app.ask') }}">
          Try Ask NexABI <span data-icon="right" data-size="15"></span>
        </a>
      </div>

      <figure style="margin:0">
        <div class="shot">
          <img src="{{ asset('img') }}/shot-ask.png"
               alt="Ask NexABI answering a question with a headline figure, narrative, chart and contributing signals"
               width="1568" height="897" loading="lazy">
          <div class="shot-cap">
            <span data-icon="ask" data-size="14" style="color:var(--brand-500)"></span>
            Natural language over live metrics
          </div>
        </div>
      </figure>
    </div>

    <!-- Coverage -->
    <div class="feat-grid mt-48" data-reveal>
      <div class="feat">
        <div class="feat__i" data-icon="chart" data-size="20"></div>
        <div class="feat__t">Sales</div>
        <div class="feat__d">Revenue, funnel, win rate and agent performance — including the cohorts quietly dragging the average down.</div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="users" data-size="20"></div>
        <div class="feat__t">Leads</div>
        <div class="feat__d">Scoring, source quality and the follow-up backlog. Finds the qualified leads going cold, not just the count.</div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="home" data-size="20"></div>
        <div class="feat__t">Properties</div>
        <div class="feat__d">Inventory, time on market, and demand per available unit — the number that shows when a segment is underpriced.</div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="spark" data-size="20"></div>
        <div class="feat__t">Marketing</div>
        <div class="feat__d">Spend, cost per lead, acquisition cost and return by campaign and channel.</div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="wallet" data-size="20"></div>
        <div class="feat__t">Finance</div>
        <div class="feat__d">Receivables aging, collection ratio and the accounts that need a relationship call rather than a chase.</div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="chat" data-size="20"></div>
        <div class="feat__t">Feedback</div>
        <div class="feat__d">Sentiment and recurring themes. One complaint is noise; the same one thirty times is an operational defect.</div>
      </div>
    </div>
  </div>
</section>

<!-- ══ Portals ══════════════════════════════════════════════════════════ -->
<section class="section" id="portals">
  <div class="container">
    <span class="kicker" data-reveal>Two ways in</span>
    <h2 class="headline mt-16 measure-tight" data-reveal>
      The business runs the console. The buyer gets their own front door.
    </h2>
    <p class="lede mt-16 measure" data-reveal>
      Your buyers do not need a dashboard. They need to know what they bought, what
      they owe, when it is due, and who to call. So they get their own simple
      page — reading the same records you do.
    </p>

    <div class="portals mt-32" data-reveal>
      <a class="portal" href="{{ route('app.dashboard') }}">
        <div class="portal__icon" data-icon="chart" data-size="20"></div>
        <div class="portal__t">Agency console</div>
        <div class="portal__who">Executives · Sales managers · Finance · Agents</div>
        <div class="portal__d">
          The full intelligence layer across thirteen business areas, with insights
          ranked for whichever role is signed in.
        </div>
        <ul class="portal__list">
          <li><span data-icon="check" data-size="14"></span>Dashboard, insights and Ask NexABI</li>
          <li><span data-icon="check" data-size="14"></span>Leads, properties, marketing, finance</li>
          <li><span data-icon="check" data-size="14"></span>Reports, alerts and the action log</li>
          <li><span data-icon="check" data-size="14"></span>Time machine and tournament mode</li>
        </ul>
        <span class="portal__go">Open the console <span data-icon="right" data-size="15"></span></span>
      </a>

      <a class="portal" href="{{ route('portal.overview') }}">
        <div class="portal__icon" data-icon="home" data-size="20"></div>
        <div class="portal__t">Client portal</div>
        <div class="portal__who">Property buyers · Investors</div>
        <div class="portal__d">
          Everything a buyer actually asks about, without a single chart they did
          not ask for.
        </div>
        <ul class="portal__list">
          <li><span data-icon="check" data-size="14"></span>My property and purchase status</li>
          <li><span data-icon="check" data-size="14"></span>Instalment schedule and receipts</li>
          <li><span data-icon="check" data-size="14"></span>Title, survey and handover documents</li>
          <li><span data-icon="check" data-size="14"></span>Raise an issue, reach my agent</li>
        </ul>
        <span class="portal__go">Open the client portal <span data-icon="right" data-size="15"></span></span>
      </a>
    </div>
  </div>
</section>

<!-- ══ Proof ════════════════════════════════════════════════════════════ -->
<section class="section" id="proof">
  <div class="container">
    <span class="kicker" data-reveal>How it actually works</span>
    <h2 class="headline mt-16 measure-tight" data-reveal>
      Nothing here is written in advance.
    </h2>
    <p class="lede mt-16 measure" data-reveal>
      Every figure and every warning is worked out from your data the moment you
      open the page. Change the data and they change with it. That is the
      difference between a report and a tool.
    </p>

    <div class="feat-grid mt-32" data-reveal>
      <div class="feat">
        <div class="feat__i" data-icon="layers" data-size="20"></div>
        <div class="feat__t">It finds, it doesn't recite</div>
        <div class="feat__d">
          Delete the campaign that is losing money and the warning about it
          disappears by itself. Nothing is pinned to the screen.
        </div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="shield" data-size="20"></div>
        <div class="feat__t">Careful with small numbers</div>
        <div class="feat__d">
          A warning based on twelve records never outranks one based on four
          hundred, and every finding shows how sure it is.
        </div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="clock" data-size="20"></div>
        <div class="feat__t">It keeps watching</div>
        <div class="feat__d">
          Move the clock forward and the business moves too — leads arrive, deals
          close, payments fall due — and new warnings appear on their own.
        </div>
      </div>
      <div class="feat">
        <div class="feat__i" data-icon="download" data-size="20"></div>
        <div class="feat__t">Check it yourself</div>
        <div class="feat__d">
          Drop your own spreadsheet of leads into Settings and watch the totals
          move and new warnings appear. Takes about ten seconds.
        </div>
      </div>
    </div>

    <div class="ed-split mt-48" data-reveal>
      <figure style="margin:0">
        <div class="shot">
          <img src="{{ asset('img') }}/shot-finance.png"
               alt="The Finance module showing receivables aging, collection ratio and accounts at risk"
               width="1568" height="897" loading="lazy">
          <div class="shot-cap">
            <span data-icon="wallet" data-size="14" style="color:var(--brand-500)"></span>
            Finance — receivables aging and accounts at risk
          </div>
        </div>
      </figure>
      <div>
        <h3 class="display--sm">It tells you when something didn't work.</h3>
        <p class="lede mt-16">
          Send payment reminders and some people pay, most don't. NexABI moves the
          figure by what actually came in, not by what you hoped for.
        </p>
        <p class="lede mt-16">
          A tool that always says it worked is a tool you stop believing.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ══ CTA ══════════════════════════════════════════════════════════════ -->
<section class="section section--tight">
  <div class="container">
    <div class="cta-band" data-reveal>
      <span class="kicker" style="justify-content:center">Try it now</span>
      <h2 class="display--sm mt-16">Nothing to install. Nothing to sign up for.</h2>
      <p class="lede mt-16 measure" style="margin-inline:auto">
        The console opens with a full seeded business already in it. Break it,
        advance the clock, import data, reset it. It regenerates identically
        every time.
      </p>
      <div class="row wrap g-12 mt-24" style="justify-content:center">
        <a class="btn btn--primary btn--lg" href="{{ route('app.dashboard') }}">
          Open the console <span data-icon="arrow" data-size="16"></span>
        </a>
        <a class="btn btn--ghost btn--lg" href="{{ route('login') }}">Sign in as a client</a>
      </div>
    </div>
  </div>
</section>

<!-- ══ Footer ═══════════════════════════════════════════════════════════ -->
<footer class="site-foot">
  <div class="container">
    <div class="foot-grid">
      <div class="foot-col">
        <a class="brand" href="{{ route('home') }}">
          <span class="brand-mark">
            <svg viewBox="0 0 100 100" width="19" height="19" aria-hidden="true">
              <path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="currentColor"/>
            </svg>
          </span>
          <span class="brand-text">
            <span class="brand-name">NexABI</span>
            <span class="brand-tag">Adaptive Business Intelligence</span>
          </span>
        </a>
        <p class="tiny dim mt-16 measure-tight" style="line-height:1.6">
          Business data → AI analysis → insight → recommendation → action.
          Built for real-estate operators in Lagos.
        </p>
      </div>

      <div class="foot-col">
        <h4>Product</h4>
        <a href="{{ route('app.dashboard') }}">Agency console</a>
        <a href="{{ route('portal.overview') }}">Client portal</a>
        <a href="{{ route('app.ask') }}">Ask NexABI</a>
        <a href="{{ route('app.insights') }}">AI Insights</a>
      </div>

      <div class="foot-col">
        <h4>Explore</h4>
        <a href="#loop">The loop</a>
        <a href="#product">Coverage</a>
        <a href="#proof">How it works</a>
        <a href="{{ route('app.settings') }}">Import your data</a>
      </div>

      <div class="foot-col">
        <h4>Account</h4>
        <a href="{{ route('login') }}">Sign in</a>
        <a href="{{ route('register') }}">Create an account</a>
      </div>
    </div>

    <div class="foot-bar">
      <span>NexABI — a competition prototype. Data is simulated.</span>
      <span>Fonts: IBM Plex &amp; Fraunces, SIL OFL 1.1</span>
    </div>
  </div>
</footer>

</div>

    <script type="module" src="{{ asset('js/site.js') }}"></script>
</x-layouts.marketing>

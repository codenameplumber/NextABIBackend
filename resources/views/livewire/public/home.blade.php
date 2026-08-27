@php use App\Support\Fmt; @endphp

<div>
    {{-- ────────────────────────────────────────────────── hero carousel ──
         Full-bleed photography with the argument over it. Four claims, in an
         order that reasons rather than lists: it finds what is wrong, it
         fixes it, it answers questions about it, and it runs the business
         underneath all three.

         Every figure on these slides is read off the live database. That is
         the trick of the whole page — the boldest claim on it is also the
         most checkable one.                                              --}}

    <section class="hero" data-carousel data-interval="7000"
             role="region" aria-roledescription="carousel" aria-label="What NexABI does">

        <div class="hero__track" data-carousel-track>
            @foreach ($slides as $i => $s)
                <article class="hslide" data-carousel-slide
                         role="group" aria-roledescription="slide"
                         aria-label="{{ $i + 1 }} of {{ count($slides) }}">

                    {{-- An <img> rather than a CSS background: it can be
                         lazily loaded, it can be sized by the CDN, and the
                         first one is a real LCP element the browser can
                         prioritise instead of discovering it in a stylesheet. --}}
                    <img class="hslide__img" src="{{ $s['image'] }}" alt=""
                         loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                         fetchpriority="{{ $i === 0 ? 'high' : 'auto' }}"
                         decoding="{{ $i === 0 ? 'sync' : 'async' }}">

                    <div class="hslide__veil" aria-hidden="true"></div>

                    <div class="hslide__in">
                        <span class="kicker kicker--dark">{{ $s['kicker'] }}</span>

                        <h1 class="hslide__h">{{ $s['title'] }}</h1>

                        <p class="hslide__p">{{ $s['body'] }}</p>

                        <div class="hslide__cta">
                            <a class="btn btn--primary btn--lg" href="{{ route('login') }}">
                                <x-icon name="insight" size="16" /><span>See it on live data</span>
                            </a>
                            <a class="btn btn--glass btn--lg" href="{{ route('deck') }}">
                                <span>How it works</span><x-icon name="right" size="14" />
                            </a>
                        </div>

                        <div class="hslide__proof">
                            <span class="n">{{ $s['stat'][0] }}</span>
                            <span class="l">{{ $s['stat'][1] }}</span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <button class="hero__arrow is-prev" data-carousel-prev aria-label="Previous">
            <x-icon name="left" size="17" />
        </button>
        <button class="hero__arrow is-next" data-carousel-next aria-label="Next">
            <x-icon name="right" size="17" />
        </button>

        <div class="hero__dots" data-carousel-dots role="tablist" aria-label="Choose a slide">
            @foreach ($slides as $i => $s)
                <button class="hero__dot" data-carousel-dot="{{ $i }}"
                        role="tab" aria-label="{{ $s['kicker'] }}"></button>
            @endforeach
        </div>
    </section>

    {{-- ───────────────────────────────────────────────── the live figures ── --}}

    <section class="figband">
        <div class="figband__in">
            <div class="figband__i">
                <span class="n">{{ Fmt::num($stats['properties']) }}</span>
                <span class="l">Units under management</span>
            </div>
            <div class="figband__i">
                <span class="n">{{ Fmt::num($stats['investors']) }}</span>
                <span class="l">Buyers on the platform</span>
            </div>
            <div class="figband__i">
                <span class="n">{{ Fmt::num($stats['realtors']) }}</span>
                <span class="l">Realtors selling</span>
            </div>
            <div class="figband__i">
                <span class="n">{{ Fmt::moneyShort($stats['collected']) }}</span>
                <span class="l">Instalments collected</span>
            </div>
        </div>
    </section>

    {{-- ────────────────────────────────────────────────────── the loop ──
         A genuine sequence — you cannot act on a finding before it is
         detected — so the numbering carries information.                --}}

    <section class="sec-wrap">
        <header class="sec-lead">
            <span class="eyebrow-b">How it works</span>
            <h2>Four steps, running whether anyone is looking or not.</h2>
            <p>
                Most business intelligence waits to be asked a question. This runs on a schedule,
                finds what changed, and brings it to you ranked for the seat you sit in.
            </p>
        </header>

        <ol class="flow">
            <li class="flow__i">
                <span class="flow__n">01</span>
                <h3>It reads everything</h3>
                <p>
                    Sales, leads, inventory, receivables, marketing, satisfaction. Nine metric
                    groups computed in SQL against your live rows — not sampled, not estimated,
                    not yesterday's export.
                </p>
            </li>
            <li class="flow__i">
                <span class="flow__n">02</span>
                <h3>Fourteen detectors look</h3>
                <p>
                    Stagnation, erosion, concentration, divergence. If the business is healthy
                    they return nothing — and saying nothing is what separates this from a
                    dashboard that always has something on it.
                </p>
            </li>
            <li class="flow__i">
                <span class="flow__n">03</span>
                <h3>Findings are ranked</h3>
                <p>
                    Scored on severity, confidence and money at stake, then re-weighted for your
                    role. A dramatic finding drawn from twelve records never outranks a certain
                    one drawn from four hundred.
                </p>
            </li>
            <li class="flow__i">
                <span class="flow__n">04</span>
                <h3>You act, and it closes</h3>
                <p>
                    Each one carries the single thing to do about it. Do it and the rows change,
                    logged with a diff — then the finding retires itself, because the condition
                    it described has stopped holding.
                </p>
            </li>
        </ol>
    </section>

    {{-- ─────────────────────────────────────────── why a company uses it ── --}}

    <section class="sec-band">
        <div class="sec-wrap">
            <header class="sec-lead">
                <span class="eyebrow-b">Why companies run it</span>
                <h2>Six things that stop going wrong.</h2>
                <p>
                    Written as outcomes rather than features. Nobody buys a detector — they buy
                    knowing which realtor stopped producing, before the quarter closes.
                </p>
            </header>

            <div class="bgrid">
                <article class="bcard">
                    <span class="bcard__ico"><x-icon name="check" size="17" /></span>
                    <h3>Commission always has an owner</h3>
                    <p>
                        No instalment plan opens without a realtor attached to the buyer, so no
                        commission is ever earned with nobody to pay it to. The blocker and its
                        fix sit on the same screen.
                    </p>
                </article>

                <article class="bcard">
                    <span class="bcard__ico"><x-icon name="finance" size="17" /></span>
                    <h3>Receivables that cannot lie</h3>
                    <p>
                        Every instalment's status is worked out from the clock at the moment you
                        read it. Nothing sits in the system calling itself "scheduled" three weeks
                        after it fell due.
                    </p>
                </article>

                <article class="bcard">
                    <span class="bcard__ico"><x-icon name="agent" size="17" /></span>
                    <h3>Rates that cannot be rewritten</h3>
                    <p>
                        The commission rate is frozen onto the sale at approval. Change a
                        property's rate next year and money already earned does not move —
                        because it is a contract, not a setting.
                    </p>
                </article>

                <article class="bcard">
                    <span class="bcard__ico"><x-icon name="marketing" size="17" /></span>
                    <h3>Offline spend finally measurable</h3>
                    <p>
                        Billboards, radio reads and church announcements leave no click. Record
                        what they cost and what you were promised, and they are judged beside
                        everything that tracks itself.
                    </p>
                </article>

                <article class="bcard">
                    <span class="bcard__ico"><x-icon name="insight" size="17" /></span>
                    <h3>Problems that surface themselves</h3>
                    <p>
                        A sweep every fifteen minutes, and a record of how long each condition
                        went unfixed. Nobody has to remember to run anything, and nothing waits
                        for a monthly review.
                    </p>
                </article>

                <article class="bcard">
                    <span class="bcard__ico"><x-icon name="ask" size="17" /></span>
                    <h3>Answers you can check</h3>
                    <p>
                        Every figure opens onto the rows that produced it. The model never sees a
                        raw row — only figures already computed — so it is structurally incapable
                        of inventing one.
                    </p>
                </article>
            </div>
        </div>
    </section>

    {{-- ─────────────────────────────────────────────────── why different ── --}}

    <section class="sec-wrap">
        <header class="sec-lead">
            <span class="eyebrow-b">Built differently</span>
            <h2>Three rules the whole system is arranged around.</h2>
            <p>Each one is a decision most software gets wrong by default.</p>
        </header>

        <div class="wgrid">
            <article class="wcard">
                <span class="wcard__n">01</span>
                <h3>A payment's status is always live</h3>
                <p>
                    Derived from the due date against the clock, every time it is read. Store it
                    instead and an instalment three weeks late keeps reporting itself as on
                    schedule — the kind of quiet wrongness that permanently costs a buyer's trust.
                </p>
            </article>

            <article class="wcard">
                <span class="wcard__n">02</span>
                <h3>A commission rate is always frozen</h3>
                <p>
                    Copied onto the sale the moment it is approved, and read from there for ever
                    after. A test changes a rate after approval and fails if the earned figure
                    moves so much as a naira.
                </p>
            </article>

            <article class="wcard">
                <span class="wcard__n">03</span>
                <h3>A finding retires itself</h3>
                <p>
                    Nothing here is a hardcoded card. When the condition behind a finding stops
                    holding, it closes on its own and the system records how long the fix took.
                    If nothing actually changed, it stays exactly where it was.
                </p>
            </article>
        </div>
    </section>

    {{-- ────────────────────────────────────── the business underneath it ── --}}

    <section class="sec-band">
        <div class="sec-wrap">
            <header class="sec-lead">
                <span class="eyebrow-b">The platform</span>
                <h2>The intelligence needs something to read.</h2>
                <p>
                    So NexABI runs the business it analyses — property sold in instalments, on
                    terms the buyer picks. Three kinds of people sign in, and every commitment,
                    payment and enquiry is a row the engine reads.
                </p>
            </header>

            <div class="fgrid fgrid--3">
                <article class="role-card">
                    <span class="role-card__ico"><x-icon name="customers" size="18" /></span>
                    <h3>Buyers</h3>
                    <p>
                        Commit to a property, choose a term from three months to sixty, and watch
                        every payment land in their own table.
                    </p>
                    <a class="role-card__go" href="{{ route('properties') }}" wire:navigate>
                        <span>Browse the catalogue</span><x-icon name="right" size="13" />
                    </a>
                </article>

                <article class="role-card">
                    <span class="role-card__ico"><x-icon name="agent" size="18" /></span>
                    <h3>Realtors</h3>
                    <p>
                        A referral code and their own registration link, so every buyer is
                        attributed on arrival rather than reconstructed at month end.
                    </p>
                    <a class="role-card__go" href="{{ route('realtor.apply') }}">
                        <span>Apply as a realtor</span><x-icon name="right" size="13" />
                    </a>
                </article>

                <article class="role-card">
                    <span class="role-card__ico"><x-icon name="dashboard" size="18" /></span>
                    <h3>Operators</h3>
                    <p>
                        Approvals, receivables, commission owed against paid, marketing
                        performance — and the findings that come out of running all of it.
                    </p>
                    <a class="role-card__go" href="{{ route('login') }}">
                        <span>Open the console</span><x-icon name="right" size="13" />
                    </a>
                </article>
            </div>
        </div>
    </section>

    {{-- ───────────────────────────────────────────────────── the catalogue ── --}}

    <section class="sec-wrap">
        <div class="sec-h">
            <div>
                <h2>On the market now</h2>
                <p>{{ Fmt::num($stats['properties']) }} available — homes, land and commercial</p>
            </div>
            <a class="btn btn--ghost btn--sm" href="{{ route('properties') }}" wire:navigate>
                <span>See all {{ Fmt::num($stats['properties']) }}</span><x-icon name="right" size="13" />
            </a>
        </div>

        @if ($featured->isNotEmpty())
            <div class="pgrid pgrid--four">
                @foreach ($featured as $p)
                    <a class="pcard" href="{{ route('property', $p->ref) }}" wire:navigate>
                        <div class="pcard__art">
                            <x-property-art :property="$p" />
                        </div>
                        <div class="pcard__b">
                            <h3 class="pcard__t">{{ $p->title }}</h3>
                            <span class="pcard__loc">{{ $p->location }}</span>
                            <span class="pcard__price">{{ Fmt::money($p->price) }}</span>
                            <span class="pcard__per">
                                From <b>{{ Fmt::money($p->instalmentFor(max($p->durationOptions()))) }}</b>
                                a month
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ──────────────────────────────────────────────────────── close ── --}}

    <section class="sec-wrap" style="padding-top:0">
        <div class="cta-band">
            <div>
                <h2>Open it on twenty months of real trading</h2>
                <p>
                    {{ Fmt::num($stats['investors']) }} buyers, {{ Fmt::num($stats['realtors']) }} realtors
                    and {{ Fmt::num($stats['instalments']) }} recorded instalments — with findings
                    already computed from all of it. Nothing is mocked and nothing is hardcoded.
                </p>
            </div>
            <div class="row-2 wrap" style="gap:var(--s-3)">
                <a class="btn btn--primary btn--lg" href="{{ route('login') }}">
                    <span>See it on live data</span>
                </a>
                <a class="btn btn--ghost btn--lg" href="{{ route('deck') }}">
                    <span>Read the deck</span>
                </a>
            </div>
        </div>
    </section>
</div>

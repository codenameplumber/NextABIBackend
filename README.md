# NexABI — Property ownership in instalments

**Browse a property → commit to it → choose your term → pay it off → own it.**
With an intelligence layer watching the whole business and telling the operator
what needs a decision today.

Built for the Nigerian property market.
Laravel 13 · Livewire 4 · MySQL 9 · PHP 8.3 · OpenAI · Mailtrap.

---

## Submission documents

Three published pages, each generated from figures read off the running system:

| | What it covers |
|---|---|
| **[Technical dossier](https://claude.ai/code/artifact/05b89062-f989-412c-b90c-8d087f82b52c)** | The eight submission requirements in order — problem, prototype, workflow, tools and models, sample inputs and outputs, test evidence, the demo path, and business impact |
| **[Three doors in](https://claude.ai/code/artifact/d987ee91-a73c-4aa7-907b-4f4c372324ff)** | What each kind of user can do — visitor, buyer, realtor, operator — and what each deliberately cannot |
| **[Access keys](https://claude.ai/code/artifact/af213ab7-96ee-4568-b740-99d7b732abf3)** | All twelve seeded accounts with one-click copy |

---

## Contents

1. [What it is](#what-it-is)
2. [Running it](#running-it)
3. [Sign-in accounts](#sign-in-accounts)
4. [The four surfaces](#the-four-surfaces)
5. [The journey, end to end](#the-journey-end-to-end)
6. [Attribution — two axes](#attribution--two-axes)
7. [Two rules that must not be broken](#two-rules-that-must-not-be-broken)
8. [The intelligence layer](#the-intelligence-layer)
9. [Architecture](#architecture)
10. [Wiring OpenAI](#wiring-openai)
11. [Wiring Mailtrap](#wiring-mailtrap)
12. [The dataset](#the-dataset)
13. [Testing](#testing)
14. [Production](#production)

---

## What it is

Most people who want property in Lagos are not short of intent — they are short
of a way to pay for it that fits how they actually earn. NexABI is a platform
where a buyer commits to a verified property and pays over a term **they**
choose, with every instalment recorded and visible to them.

Three kinds of people sign in:

| | Where | What they do |
|---|---|---|
| **Investors** | `/portal` | Browse, commit, choose a term, watch the journey fill in |
| **Realtors** | `/realtor` | Bring buyers in with their own link, earn commission |
| **Admins** | `/app` | Approve, record payments, run the business |

The intelligence layer — fourteen detectors over nine metric groups — sits on
the admin side. It finds what is going wrong, recommends what to do, and the
finding retires itself once the condition no longer holds.

---

## Running it

```bash
composer install
cp .env.example .env          # set your MySQL credentials
php artisan key:generate

php artisan migrate:fresh --seed   # ~20 months of trading, about 90 seconds
php artisan nexabi:detect          # first detector sweep

php artisan serve
```

Then `php artisan schedule:work` in a second terminal if you want the scheduled
detection and the morning digest.

---

## Sign-in accounts

**Twelve accounts. The password is `password` on every one of them.**

> A copy-and-paste sheet of all twelve, with what each one opens on, is published at
> **https://claude.ai/code/artifact/af213ab7-96ee-4568-b740-99d7b732abf3**

Every account below is a real user with a hashed password — sessions, roles and
area isolation behave exactly as they would in production. Signing in as one
kind of account and typing another area's URL walks you back to your own front
door rather than showing you an error.

Seeded names are generated, so the person behind an address changes on a fresh
`migrate:fresh --seed`. The address, the role and the situation do not.

### Admin console — `/app`

Six seats across the four role lenses. The lens re-ranks the insight feed for
who is looking; it never filters it, so a finance risk does not stop existing
when a sales manager signs in.

| Email | Password | Seat |
|---|---|---|
| `ada@anchorstone.ng` | `password` | CEO / Managing Director |
| `tunde@anchorstone.ng` | `password` | Sales Manager |
| `ngozi@anchorstone.ng` | `password` | Finance Lead |
| `segun@anchorstone.ng` | `password` | Sales Agent |
| `funke@anchorstone.ng` | `password` | Sales Manager · Island |
| `ibrahim@anchorstone.ng` | `password` | Sales Agent · Mainland |

Start with `ada@anchorstone.ng` — the CEO lens ranks by revenue impact and shows
the widest spread of findings.

### Realtors — `/realtor`

| Email | Password | What it opens on |
|---|---|---|
| `realtor@anchorstone.ng` | `password` | **Approved.** Referral code, registration link, own leads, catalogue with commission per unit, materials, commission statement |
| `pending@anchorstone.ng` | `password` | **The holding screen.** Signed up, waiting on approval — no code, no leads, no catalogue, and every other realtor URL redirects back here |

Both are worth opening. A demo that only shows the approved state never shows
the gate working, and the gate is half of what the realtor model is.

The approved realtor's code is minted at approval and is therefore different on
every fresh seed. Read it off the Realtors screen or the realtor's own
dashboard rather than memorising one.

### Investors — `/portal`

Four accounts, four genuinely different positions. A portal demo that only ever
opens on a healthy account never shows what the product does when something is
wrong.

| Email | Password | Position at seed time |
|---|---|---|
| `client@example.com` | `password` | **Behind.** 2 of 3 instalments paid, one past due |
| `buyer2@example.com` | `password` | **On schedule.** 1 of 6 paid, nothing late |
| `buyer3@example.com` | `password` | **Nearly done.** 3 of 4 paid, one past due |
| `buyer4@example.com` | `password` | **Settled.** Paid in full, documents released |

Balances move as the clock does — instalment status is derived from `due_at`
against the real current time on every read, never from a stored column. An
account that is one day from due today is overdue next week without anybody
writing to the database. Advancing the Time Machine in the console moves these
on too.

---

## The four surfaces

### Public — no account needed

The catalogue is open. A property platform that demands registration before it
will show a price is one people leave — the price is the reason they came, and
withholding it buys an email address at the cost of the visit.

| Route | What it is |
|---|---|
| `/` | What this is, how it works, live stock, one way in |
| `/properties` | The full catalogue, with the monthly figure on every card |
| `/properties/{ref}` | One property, with a live term picker |
| `/enquire?ref=CODE` | The questionnaire. Writes a lead — deliberately not an account |
| `/register?ref=CODE` | Investor registration |
| `/realtor/apply` | Realtor self-signup |

### Investor — `/portal`

**Overview** answers "is anything owed, and when" in a sentence before anything
else loads. **Browse** and **Commit** let them choose a term with the monthly
figure updating as they move it — that number is the whole decision, so it is
never behind a submit button. **Payments** is the progress table. **Documents**
and **Support** complete the file.

### Realtor — `/realtor`

Nothing works until an admin approves them. A pending realtor sees one holding
screen that says what happens next and roughly when.

Once approved: a **dashboard** of their own numbers, a **copyable registration
link**, **their own leads** (from the link, or recorded by hand), the **full
catalogue with their commission shown on each unit**, **promotional materials
already personalised with their details**, and a **commission statement** they
can check line by line.

### Admin — `/app`

| Screen | What it answers |
|---|---|
| **Dashboard** | Total users, realtors, assets sold, what is blocked — then the AI briefing and the health strip |
| **Insights** | Every finding, worst first |
| **Ask NexABI** | Natural language over the metrics layer |
| **Sales** | Revenue, the funnel, realtor close rates against the median |
| **Commitments** | The approval queue, with the blocker and its fix in one place |
| **Leads** | Questionnaire captures — who sent them, which channel |
| **Properties** | Stock, with commission rate and term bounds |
| **Marketing** | What was run, what it was expected to produce, and what it actually did — including billboards, radio and church announcements, which leave no click to track |
| **Investors** | Everyone registered, plus the **unassigned queue** |
| **Finance** | Assets sold, commission paid against owed, recording payments |
| **Realtors** | The approval queue and per-realtor production |
| **Materials** | Upload flyers, social images, brochures and clips; every realtor downloads their own version |
| **Analytics · Reports · Action log · Settings** | Cross-module trends, written summaries, the audit trail |

There is no Feedback screen. Satisfaction is still collected — at journey
milestones — and two of the fourteen detectors still run on it; it is read on
the investor's own file.

---

## The journey, end to end

```
advert or a realtor's questionnaire link
        ↓  basic info + "where did you hear about us"
      LEAD                                    ← admin Leads · realtor's own list
        ↓  registers: state → LGA, income, occupation, source
   INVESTOR                                   ← unassigned queue if no code
        ↓  browses, commits, picks a term
  COMMITMENT (pending)                        ← unit HELD, not sold
        ↓  admin approves
 INSTALMENT PLAN opens                        ← rate FROZEN onto the sale
        ↓  buyer transfers · admin records each one
  PROGRESS TABLE fills                        ← what the investor watches
        ↓  first instalment, and again on completion
   FEEDBACK requested                         ← keeps two detectors fed
        ↓
  COMMISSION owed → paid                      ← admin Finance
```

**A commitment cannot be approved until the investor has a realtor attached.**
That single rule is what stops an instalment plan going live with no commission
owner, and it is why nobody has to be turned away at registration for arriving
without a code.

---

## Attribution — two axes

They answer different questions, and neither substitutes for the other.

| | Answers | Drives |
|---|---|---|
| **Realtor code** | *who* brought them in | Commission, realtor production, sales credit |
| **"Where did you hear about us"** | *which channel* reached them | Marketing performance |

A buyer can hear a radio advert and still be signed up by a realtor. Collapsing
these into one field makes both questions unanswerable.

The second axis is the half that a click-tracked marketing page cannot see at
all: word of mouth, radio, a church announcement. Asked at both the
questionnaire and the registration, they are measured on the investors they
actually produced.

### Recording the spend that leaves no trace

Knowing a buyer heard a radio advert measures the **outcome**. It says nothing
about the **spend**, so the return on it stays uncomputable — and offline spend
is most of what an agency here actually pays for.

**Marketing → Record marketing** closes that. A billboard, a radio read, a
church announcement or an exhibition stand is entered by hand with what it cost
and what it was sold to you as; what it produced is keyed in later, once you
know.

Keeping the expectation is the point. Forty leads is a success or a failure
depending entirely on whether you were promised thirty or four hundred, and an
agency that stores only the outcome can never tell which supplier oversells.

Uncounted and zero stay distinguishable. `NULL` means nobody has counted yet,
which is a chore; `0` is a result, and a finding. Collapsing them would make
every unmeasured billboard look like a failed one.

---

## Two rules that must not be broken

The same lesson, pointed in opposite directions.

**A payment's status is always live.** Derived from `due_at` against the clock
every time it is read. The stored column is a snapshot from whenever the row was
last written; trust it and an instalment three weeks past due still reports
itself as "scheduled".

**A commission rate is always frozen.** Copied onto the commitment at the moment
it is approved, and read from there for ever after. It is a contractual figure —
editing a property's rate next year must never rewrite commission already
earned. `CommitmentTest` changes a rate after approval and asserts the earned
figure does not move.

---

## The intelligence layer

Fourteen detectors across seven archetypes, unchanged by the pivot and pointed
at the new inputs:

| Archetype | Detectors |
|---|---|
| `threshold` | `paymentRisk`, `collectionHealth`, `hotUnworked`, `staleInventory`, `customerRisk` |
| `trend` | `revenueTrend` |
| `anomaly` | `feedbackCluster` |
| `cohort` | `agentDivergence`, `campaignBurn`, `sourceQuality` |
| `stagnation` | `staleLeads` |
| `funnel` | `funnelErosion` |
| `concentration` | `demandGap`, `revenueConcentration` |

Every finding is scored:

```
priority = severity × confidence × role weight × log(impact)
```

Severity measures how far past the baseline the condition sits. Confidence is
gated on sample size, so a dramatic finding drawn from twelve records will not
outrank a certain one drawn from four hundred. Role weight re-ranks for the seat
you are sitting in — it never filters.

**Statistical care, not statistical theatre.** Rates are Laplace-smoothed.
Cohort conversion drops the most recent weeks, because leads captured last week
have not had time to progress. Trends are fitted to a smoothed series. Funnels
count the deepest stage each lead ever *reached*. ROAS is measured on
commission, not on the purchase price — an agency does not bank the value of the
flat.

---

## Architecture

```
L0  Simulation      Support\BusinessSimulator   seeded, deterministic
    Platform        Support\PlatformSeeder      the product around the business
L1  Access          role-based, three areas     investor | realtor | admin
L2  Metrics         Engine\Metrics              9 groups, SQL aggregates, cached
L3  Detectors       Engine\Detectors\*          14 detectors
L4  Insights        Engine\InsightEngine        fingerprint, score, rank, reconcile
L5  Playbooks       Engine\PlaybookRegistry     what to do about each finding
L6  Action bus      Engine\ActionBus            18 handlers, idempotent, logged
    Platform ops    Platform\*                  commitments, realtors, instalments
L7  Views           Livewire + Blade            four surfaces
      ↑
    AI seam         Ai\NarratorContract         the only layer that generates
```

### The schema keeps its names

Renaming `agents` to `realtors` across the metrics layer, fourteen detectors,
the playbooks, the action bus and the test suite is pure risk for no functional
gain. The tables keep the vocabulary the engine already speaks; the product
speaks the new one.

| Table | Called in the product |
|---|---|
| `agents` | Realtor |
| `customers` | Investor |
| `deals` | Commitment |
| `payments` | Instalment |
| `campaigns` | Marketing record |

A naming sweep can happen later as its own change, with tests already green.

### Reference data

`App\Support\Nigeria` holds 36 states plus the FCT and all **774 local
governments** — the full statutory list, verified by a test. A static map rather
than a table: it changes when the National Assembly creates a local government,
which is to say roughly never.

---

## Wiring OpenAI

```dotenv
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

That is the whole change. **Settings → Intelligence layer** shows which provider
is answering.

With no key, `App\Ai\TemplateNarrator` writes the prose deterministically and the
product is fully functional. With a key, `App\Ai\OpenAiNarrator` takes over — and
every call still falls back to the templated narrator on failure.

The model receives `insight.facts` — figures already computed by the metrics
layer — and **never raw rows**. It is therefore structurally incapable of
inventing a number. Narration is cached against a SHA-256 of the facts that
produced it.

---

## Wiring Mailtrap

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_FROM_ADDRESS="hello@nexabi.ng"
```

Then `php artisan nexabi:mailcheck you@example.com`.

### The flows

| Flow | Trigger | Goes to |
|---|---|---|
| Investor welcome | registration | the new investor, naming their realtor |
| Realtor approved | admin approval | the realtor, **carrying their code and link** |
| Password reset | forgot-password form | whoever asked, one-use, 60 minutes |
| Support ticket raised | buyer submits in the portal | that buyer's own realtor, reply-to the buyer |
| Ticket receipt | same submission | the buyer |
| Payment reminder | the `SEND_REMINDERS` action | each late buyer, **one email per account** |
| Findings digest | `nexabi:digest`, daily 07:30 | every admin, ranked for their seat |

Bulk paths (reminders, digest) dispatch to a queue; anything a person is waiting
on (tickets, welcome, approval) sends inline. With `QUEUE_CONNECTION=sync` both
behave identically locally.

---

## The dataset

`php artisan migrate:fresh --seed` simulates roughly 20 months of trading, then
wraps the platform around it. Every figure is generated from a fixed seed, so it
is byte-identical on every run.

| Entity | Rows |
|---|---|
| Realtors | 120 (118 approved, **2 awaiting approval**) |
| Properties | 700 |
| Leads | 4,500 |
| Commitments | ~1,876 |
| Investors | ~351 (**7 deliberately unassigned**) |
| Instalments | ~946 |
| Commission accruals | ~1,876 |
| Pending commitments | **6** |
| Promotional materials | 5 |

The pending items are planted on purpose. An approval queue that is empty on
first login teaches nobody what it is for — and one of the six pending
commitments has no realtor attached, so the blocker and its inline fix are both
visible from the first minute.

### The seven planted conditions

The simulator deliberately writes seven conditions into the data. The detectors
are never told about any of them and rediscover each independently:

`STALE_LEADS` · `CAMPAIGN_BURN` · `DEMAND_GAP` · `PAYMENT_RISK` ·
`FEEDBACK_CLUSTER` · `AGENT_DIVERGENCE` · `FUNNEL_EROSION`

They are expressed as **proportions rather than counts**, so they survive a
change in scale. `ScaleTest` fails if any stops being rediscovered.

---

## Testing

```bash
php artisan test          # 197 tests
php artisan nexabi:audit  # 109 functional checks against the live database
```

Tests run against MySQL — the metrics layer speaks `DATE_FORMAT`, `YEARWEEK` and
`DATEDIFF`. The suite runs the **real seeder** once and rolls each test back, so
the fixture and the shipped dataset cannot drift.

| Suite | What it asserts |
|---|---|
| `CommitmentTest` | Committing holds rather than sells; approval generates the plan and freezes the rate; the schedule sums to the price exactly; a rate change afterwards does not move earned commission; one transfer cannot be recorded twice; reversing the last payment reopens the commitment |
| `AttributionTest` | The code and the channel are recorded separately; direct signups land unassigned; a converted enquiry closes its own lead; commission reaches the right realtor; moving an investor later does not move a sale already credited; all 774 LGAs are real |
| `PlatformSmokeTest` | Every surface renders for the kind of person meant to see it; a pending realtor reaches exactly one screen; each account type is kept to its own area |
| `ClosedLoopTest` | Applying a recommendation moves real rows, is logged with a diff, retires the finding, and cannot be applied twice |
| `PlantedNarrativesTest` | All seven planted conditions are independently rediscovered |
| `ScaleTest` | Every entity carries a real book; nine metric groups cost under 120 queries; a full detector run stays inside a request |
| `IsolationTest` | Staff and buyers cannot enter each other's areas |
| `PortalTest` | Status derived from the clock; "next" is the earliest unpaid instalment; no other customer's name appears |
| `AskEngineTest` | An unanswerable question comes back saying the data is not available, rather than as a confident wrong number |
| `NarrationTest` | Every detector produces prose; a failing model falls through with identical output; the prompt carries facts and never rows |
| `MailTest` | Both sides of a ticket are emailed; a reminder is one email per customer; an approved realtor is emailed their code |
| `CacheCoherenceTest` | The metric cache agrees with the database after an action, a refused action, a rolled-back probe, and a clock jump |
| `TimeMachineTest` | Advancing ages the history and brings new business |
| `ConsoleTest` | Every console view renders; the role switcher re-ranks without filtering |
| `OperatorToolsTest` | Marketing that leaves no click can still be recorded, and an uncounted record never reads as a failed one; the approval queue refuses a commitment with no realtor and clears the block from its own panel; an uploaded photograph lands under `public/` and resolves from any URL on the site; a flyer needs artwork and a message needs words; the image store refuses to delete outside its own folder |

---

## Production

```bash
php artisan optimize
php artisan migrate --force
php artisan schedule:work
```

The metric cache uses the file store deliberately. On the database store every
cache read is itself a query, which turned a 979ms action into a 3.7s one.

**It is not transactional, and that matters.** Handlers call `metrics()` to
measure their own before/after diff, which repopulates the cache from
uncommitted state. Every path that mutates inside a transaction and may roll
back (`ActionBus::settle`, `TimeMachine::advance`, `nexabi:audit`) flushes once
the transaction has resolved, on both branches. `CacheCoherenceTest` fails if one
stops.

### Uploaded files

Property photographs go to `public/img/properties/`, promotional assets to
`public/downloads/materials/`. Both are written under `public/` directly rather
than to the `public` **disk**, which points at `storage/app/public` and is only
reachable over HTTP once `php artisan storage:link` has been run — a step that
is easy to miss, produces no error when missed, and surfaces as every uploaded
photograph 404ing on the one machine where the deploy script forgot it.

`properties.images` holds either kind of value: a relative path from an upload,
or a full URL to photography the agency already hosts. `PropertyPhotos::resolve()`
decides which at render time, so an agency with its stock already shot and on a
CDN never has to re-upload it.

Back both directories up. They are the only application state that is not in
the database.

### Queue workers

```dotenv
QUEUE_CONNECTION=database
```

```bash
php artisan queue:work --queue=mail,default --tries=3 --max-time=3600
```

Add a Scheduler process too, or `nexabi:detect` never fires and findings stop
appearing on their own — which is half the product.

### Scheduled work

| Command | Cadence | What it does |
|---|---|---|
| `nexabi:detect` | every 15 min | Runs all 14 detectors, stamps `resolved_at` on anything that stopped holding |
| `nexabi:digest` | daily 07:30 | Emails each admin the findings ranked for their seat |

# NexABI — Adaptive Business Intelligence

**Business data → AI analysis → insight → recommendation → action → changed data.**
Then the finding retires itself, because the condition it described no longer holds.

Built for real-estate operators in Lagos. Laravel 13 · Livewire 4 · MySQL 9 · PHP 8.3.

---

## The claim, and where to check it

Most BI tools stop at the chart. They tell you revenue fell; they do not tell you
why, what to do about it, or whether what you did worked. NexABI does all four,
and every step of that is a real computation over real rows.

The one line worth remembering: **the intelligence is real; only the prose is
generated.** Analysis, detection, scoring, recommendation and action all run
against your database. Language is the single thing arithmetic cannot do, so it
is the single thing behind the AI seam — one interface, swappable by setting an
environment variable.

If you would rather test that claim than take it on trust:

| Claim | Where to check it |
|---|---|
| Findings are computed, not scripted | **Settings → Planted conditions.** The simulator plants seven conditions on purpose; the detectors are never told about any of them. Delete the failing campaign's rows and its finding disappears on its own. |
| Every figure is drillable | Any insight card. Each evidence chip is one click from the rows that produced it. |
| Actions change data | **Action log.** Every applied recommendation carries a before → after diff, measured by the handler either side of the change. |
| The loop closes | Apply "Follow up N stale leads", then watch the finding retire itself and the nav badge drop. Nothing about that is scripted — if the action changes nothing, the card stays. |
| The portal and the console agree | The buyer flagged in **Finance** is the same buyer, with the same figure, in **the client portal**. One set of rows, two renderings. |

---

## Running it

```bash
composer install
cp .env.example .env          # then set your MySQL credentials
php artisan key:generate

php artisan migrate:fresh --seed   # simulates ~20 months of a Lagos agency
php artisan nexabi:detect          # first detector sweep

php artisan serve
```

Sign in with any of these — password `password`:

| Account | Seat |
|---|---|
| `ada@anchorstone.ng` | CEO / Managing Director |
| `tunde@anchorstone.ng` | Sales Manager |
| `ngozi@anchorstone.ng` | Finance Lead |
| `client@example.com` | Buyer — client portal |

These are real users with hashed passwords. The chips on the login page fill the
form; they do not bypass the guard.

### Turning the language model on

```dotenv
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

That is the whole change. With no key, `App\Ai\TemplateNarrator` writes the prose
deterministically and the product is fully functional — **Settings → Intelligence
layer** shows which provider is answering. With a key, `App\Ai\OpenAiNarrator`
takes over, and every call still falls back to the templated narrator on failure.
An AI outage should degrade the prose, not the product.

The model receives `insight.facts` — figures already computed by the metrics
layer — and never raw rows. It is therefore structurally incapable of inventing a
number. Narration is cached against a hash of the facts that produced it, so a
finding is re-narrated only when its numbers actually move.

### Scheduling

```bash
php artisan schedule:work      # or a cron entry for schedule:run
```

`nexabi:detect` runs every fifteen minutes across every tenant. This is the half
of the loop a browser could never have: findings appear on their own, and
`resolved_at` is stamped when a condition stops holding — which is what makes
"how long did this go unfixed" answerable at all.

Applying an action still re-runs a *targeted* subset synchronously, so a card
retires the moment it is fixed rather than waiting for the next tick.

---

## The seven layers

```
L0  Simulation      Support\BusinessSimulator   seeded, deterministic, biased on purpose
L1  Repository      Eloquent + tenant scope     every query carries the agency
L2  Metrics         Engine\Metrics              9 groups, SQL aggregates, cached
L3  Detectors       Engine\Detectors\*          14 detectors across 7 archetypes
L4  Insights        Engine\InsightEngine        fingerprint, score, rank, reconcile
L5  Playbooks       Engine\PlaybookRegistry     what to do about each finding
L6  Action bus      Engine\ActionBus            18 handlers, idempotent, logged
L7  Views           Livewire + Blade            12 console modules, 4 portal pages
      ↑
    AI seam         Ai\NarratorContract         the only layer that generates
```

### The seven detector archetypes

`threshold` · `trend` · `anomaly` · `cohort` · `stagnation` · `funnel` ·
`concentration` — fourteen concrete detectors between them. Adding a fifteenth
means one class and one line in `InsightEngine::DETECTORS`; nothing else changes.

---

## Decisions worth explaining

**Statistical care, not statistical theatre.** Rates are Laplace-smoothed so a
2-of-3 sample cannot outrank a 340-of-900 one. Cohort conversion drops the most
recent weeks, because leads captured last week have not had time to progress and
including them manufactures a decline in every dataset. Trends are fitted to a
smoothed series. Funnels count the deepest stage each lead ever *reached*, so a
lead lost after a viewing still counts as having viewed. Confidence is gated on
sample size.

**Severity that means something.** Bands sit at 0.82 / 0.55 / 0.28. An earlier
tuning put nine of eleven findings in "critical", which is a product where
everything is urgent and therefore nothing is. There is a test that fails if the
bands collapse again.

**Live status, never a stored snapshot.** A payment's status is derived from
`due_at` against the clock every time it is read. The stored column is a snapshot
from whenever the row was last written; trust it and an instalment three weeks
past due still reports itself as "scheduled" — the kind of quiet wrongness that
permanently costs a customer's confidence in a payments screen.

**Fingerprinted findings.** Identity is `tenant + detector + entity`, hashed.
Without it, a dismissed finding resurrects on the next scheduled run and the
product becomes something people learn to ignore.

**Targeted invalidation.** Every action declares which detectors it can affect.
Re-running all fourteen on every click is wasteful; re-running none breaks the
loop. `ActionBus::INVALIDATES` is the compromise, and the single most important
map to keep honest when adding a handler.

**Colour doctrine.** Gold is reserved for AI and emphasis — it never appears as
chart ink. Severity gets its own ordinal ramp, distinct from the categorical
palette. The chart palette excludes yellow and red for that reason, and is
assigned in fixed order so a seventh series folds into "Other" rather than
inventing a hue. Both themes were validated separately for colour-vision
deficiency; the dark palette is chosen, not auto-lightened.

**Two type registers.** Fraunces for editorial surfaces, IBM Plex Sans for the
product. `--font-display` is opt-in, so nothing in the console accidentally wears
the marketing voice.

**One axis, always.** There are no dual-scale charts anywhere in this codebase. A
second axis lets the author pick the story by picking the scaling. Two measures
of different scale get two charts.

---

## Tests

```bash
php artisan test
```

80 tests / 409 assertions, run against MySQL — the metrics layer speaks
`DATE_FORMAT`, `YEARWEEK` and `DATEDIFF`, so testing it against SQLite would be
testing a different program. The suite seeds one full agency once and rolls each
test back.

What they actually assert:

- **`ClosedLoopTest`** — applying a recommendation moves real rows, is logged
  with a diff, retires the finding, and cannot be applied twice. A dismissal
  survives re-detection.
- **`PlantedNarrativesTest`** — all seven planted conditions are independently
  rediscovered, every finding names a real detector, and the severity bands have
  not collapsed.
- **`IsolationTest`** — a second tenant's rows are invisible, new rows are
  stamped, and staff and buyers cannot enter each other's areas.
- **`PortalTest`** — payment status is derived from the clock, "next" is the
  earliest unpaid instalment, title documents wait on settlement, and no other
  customer's name appears on a buyer's page.
- **`AskEngineTest`** — the parser separates subject from metric, specific
  patterns beat generic ones, and an unanswerable question comes back as "I can't
  answer that" rather than a confident wrong number. Every suggested question is
  checked to actually answer.
- **`NarrationTest`** — every detector produces prose, a missing fact never
  blanks a card, and a failing model call falls through to the templated narrator
  with identical output.
- **`ConsoleTest`** — all 25 console views render, CSV import moves the metrics,
  and the role switcher re-ranks without filtering.

The suite needs a `nexabi_test` database; `phpunit.xml` points at it.

---

## Production

```bash
php artisan optimize          # config, route and view caches
php artisan migrate --force
```

Run behind php-fpm with opcache enabled. On the built-in dev server most of a
request is framework bootstrap; the engine itself answers the common render path
in about 120ms across two queries once the metric cache is warm.

The metric cache uses the file store deliberately. On the database store every
cache read is itself a query, which turned a 979ms action into a 3.7s one.

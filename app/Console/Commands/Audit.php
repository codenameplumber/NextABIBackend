<?php

namespace App\Console\Commands;

use App\Ai\AskEngine;
use App\Ai\NarratorContract;
use App\Engine\ActionBus;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Insight;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Recommendation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * End-to-end functional audit.
 *
 * Not a test suite — a report. Every interaction the product exposes is
 * exercised against the live database and the outcome printed, so "what is
 * broken" is a list of observations rather than an opinion. Runs inside a
 * transaction and rolls back, so it can be run against real data safely.
 */
class Audit extends Command
{
    protected $signature = 'nexabi:audit {--keep : Commit the changes instead of rolling back}';

    protected $description = 'Exercise every interaction and report what works and what does not';

    private array $results = [];

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('No tenant. Run: php artisan migrate:fresh --seed');

            return self::FAILURE;
        }

        app()->instance('nexabi.tenant', $tenant);
        Metrics::for($tenant)->flush();

        DB::beginTransaction();

        try {
            $this->auditEngine($tenant);
            $this->auditActions($tenant);
            $this->auditConsole($tenant);
            $this->auditPortal($tenant);
            $this->auditAsk($tenant);
            $this->auditMail();
            $this->auditParity();
        } finally {
            $this->option('keep') ? DB::commit() : DB::rollBack();

            // The metric cache is a file store and is not transactional, so a
            // rollback leaves it describing a world that was rolled away. An
            // audit that poisons the app's figures for ten minutes is worse
            // than no audit.
            Metrics::for($tenant)->flush();
        }

        $this->report();

        return collect($this->results)->contains(fn ($r) => $r['status'] === 'FAIL')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /* ---------------------------------------------------------- harness */

    private function check(string $area, string $what, callable $fn): void
    {
        try {
            $note = $fn();
            $this->results[] = ['area' => $area, 'what' => $what, 'status' => 'OK', 'note' => (string) $note];
        } catch (\Throwable $e) {
            $this->results[] = [
                'area' => $area, 'what' => $what, 'status' => 'FAIL',
                'note' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 120),
            ];
        }
    }

    private function missing(string $area, string $what, string $note): void
    {
        $this->results[] = ['area' => $area, 'what' => $what, 'status' => 'MISSING', 'note' => $note];
    }

    private function assert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new \RuntimeException($message);
        }
    }

    /* ----------------------------------------------------------- engine */

    private function auditEngine(Tenant $t): void
    {
        $this->check('engine', 'detectors run', function () use ($t) {
            $n = InsightEngine::for($t)->run();
            $this->assert($n > 0, 'no findings produced');

            return "{$n} live findings";
        });

        $this->check('engine', 'all 14 detectors execute', function () use ($t) {
            $failed = [];
            $m = Metrics::for($t);
            foreach (InsightEngine::DETECTORS as $class) {
                try {
                    (new $class)->run($m, $t);
                } catch (\Throwable $e) {
                    $failed[] = (new $class)->key().' ('.mb_substr($e->getMessage(), 0, 40).')';
                }
            }
            $this->assert(! $failed, implode('; ', $failed));

            return '14/14 clean';
        });

        $this->check('engine', 'every metric group computes', function () use ($t) {
            $m = Metrics::for($t);
            foreach (['sales', 'funnel', 'leads', 'properties', 'finance', 'marketing', 'customers', 'feedback', 'agents', 'headline'] as $g) {
                $this->assert(! empty($m->$g()), "{$g}() returned empty");
            }

            return '10/10 groups';
        });

        $this->check('engine', 'every finding narrates', function () use ($t) {
            $n = app(NarratorContract::class);
            $blank = [];
            foreach (InsightEngine::for($t)->live() as $i) {
                if (mb_strlen($n->narrate($i)) < 40) {
                    $blank[] = $i->detector;
                }
            }
            $this->assert(! $blank, 'thin prose from: '.implode(', ', $blank));

            return 'all cards filled';
        });

        $this->check('engine', 'every finding has recommendations', function () use ($t) {
            $bare = collect(InsightEngine::for($t)->live())
                ->filter(fn ($i) => $i->recommendations->isEmpty())->pluck('detector');
            $this->assert($bare->isEmpty(), 'no actions on: '.$bare->implode(', '));

            return 'all actionable';
        });

        $this->check('engine', 'all 7 planted narratives rediscovered', function () use ($t) {
            $want = ['staleLeads', 'campaignBurn', 'demandGap', 'paymentRisk', 'feedbackCluster', 'agentDivergence', 'funnelErosion'];
            $have = collect(InsightEngine::for($t)->live())->pluck('detector')->unique();
            $missing = array_diff($want, $have->all());
            $this->assert(! $missing, 'not found: '.implode(', ', $missing));

            return '7/7';
        });
    }

    /* ---------------------------------------------------------- actions */

    private function auditActions(Tenant $t): void
    {
        $bus = new ActionBus($t);

        // Every handler, driven with the payload its playbook actually sends.
        $cases = [
            'SCHEDULE_FOLLOWUP' => ['filter' => 'stale', 'note' => 'audit'],
            'RESCORE_LEADS' => ['filter' => 'stale'],
            'MATCH_LEADS' => [],
            'REBALANCE_LEADS' => [],
            'SEND_REMINDERS' => ['filter' => 'overdue'],
            'ESCALATE_ACCOUNTS' => ['customerIds' => Customer::limit(3)->pluck('id')->all()],
            'RESTRUCTURE_TERMS' => ['customerIds' => Customer::limit(2)->pluck('id')->all()],
            'OUTREACH' => ['customerIds' => Customer::limit(3)->pluck('id')->all()],
            'PAUSE_CAMPAIGN' => ['id' => Campaign::where('status', 'active')->value('id')],
            'REALLOCATE_BUDGET' => ['fromId' => Campaign::where('status', 'active')->value('id')],
            'ADJUST_PRICE' => [],
            'FLAG_ACQUISITION' => [],
            'FLAG_PROMOTION' => [],
            'ASSIGN_COACHING' => [],
            'SET_POLICY' => ['key' => 'audit.probe', 'value' => 1],
            'ACKNOWLEDGE' => [],
            'NAVIGATE' => [],
        ];

        foreach ($cases as $type => $payload) {
            $this->check('action', $type, function () use ($bus, $type, $payload) {
                $r = $bus->manual($type, $payload, 'Audit '.$type, 'audit:'.$type.':'.uniqid());
                // "Nothing to do" is a legitimate outcome; a crash is not.
                return $r['ok'] ? $r['message'] : 'no-op: '.$r['message'];
            });
        }

        $this->check('action', 'idempotency guard', function () use ($bus) {
            // SET_POLICY, because it always has something to do — the earlier
            // sweeps in this audit have already cleared the stale backlog, so
            // a follow-up action would no-op and prove nothing either way.
            $key = 'audit:idem:'.uniqid();
            $payload = ['key' => 'audit.idem.'.uniqid(), 'value' => 1];

            $a = $bus->manual('SET_POLICY', $payload, 'Audit', $key);
            $b = $bus->manual('SET_POLICY', $payload, 'Audit', $key);

            $this->assert($a['ok'], 'first application did not take: '.$a['message']);
            $this->assert(! $b['ok'], 'the same action applied twice');

            return 'second click refused';
        });

        $this->check('action', 'applying a real recommendation', function () use ($t) {
            InsightEngine::for($t)->run();
            $rec = Recommendation::whereNull('done_at')
                ->whereNotIn('action_type', ['NAVIGATE', 'ACKNOWLEDGE'])->first();
            $this->assert((bool) $rec, 'no pending recommendation exists');
            $r = (new ActionBus($t))->execute($rec);

            return $r['ok']
                ? $rec->action_type.' → '.($r['before'] ?? '?').' → '.($r['after'] ?? '?')
                : 'refused: '.$r['message'];
        });
    }

    /* ---------------------------------------------------------- console */

    private function auditConsole(Tenant $t): void
    {
        $staff = User::where('is_client', false)->first();
        $this->assert((bool) $staff, 'no staff user');

        $components = [
            'Dashboard' => \App\Livewire\Pages\Dashboard::class,
            'Insights' => \App\Livewire\Pages\Insights::class,
            'Ask' => \App\Livewire\Pages\Ask::class,
            'Sales' => \App\Livewire\Pages\Sales::class,
            'Leads' => \App\Livewire\Pages\Leads::class,
            'Properties' => \App\Livewire\Pages\Properties::class,
            'Marketing' => \App\Livewire\Pages\Marketing::class,
            'Customers' => \App\Livewire\Pages\Customers::class,
            'Finance' => \App\Livewire\Pages\Finance::class,
            'Feedback' => \App\Livewire\Pages\Feedback::class,
            'Analytics' => \App\Livewire\Pages\Analytics::class,
            'Reports' => \App\Livewire\Pages\Reports::class,
            'Activity' => \App\Livewire\Pages\Activity::class,
            'Settings' => \App\Livewire\Pages\Settings::class,
            'Nav' => \App\Livewire\Nav::class,
            'Palette' => \App\Livewire\Palette::class,
        ];

        foreach ($components as $label => $class) {
            $this->check('console', $label.' mounts', function () use ($staff, $class) {
                Livewire::actingAs($staff)->test($class)->assertOk();

                return 'renders';
            });
        }

        $this->check('console', 'leads: filter + sort + paginate', function () use ($staff) {
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Leads::class)
                ->set('filter', 'stale')->assertOk()
                ->call('sortBy', 'budget')->assertOk()
                ->set('q', 'a')->assertOk()
                ->call('open', Lead::value('id'))->assertOk()
                ->call('closeDrawer')->assertOk();

            return 'filter, sort, search, drawer';
        });

        $this->check('console', 'leads: sweep stale', function () use ($staff) {
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Leads::class)
                ->call('sweepStale')->assertOk();

            return 'sweep runs';
        });

        $this->check('console', 'finance: chase / remind / escalate', function () use ($staff) {
            $c = Customer::first();
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Finance::class)
                ->call('chaseAll')->assertOk()
                ->call('remindCustomer', $c->id, $c->name)->assertOk()
                ->call('escalateHighValue')->assertOk();

            return 'all three run';
        });

        $this->check('console', 'marketing: pause campaign', function () use ($staff) {
            $c = Campaign::where('status', 'active')->first();
            $this->assert((bool) $c, 'no active campaign');
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Marketing::class)
                ->call('pause', $c->id, $c->name)->assertOk();

            return 'paused';
        });

        $this->check('console', 'insights: re-run + filters', function () use ($staff) {
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Insights::class)
                ->call('rerun')->assertOk()
                ->call('setBand', 'critical')->assertOk()
                ->call('setArea', 'finance')->assertOk()
                ->call('clearFilters')->assertOk();

            return 'rerun + band + area';
        });

        $this->check('console', 'insight card: apply + dismiss', function () use ($staff, $t) {
            InsightEngine::for($t)->run();
            $i = InsightEngine::for($t)->live()[0] ?? null;
            $this->assert((bool) $i, 'no live finding');
            Livewire::actingAs($staff)->test(\App\Livewire\InsightCard::class, ['insight' => $i])
                ->call('dismiss')->assertOk();

            return 'dismiss works';
        });

        $this->check('console', 'settings: generate + import', function () use ($staff) {
            $csv = "name,email,source,status,score,budget,location,type\n"
                ."Audit One,audit1@example.com,Referral,qualified,70,100000000,Lekki,Apartment";
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Settings::class)
                ->call('generate', 5)->assertOk()
                ->set('csv', \Illuminate\Http\UploadedFile::fake()->createWithContent('a.csv', $csv))
                ->call('import')->assertHasNoErrors();

            return 'generate + CSV import';
        });

        $this->check('console', 'downloads (template, export, receipts)', function () use ($staff) {
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Settings::class)->call('template');
            Livewire::actingAs($staff)->test(\App\Livewire\Pages\Activity::class)->call('export');

            return 'CSV template + JSON export stream';
        });

        $this->check('console', 'palette search', function () use ($staff) {
            Livewire::actingAs($staff)->test(\App\Livewire\Palette::class)
                ->call('openPalette')
                ->set('q', 'a')->assertOk()
                ->set('q', 'why is conversion falling?')->assertOk();

            return 'records + question routing';
        });

        $this->check('console', 'role switcher persists', function () use ($staff) {
            $this->assert(
                array_key_exists('finance', InsightEngine::ROLES),
                'finance role missing',
            );
            $a = collect(InsightEngine::for(app('nexabi.tenant'))->feed('ceo', 20))->pluck('id')->all();
            $b = collect(InsightEngine::for(app('nexabi.tenant'))->feed('finance', 20))->pluck('id')->all();
            $this->assert($a !== $b, 'role weights change nothing');

            return 'ceo vs finance order differs';
        });
    }

    /* ----------------------------------------------------------- portal */

    private function auditPortal(Tenant $t): void
    {
        $client = User::where('is_client', true)->first();

        if (! $client) {
            $this->missing('portal', 'client account', 'no is_client user seeded');

            return;
        }

        foreach ([
            'Overview' => \App\Livewire\Portal\Overview::class,
            'Payments' => \App\Livewire\Portal\Payments::class,
            'Documents' => \App\Livewire\Portal\Documents::class,
            'Support' => \App\Livewire\Portal\Support::class,
        ] as $label => $class) {
            $this->check('portal', $label.' mounts', function () use ($client, $class) {
                Livewire::actingAs($client)->test($class)->assertOk();

                return 'renders';
            });
        }

        $this->check('portal', 'receipt download', function () use ($client) {
            $paid = Payment::where('customer_id', $client->customer_id)
                ->whereNotNull('paid_at')->first();
            $this->assert((bool) $paid, 'this buyer has no paid instalment');
            Livewire::actingAs($client)->test(\App\Livewire\Portal\Payments::class)
                ->call('receipt', $paid->id);

            return 'streams a receipt';
        });

        $this->check('portal', 'document download', function () use ($client) {
            Livewire::actingAs($client)->test(\App\Livewire\Portal\Documents::class)
                ->call('download', 'contract');

            return 'streams a document';
        });

        $this->check('portal', 'support ticket saves', function () use ($client) {
            Livewire::actingAs($client)->test(\App\Livewire\Portal\Support::class)
                ->set('topic', 'payment-plan')
                ->set('body', 'Audit probe — please ignore this request entirely.')
                ->call('send')->assertHasNoErrors();

            return 'row written';
        });

        $this->check('portal', 'live payment status', function () use ($client) {
            $p = Payment::where('customer_id', $client->customer_id)->first();
            $p->forceFill(['status' => 'scheduled', 'paid_at' => null, 'due_at' => now()->subDays(60)])->save();
            $this->assert($p->fresh()->liveStatus() === 'overdue', 'stored status won over the clock');

            return 'derived from the clock';
        });
    }

    /* -------------------------------------------------------------- ask */

    private function auditAsk(Tenant $t): void
    {
        $engine = new AskEngine($t, Metrics::for($t), InsightEngine::for($t));

        foreach ($engine->suggestions() as $q) {
            $this->check('ask', mb_substr($q, 0, 40), function () use ($engine, $q) {
                $a = $engine->ask($q);
                $this->assert($a['kind'] !== 'unknown', 'suggested but unanswerable');

                return $a['parsed']['intent'].' → '.mb_substr((string) $a['headline'], 0, 34);
            });
        }

        $this->check('ask', 'refuses out-of-domain', function () use ($engine) {
            $a = $engine->ask('what is the weather in Lagos');
            $this->assert($a['kind'] === 'unknown', 'it guessed instead of refusing');

            return 'says it cannot answer';
        });
    }

    /* ------------------------------------------------------------- mail */

    private function auditMail(): void
    {
        $mailables = glob(app_path('Mail/*.php')) ?: [];
        $notifications = glob(app_path('Notifications/*.php')) ?: [];

        if (! $mailables && ! $notifications) {
            $this->missing('email', 'any Mailable or Notification', 'none exist in app/Mail or app/Notifications');
        }

        // Configured is not the same as working. SMTP with no credentials is
        // the shape a Mailtrap setup takes ten seconds before it is finished,
        // and it fails on the first send rather than at boot.
        $mailer = config('mail.default');
        $username = config('mail.mailers.smtp.username');
        $host = config('mail.mailers.smtp.host');

        $this->results[] = match (true) {
            $mailer === 'log' => [
                'area' => 'email', 'what' => 'transport', 'status' => 'MISSING',
                'note' => 'MAIL_MAILER=log — written to storage/logs, not delivered',
            ],
            $mailer === 'smtp' && blank($username) => [
                'area' => 'email', 'what' => 'transport', 'status' => 'MISSING',
                'note' => $host.' set, but MAIL_USERNAME is blank — paste your Mailtrap credentials',
            ],
            default => [
                'area' => 'email', 'what' => 'transport', 'status' => 'OK',
                'note' => $mailer.' via '.$host,
            ],
        };

        // Every flow, actually rendered. A Mailable that exists and throws on
        // render is worse than one that does not exist.
        $tenant = app('nexabi.tenant');
        $staff = User::where('is_client', false)->first();

        $ticket = \App\Models\SupportTicket::with(['customer', 'agent'])->first()
            ?? \App\Models\SupportTicket::create([
                'tenant_id' => $tenant->id,
                'customer_id' => Customer::value('id'),
                'agent_id' => \App\Models\Agent::value('id'),
                'reference' => 'TCK-AUDIT', 'topic' => 'Question about a payment',
                'body' => 'Audit probe.', 'status' => 'open',
            ])->load(['customer', 'agent']);

        $engine = InsightEngine::for($tenant);
        $metrics = Metrics::for($tenant);
        $feed = $engine->feed('ceo', 8);

        $flows = [
            'welcome on registration' => fn () => new \App\Mail\AgencyWelcome($staff),
            'support ticket to the agent' => fn () => new \App\Mail\SupportTicketRaised($ticket),
            'ticket receipt to the buyer' => fn () => new \App\Mail\SupportTicketReceived($ticket),
            'payment reminder to the buyer' => fn () => new \App\Mail\PaymentReminder(
                Customer::first(),
                \App\Models\Payment::whereNull('paid_at')->limit(2)->get(),
            ),
            'daily digest of findings' => fn () => new \App\Mail\FindingsDigest(
                $staff, $tenant, $feed, $metrics->headline(),
                app(NarratorContract::class)->brief($metrics->headline(), $feed),
                $engine->impactOnTable($feed),
            ),
        ];

        foreach ($flows as $name => $make) {
            $this->check('email', $name, function () use ($make) {
                $html = $make()->render();
                $this->assert(mb_strlen($html) > 500, 'rendered to almost nothing');
                $this->assert(str_contains($html, 'NexABI'), 'unbranded');

                return mb_strlen($html).' bytes';
            });
        }

        $this->check('email', 'password reset link', function () use ($staff) {
            $mail = (new \App\Notifications\ResetPasswordLink('audit-token'))->toMail($staff);
            $html = view($mail->view, $mail->viewData)->render();
            $this->assert(str_contains($html, 'reset-password/audit-token'), 'no working link');

            return 'branded, token embedded';
        });

        $this->check('email', 'reminders actually leave the action', function () use ($tenant) {
            $fake = \Illuminate\Support\Facades\Mail::fake();

            $result = (new \App\Engine\ActionBus($tenant))->manual(
                'SEND_REMINDERS', ['filter' => 'overdue'],
                'Audit reminders', 'audit:mail:'.uniqid(),
            );

            // Counted off the fake rather than asserted. PHPUnit's assertions
            // need a Configuration singleton that only exists inside a test
            // run, so a failing assertion here dies with an unrelated error
            // instead of reporting what went wrong.
            $queued = $fake->queued(\App\Mail\PaymentReminder::class)->count();
            $sent = $fake->sent(\App\Mail\PaymentReminder::class)->count();
            $total = $queued + $sent;

            $this->assert($result['ok'], 'the action itself refused: '.$result['message']);
            $this->assert($total > 0, 'the action reported success and sent nothing');

            return $total.' reminder '.($total === 1 ? 'email' : 'emails')
                .' ('.$queued.' queued, '.$sent.' inline)';
        });
    }

    /* ----------------------------------------------- design parity ---- */

    /**
     * Design parity with the reviewed prototype.
     *
     * Checked rather than asserted: each entry names a file and a marker, so
     * this fails if a feature is removed again rather than quietly drifting.
     */
    private function auditParity(): void
    {
        $checks = [
            ['ai', 'OpenAI narrator wired', 'app/Ai/OpenAiNarrator.php', 'api.openai.com'],
            ['ai', 'key read from OPENAI_API_KEY', 'config/services.php', 'OPENAI_API_KEY'],
            ['ai', 'falls back to the templated narrator', 'app/Ai/OpenAiNarrator.php', '$fallback()'],
            ['console shell', 'Time Machine (+7d / +30d)', 'app/Livewire/TimeMachine.php', 'advance'],
            ['console shell', 'Tournament Mode walkthrough', 'public/js/tournament.js', 'Tournament Mode'],
            ['console shell', 'palette arrow-key navigation', 'resources/views/livewire/palette.blade.php', 'ArrowDown'],
            ['console shell', 'palette action commands', 'app/Livewire/Palette.php', "'Actions'"],
            /*
             | This used to assert that the login page carried one-click demo
             | chips. It no longer does — a sign-in screen handing out six
             | working logins reads as a demo rather than as a product, so they
             | were removed and the accounts documented in the README instead.
             |
             | The check follows the information rather than being deleted. Its
             | point was never the chips; it was that a reviewer can get in
             | without reading source, and that is still worth failing over.
             */
            ['auth', 'demo accounts documented for reviewers', 'README.md', 'ada@anchorstone.ng'],
            /*
             | The seat selector was removed from the sign-in form — a lens is
             | a preference, not a credential. The role switcher inside the
             | console is where it lives now, and that is what this checks: the
             | capability still has to exist, just not on the door.
             */
            ['auth', 'role switcher lives in the console', 'app/Http/Controllers/AuthController.php', 'switchRole'],
            ['auth', 'register: state and local government', 'resources/views/auth/register.blade.php', 'lgaPicker'],
            ['auth', 'register: where did you hear about us', 'resources/views/auth/register.blade.php', 'heard_about_us'],
            ['auth', 'register: realtor referral code', 'resources/views/auth/register.blade.php', 'name="ref"'],
            ['auth', 'realtor self-signup', 'resources/views/auth/realtor-apply.blade.php', 'realtor.apply.attempt'],

            ['platform', 'referral codes are issued at approval', 'app/Platform/Realtors.php', 'generateCode'],
            ['platform', 'approval requires an assigned realtor', 'app/Models/Deal.php', 'approvalBlocker'],
            ['platform', 'commission rate frozen at approval', 'app/Platform/Commitments.php', 'commission_rate'],
            ['platform', 'commission capped at 15%', 'app/Models/Property.php', 'MAX_COMMISSION_RATE'],
            ['platform', 'investor chooses the term', 'app/Models/Property.php', 'durationOptions'],
            ['platform', 'instalments recorded by an admin', 'app/Platform/Instalments.php', 'recorded_by'],
            ['platform', 'milestone feedback triggers', 'app/Platform/Instalments.php', 'feedbackDue'],
            ['platform', 'materials personalised per realtor', 'app/Models/PromotionalMaterial.php', 'personalise'],
            ['platform', '774 local governments', 'app/Support/Nigeria.php', 'lgaCount'],
            ['auth', 'register: password strength meter', 'public/js/password-strength.js', 'Strong enough'],
            ['auth', 'forgot / reset password pages', 'resources/views/auth/reset-password.blade.php', 'Choose a new password'],
            ['portal', 'documents: "Download all"', 'app/Livewire/Portal/Documents.php', 'downloadAll'],
            ['marketing', 'investor deck page', 'resources/views/deck/pitch.blade.php', '<'],
            ['marketing', 'technical appendix page', 'resources/views/deck/technical.blade.php', '<'],
        ];

        foreach ($checks as [$area, $what, $file, $marker]) {
            $this->check($area, $what, function () use ($file, $marker) {
                $path = base_path($file);
                $this->assert(is_file($path), "{$file} does not exist");
                $this->assert(
                    str_contains(file_get_contents($path), $marker),
                    "{$file} no longer contains \"{$marker}\"",
                );

                return basename($file);
            });
        }
    }

    /* ----------------------------------------------------------- report */

    private function report(): void
    {
        $this->newLine();

        foreach (collect($this->results)->groupBy('area') as $area => $rows) {
            $ok = $rows->where('status', 'OK')->count();
            $fail = $rows->where('status', 'FAIL')->count();
            $miss = $rows->where('status', 'MISSING')->count();

            $this->newLine();
            $this->line(sprintf(
                '<options=bold>%s</> — %d ok, %d failing, %d missing',
                mb_strtoupper($area), $ok, $fail, $miss,
            ));

            foreach ($rows as $r) {
                $tag = match ($r['status']) {
                    'OK' => '<fg=green>  ok  </>',
                    'FAIL' => '<fg=red;options=bold> FAIL </>',
                    default => '<fg=yellow>  --  </>',
                };
                $this->line(sprintf('%s %-46s %s', $tag, mb_substr($r['what'], 0, 46), $r['note']));
            }
        }

        $all = collect($this->results);
        $this->newLine();
        $this->line(sprintf(
            '<options=bold>TOTAL</> %d checks · <fg=green>%d ok</> · <fg=red>%d failing</> · <fg=yellow>%d missing</>',
            $all->count(),
            $all->where('status', 'OK')->count(),
            $all->where('status', 'FAIL')->count(),
            $all->where('status', 'MISSING')->count(),
        ));
    }
}

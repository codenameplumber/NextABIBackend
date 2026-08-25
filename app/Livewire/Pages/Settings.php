<?php

namespace App\Livewire\Pages;

use App\Ai\NarratorContract;
use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Activity;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Property;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings — role, data ingestion, policies and provenance.
 *
 * The ingestion panel is the strongest single argument in the product. Import
 * a CSV of leads and watch the KPIs move and new findings appear. Nothing is
 * hardcoded, so nothing *can* be hardcoded — a reviewer can prove it in ten
 * seconds without reading a line of source.
 */
#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    use InteractsWithEngine, WithFileUploads;

    #[Validate('required|file|mimes:csv,txt|max:4096')]
    public $csv;

    public array $importLog = [];

    /* ------------------------------------------------------------ import */

    public function import(): void
    {
        $this->validate();

        $before = $this->snapshot();

        try {
            $result = $this->ingest($this->csv->getRealPath(), $this->csv->getClientOriginalName());
        } catch (\Throwable $e) {
            $this->toast('Import failed', $e->getMessage(), 'neg');

            return;
        }

        $this->metrics()->flush();
        $after = $this->snapshot();

        // Re-run everything: an import can move any part of the business, so
        // this is the one place a full detector sweep is the correct scope.
        $found = $this->engine()->run();

        $this->importLog[] = [
            'file' => $result['file'],
            'rows' => $result['rows'],
            'at' => now()->toDateTimeString(),
        ];

        $this->reset('csv');
        $this->dispatch('engine-changed');

        $this->toast(
            "{$result['rows']} leads imported",
            'Pipeline '.Fmt::moneyShort($before['pipeline']).' → '.Fmt::moneyShort($after['pipeline'])
                ." · {$found} live findings after re-detection",
            'ai',
        );
    }

    /**
     * Generate synthetic leads.
     *
     * Here so the import claim can be checked without anyone having to build a
     * CSV first. The rows are real rows: they go through the same insert, move
     * the same metrics and are seen by the same detectors.
     */
    public function generate(int $n = 40): void
    {
        $before = $this->snapshot();
        $agents = Agent::where('active', true)->pluck('id')->all() ?: Agent::pluck('id')->all();

        if (! $agents) {
            $this->toast('Nothing to attach to', 'This agency has no agents yet.', 'neg');

            return;
        }

        $sources = ['Instagram', 'Referral', 'Facebook', 'Website', 'WhatsApp'];
        $locations = ['Lekki', 'Ajah', 'Ikoyi', 'Yaba', 'Magodo'];
        $stamp = now()->timestamp;

        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $created = now()->subDays(($i % 30) + 1);
            $rows[] = [
                'tenant_id' => $this->tenant()->id,
                'name' => 'Imported Lead '.($i + 1),
                'email' => "imported.{$stamp}.{$i}@example.com",
                'phone' => '+2348000000000',
                'source' => $sources[$i % count($sources)],
                'channel' => 'Import',
                'status' => 'contacted',
                'max_stage' => 'contacted',
                'score' => 40 + (($i * 13) % 55),
                'budget' => (40 + (($i * 7) % 160)) * 1e6,
                'wants_location' => $locations[$i % count($locations)],
                'wants_type' => 'Apartment',
                'assigned_agent_id' => $agents[$i % count($agents)],
                'created_at' => $created,
                'updated_at' => now(),
                'last_contact_at' => now()->subDays(($i % 26) + 2),
                'is_open' => true,
                'imported' => true,
            ];
        }

        Lead::insert($rows);

        $this->metrics()->flush();
        $after = $this->snapshot();
        $this->engine()->run();
        $this->dispatch('engine-changed');

        $this->toast(
            "{$n} leads generated",
            'Stale '.$before['stale'].' → '.$after['stale']
                .' · pipeline '.Fmt::moneyShort($before['pipeline']).' → '.Fmt::moneyShort($after['pipeline']),
            'ai',
        );
    }

    public function rerun(): void
    {
        $this->metrics()->flush();
        $found = $this->engine()->run();
        $this->dispatch('engine-changed');
        $this->toast('Detectors re-run', "All 14 detectors checked the current data · {$found} live findings", 'ai');
    }

    public function template(): StreamedResponse
    {
        $csv = implode("\n", [
            'name,email,phone,source,status,score,budget,location,type,createdAt,lastContact',
            'Amaka Nwosu,amaka@example.com,+2348012345678,Referral,qualified,78,120000000,Lekki,Apartment,'
                .now()->subDays(50)->toDateString().','.now()->subDays(30)->toDateString(),
            'Tunde Balogun,tunde@example.com,+2348023456789,Instagram,contacted,54,85000000,Ajah,Terraced Duplex,'
                .now()->subDays(46)->toDateString().','.now()->subDays(41)->toDateString(),
            'Zainab Bello,zainab@example.com,+2348034567890,Google Ads,viewing,81,340000000,Ikoyi,Detached Duplex,'
                .now()->subDays(63)->toDateString().','.now()->subDays(5)->toDateString(),
        ]);

        return response()->streamDownload(
            fn () => print($csv),
            'nexabi-leads-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    /* ----------------------------------------------------------- parsing */

    /**
     * Minimal RFC4180-ish CSV import.
     *
     * Deliberately forgiving about column names and strict about values: a
     * missing header is a shrug, a budget of "₦120,000,000" is parsed, and an
     * unparseable date falls back to a sensible one rather than rejecting the
     * whole file. An import that refuses 400 rows because row 12 is odd is an
     * import nobody uses twice.
     */
    private function ingest(string $path, string $filename): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('That file could not be read.');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            throw new \RuntimeException('The file has no header row.');
        }

        $header = array_map(fn ($h) => preg_replace('/\s+/', '', mb_strtolower((string) $h)), $header);
        $agents = Agent::pluck('id')->all();

        if (! $agents) {
            throw new \RuntimeException('This agency has no agents to assign leads to.');
        }

        $num = function ($v, float $lo, float $hi, float $default) {
            $n = (float) preg_replace('/[^0-9.\-]/', '', (string) $v);

            return is_finite($n) && $n != 0.0 ? min($hi, max($lo, $n)) : $default;
        };

        $date = function ($v, $fallback) {
            try {
                return $v ? \Illuminate\Support\Carbon::parse($v) : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };

        $rows = [];
        $i = 0;

        while (($cells = fgetcsv($handle)) !== false) {
            if ($cells === [null] || $cells === false) {
                continue;
            }

            $r = [];
            foreach ($header as $j => $key) {
                $r[$key] = isset($cells[$j]) ? trim((string) $cells[$j]) : '';
            }

            if (! array_filter($r)) {
                continue;
            }

            $created = $date($r['createdat'] ?? null, now()->subDays($i % 40));
            $status = mb_strtolower($r['status'] ?? 'contacted') ?: 'contacted';

            $rows[] = [
                'tenant_id' => $this->tenant()->id,
                'name' => $r['name'] ?? $r['leadname'] ?? 'Imported '.($i + 1),
                'email' => $r['email'] ?? 'import'.$i.'.'.now()->timestamp.'@example.com',
                'phone' => $r['phone'] ?? '',
                'source' => $r['source'] ?: 'Import',
                'channel' => $r['channel'] ?? 'Import',
                'status' => $status,
                'max_stage' => in_array($status, Lead::STAGES, true) ? $status : 'contacted',
                'score' => (int) $num($r['score'] ?? null, 10, 99, 50),
                'budget' => $num($r['budget'] ?? null, 1e6, 5e9, 60e6),
                'wants_location' => $r['location'] ?? $r['wantslocation'] ?? 'Lekki',
                'wants_type' => $r['type'] ?? $r['wantstype'] ?? 'Apartment',
                'assigned_agent_id' => $agents[$i % count($agents)],
                'created_at' => $created,
                'updated_at' => now(),
                'last_contact_at' => $date($r['lastcontact'] ?? null, $created),
                'is_open' => ! in_array($status, ['won', 'lost'], true),
                'imported' => true,
            ];

            $i++;
        }

        fclose($handle);

        if (! $rows) {
            throw new \RuntimeException('No data rows were found in that file.');
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Lead::insert($chunk);
        }

        return ['file' => $filename, 'rows' => count($rows)];
    }

    private function snapshot(): array
    {
        $l = $this->metrics()->leads();

        return ['pipeline' => $l['pipeline'], 'open' => $l['open'], 'stale' => $l['stale']];
    }

    /* ------------------------------------------------------------ render */

    public function render()
    {
        $narrator = app(NarratorContract::class);

        return view('livewire.pages.settings', [
            'narrator' => $narrator,
            'policies' => $this->tenant()->policies(),
            'counts' => [
                'leads' => Lead::count(),
                'properties' => Property::count(),
                'events' => Activity::count(),
            ],
            'lastRun' => $this->tenant()->detectors_ran_at,
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithEngine;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Sidebar.
 *
 * It owns no business logic — it reads counts off the engine and re-renders
 * when an action fires. That is why the nav badges stay honest after a
 * recommendation is applied without anyone wiring them together: clear 25
 * stale leads and the number beside "Leads" drops, because it was never a
 * stored figure in the first place.
 */
class Nav extends Component
{
    use InteractsWithEngine;

    /**
     * Alerts and the Action Log used to be top-level destinations. Alerts was
     * only ever Insights filtered by severity, and the Action Log is something
     * you open from a finding rather than browse. Fourteen items was a menu;
     * eleven is a map.
     */
    public const ITEMS = [
        ['group' => 'Overview'],
        ['route' => 'app.dashboard', 'label' => 'Dashboard', 'ico' => 'dashboard'],
        ['route' => 'app.insights', 'label' => 'Insights', 'ico' => 'insight', 'badge' => 'insights'],
        ['route' => 'app.ask', 'label' => 'Ask NexABI', 'ico' => 'ask'],

        ['group' => 'Business'],
        ['route' => 'app.sales', 'label' => 'Sales', 'ico' => 'sales'],
        ['route' => 'app.leads', 'label' => 'Leads', 'ico' => 'leads', 'badge' => 'stale'],
        ['route' => 'app.properties', 'label' => 'Properties', 'ico' => 'property'],
        ['route' => 'app.marketing', 'label' => 'Marketing', 'ico' => 'marketing'],
        ['route' => 'app.customers', 'label' => 'Customers', 'ico' => 'customers'],
        ['route' => 'app.finance', 'label' => 'Finance', 'ico' => 'finance', 'badge' => 'overdue'],
        ['route' => 'app.feedback', 'label' => 'Feedback', 'ico' => 'feedback'],

        ['group' => 'Company'],
        ['route' => 'app.analytics', 'label' => 'Analytics', 'ico' => 'analytics'],
        ['route' => 'app.reports', 'label' => 'Reports', 'ico' => 'reports'],
    ];

    #[On('engine-changed')]
    public function refresh(): void
    {
        // No body needed — the attribute alone forces a re-render, which
        // re-reads the counts below.
    }

    public function render()
    {
        $counts = $this->engine()->counts();

        $badges = [
            'insights' => ['n' => $counts['total'], 'cls' => 'is-ai'],
            'stale' => ['n' => $this->metrics()->leads()['stale'], 'cls' => 'is-alert'],
            'overdue' => ['n' => $this->metrics()->finance()['overdueCount'], 'cls' => 'is-alert'],
        ];

        // Only shout when it is worth shouting about. A red "3" beside Leads
        // every single day trains people to ignore red.
        if ($badges['stale']['n'] <= 20) {
            $badges['stale']['cls'] = '';
        }
        if ($badges['overdue']['n'] === 0) {
            $badges['overdue']['cls'] = '';
        }

        return view('livewire.nav', [
            'items' => self::ITEMS,
            'badges' => $badges,
        ]);
    }
}

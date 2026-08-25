<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\ActionLog;
use App\Models\Activity as ActivityModel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Action Log — proof the loop closed.
 *
 * Every accepted recommendation lands here with a before/after diff. This is
 * the view that answers the only sceptical question that matters: "did
 * anything actually change, or did the card just disappear?"
 */
#[Layout('layouts.app')]
#[Title('Action Log')]
class Activity extends Component
{
    use InteractsWithEngine, WithPagination;

    /**
     * Exported server-side rather than assembled in the browser, so the file
     * is the database's account of what happened, not the page's.
     */
    public function export(): StreamedResponse
    {
        $rows = ActionLog::with('insight:id,title,detector')->latest()->get();
        $filename = 'nexabi-action-log-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($rows) {
            echo $rows->map(fn ($a) => [
                'at' => $a->created_at->toIso8601String(),
                'action' => $a->action_type,
                'label' => $a->label,
                'message' => $a->message,
                'before' => $a->before,
                'after' => $a->after,
                'affected' => $a->affected,
                'impact' => $a->impact,
                'area' => $a->area,
                'finding' => $a->insight?->title,
                'detector' => $a->insight?->detector,
            ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function render()
    {
        $log = ActionLog::with('insight:id,title')->latest()->paginate(30);

        $totals = ActionLog::selectRaw('COUNT(*) n, SUM(affected) affected, SUM(impact) impact')->first();

        return view('livewire.pages.activity', [
            'log' => $log,
            'count' => (int) $totals->n,
            'affected' => (int) $totals->affected,
            'impact' => (float) $totals->impact,
            'last' => ActionLog::latest()->first(),
            'events' => ActivityModel::with('agent:id,name')
                ->where('at', '<=', now())
                ->orderByDesc('at')->limit(40)->get(),
        ]);
    }
}

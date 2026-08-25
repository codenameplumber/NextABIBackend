@props(['status'])
@php
    // Wording a buyer would use, not wording a ledger would use. "Due now"
    // beats "pending"; "Overdue" is only said once it genuinely is.
    $map = [
        'paid' => ['Paid', 'pos'],
        'overdue' => ['Overdue', 'neg'],
        'due' => ['Due now', 'high'],
        'scheduled' => ['Scheduled', ''],
        'partial' => ['Part paid', 'medium'],
    ];
    [$label, $kind] = $map[$status] ?? [App\Support\Fmt::titleCase($status), ''];
@endphp
<x-badge :kind="$kind" dot>{{ $label }}</x-badge>

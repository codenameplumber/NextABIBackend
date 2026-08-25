<x-mail.layout
    eyebrow="Welcome"
    :heading="$tenant->name.' is ready'"
    preheader="Import your book, or explore the sample agency first."
    cta="Open the console"
    :cta-url="route('app.dashboard')">

    {{-- firstName() with the parentheses: Eloquent reads a bare property that
         matches a method name as a relationship and throws. --}}
    <p style="margin:0 0 14px;">Hello {{ $user->firstName() }},</p>

    <p style="margin:0 0 14px;">
        Your agency is set up. Everything you add from here stays inside it — every
        record is stamped with {{ $tenant->name }} and every query is scoped to it at the
        database layer, not by a filter someone can forget to write.
    </p>

    <p style="margin:0 0 14px;">
        The fastest way to see what NexABI does is to give it something to read.
        In <b>Settings → Data ingestion</b> you can import a CSV of leads and watch the
        figures move and new findings appear. If you would rather look around first,
        the sample Lagos agency is already loaded.
    </p>

    <p style="margin:0 0 6px;">Three things worth knowing on day one:</p>

    <ul style="margin:0 0 14px;padding-left:20px;">
        <li style="padding-bottom:7px;">
            The detectors run on a schedule. You do not have to go looking — findings
            appear on their own, and close themselves when you fix the cause.
        </li>
        <li style="padding-bottom:7px;">
            Every figure is drillable. Click any number on a finding and you land on the
            rows that produced it.
        </li>
        <li>
            Every recommendation has a button. Applying it changes real records and logs
            what moved, so you can check it did.
        </li>
    </ul>

    <x-slot:footer>
        You are receiving this because you created {{ $tenant->name }} on {{ config('app.name') }}.
    </x-slot:footer>
</x-mail.layout>

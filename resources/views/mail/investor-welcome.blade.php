<x-mail.layout
    eyebrow="Welcome"
    heading="Your account is ready"
    preheader="Browse properties and start whenever you are ready."
    cta="Browse properties"
    :cta-url="route('portal.browse')">

    {{-- firstName() with the parentheses: Eloquent reads a bare property that
         matches a method name as a relationship and throws. --}}
    <p style="margin:0 0 14px;">Hello {{ $user->firstName() }},</p>

    <p style="margin:0 0 14px;">
        Your account is set up. You can browse everything available, pick a property, and
        choose how long you want to pay over — the monthly figure updates as you move the
        term, so you decide with the real number in front of you.
    </p>

    @if ($realtor)
        <p style="margin:0 0 14px;">
            <b>{{ $realtor->name }}</b> is your realtor. Anything you are unsure about, they
            are the person to ask{{ $realtor->phone ? ' — '.$realtor->phone : '' }}.
        </p>
    @else
        <p style="margin:0 0 14px;">
            We are assigning a realtor to you now. They will introduce themselves shortly,
            and in the meantime nothing stops you looking around.
        </p>
    @endif

    <p style="margin:0 0 6px;">How it works from here:</p>

    <ul style="margin:0 0 14px;padding-left:20px;">
        <li style="padding-bottom:7px;">
            <b>Commit</b> to a property and the unit is held for you while we confirm the
            details. Nothing is binding until it is approved.
        </li>
        <li style="padding-bottom:7px;">
            <b>Your schedule opens</b> once it is approved — every instalment, with its due
            date, in one table.
        </li>
        <li style="padding-bottom:7px;">
            <b>Every payment is recorded</b> as it arrives, so you can always see exactly
            how much of the property you own.
        </li>
    </ul>

    <p style="margin:0 0 14px;">
        You never have to guess where you stand. That is the whole point.
    </p>
</x-mail.layout>

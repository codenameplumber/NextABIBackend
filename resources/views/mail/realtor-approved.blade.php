<x-mail.layout
    eyebrow="Approved"
    heading="You are in"
    preheader="Here is your referral code and your registration link."
    cta="Open my dashboard"
    :cta-url="route('realtor.dashboard')">

    <p style="margin:0 0 14px;">Hello {{ $realtor->firstName() }},</p>

    <p style="margin:0 0 14px;">
        Your application has been approved. Everything is open to you now — the full
        catalogue, promotional materials with your details already in them, and a
        commission statement you can check line by line.
    </p>

    {{-- The code and the link are the entire reason they have been waiting, so
         they go in the body rather than behind a sign-in. --}}
    <p style="margin:0 0 6px;">Your referral code:</p>

    <p style="margin:0 0 18px;font-family:ui-monospace,Menlo,Consolas,monospace;
              font-size:22px;letter-spacing:3px;font-weight:600;">
        {{ $realtor->code }}
    </p>

    <p style="margin:0 0 6px;">Your registration link:</p>

    <p style="margin:0 0 18px;word-break:break-all;">
        <a href="{{ $realtor->registrationLink() }}">{{ $realtor->registrationLink() }}</a>
    </p>

    <p style="margin:0 0 14px;">
        Send that link to anyone you are working with. They register through it and are
        attributed to you automatically — there is no code for them to remember and
        nothing for you to file.
    </p>

    <p style="margin:0 0 6px;">Two things worth knowing:</p>

    <ul style="margin:0 0 14px;padding-left:20px;">
        <li style="padding-bottom:7px;">
            Commission is set per property, up to 15%, and is shown against every listing
            before you decide what to push.
        </li>
        <li style="padding-bottom:7px;">
            The rate is fixed at the moment a sale is approved, so what you are owed
            cannot change afterwards.
        </li>
    </ul>
</x-mail.layout>

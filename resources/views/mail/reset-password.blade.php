<x-mail.layout
    eyebrow="Password reset"
    heading="Set a new password"
    preheader="This link works once and expires shortly."
    cta="Choose a new password"
    :cta-url="$url">

    <p style="margin:0 0 14px;">Hello {{ explode(' ', $user->name)[0] }},</p>

    <p style="margin:0 0 14px;">
        Someone asked to reset the password on this account. If that was you, use the
        button below — the link works once and expires in {{ $minutes }} minutes.
    </p>

    <p style="margin:0;">
        If it was not you, nothing has changed and you can ignore this. Your current
        password still works, and nobody has been told whether this address has an
        account.
    </p>

    <x-slot:footer>
        Sent because a reset was requested for {{ $user->email }}.
    </x-slot:footer>
</x-mail.layout>

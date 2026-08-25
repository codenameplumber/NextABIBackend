@props([
    'preheader' => null,
    'eyebrow' => null,
    'heading',
    'cta' => null,
    'ctaUrl' => null,
])
{{--
    One email layout, inlined.

    Email clients have no <style> support worth relying on, no custom
    properties, no web fonts and no dark-mode tokens — so none of the app's
    stylesheet applies here. This is a light-mode design that carries the same
    two things the product is recognisable by: the gold accent used only for
    emphasis, and figures set in a tabular face.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F2EE;">

    {{-- The line most inboxes show next to the subject. Without it they pull
         the first words of the body, which is usually the brand name twice. --}}
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background:#F4F2EE;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:560px;background:#FFFFFF;border:1px solid #E3DFD7;border-radius:14px;overflow:hidden;">

                    <tr>
                        <td style="padding:26px 32px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-right:10px;vertical-align:middle;">
                                        <span style="display:inline-block;width:30px;height:30px;background:#171614;border-radius:8px;text-align:center;line-height:30px;">
                                            <span style="color:#E0A82E;font:700 15px/30px Georgia,serif;">N</span>
                                        </span>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <div style="font:600 15px/1.1 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#171614;">
                                            {{ config('app.name') }}
                                        </div>
                                        <div style="font:400 11px/1.4 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#8B8578;">
                                            Adaptive Business Intelligence
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 32px 8px;">
                            @if ($eyebrow)
                                <div style="font:600 11px/1.3 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:.09em;text-transform:uppercase;color:#A08019;padding-bottom:10px;">
                                    {{ $eyebrow }}
                                </div>
                            @endif
                            <h1 style="margin:0;font:600 23px/1.25 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:-.02em;color:#171614;">
                                {{ $heading }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 32px 4px;font:400 15px/1.62 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#413D36;">
                            {{ $slot }}
                        </td>
                    </tr>

                    @if ($cta && $ctaUrl)
                        <tr>
                            <td style="padding:22px 32px 30px;">
                                <a href="{{ $ctaUrl }}"
                                   style="display:inline-block;background:#E0A82E;color:#171614;text-decoration:none;font:600 14px/1 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;padding:13px 22px;border-radius:9px;">
                                    {{ $cta }}
                                </a>
                                <div style="padding-top:14px;font:400 12px/1.6 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#8B8578;word-break:break-all;">
                                    If the button does not work, paste this into your browser:<br>
                                    <span style="color:#6B665C;">{{ $ctaUrl }}</span>
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr><td style="padding-bottom:26px;"></td></tr>
                    @endif

                    <tr>
                        <td style="padding:18px 32px 24px;border-top:1px solid #EDE9E2;font:400 12px/1.6 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#8B8578;">
                            {{ $footer ?? 'You are receiving this because you have an account with '.config('app.name').'.' }}
                        </td>
                    </tr>
                </table>

                <div style="max-width:560px;padding:16px 8px 0;font:400 11px/1.6 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#A39C8E;text-align:center;">
                    {{ config('app.name') }} — business data to insight to action.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

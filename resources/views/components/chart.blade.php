@props(['kind', 'payload' => [], 'size' => ''])
{{--
    Chart host.

    The payload is JSON-encoded with quotes and apostrophes hex-escaped, so it
    survives being an HTML attribute regardless of what is in the labels — a
    property titled "Ade's Court" would otherwise close the attribute early and
    silently break the chart.
--}}
<div {{ $attributes->merge(['class' => trim('chartbox '.($size ? "chartbox--{$size}" : ''))]) }}
     data-chart="{{ $kind }}"
     data-payload='{{ json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) }}'></div>

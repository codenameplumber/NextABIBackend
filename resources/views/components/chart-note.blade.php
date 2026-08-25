{{-- A sentence telling the reader what to look for. Charts do not explain
     themselves, and "read the chart" is not a skill everyone has. --}}
<div {{ $attributes->merge(['class' => 'chart-note']) }}>
    <x-icon name="info" size="13" />
    <span>{{ $slot }}</span>
</div>

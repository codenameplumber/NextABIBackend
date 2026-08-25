@props(['title' => 'Nothing here', 'msg' => null, 'ico' => 'search'])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    <div class="empty__icon"><x-icon :name="$ico" size="20" /></div>
    <div class="empty__title">{{ $title }}</div>
    @if ($msg)<div class="empty__msg">{{ $msg }}</div>@endif
    {{ $slot }}
</div>

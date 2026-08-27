@php use App\Support\Fmt; @endphp

<div>
    <x-page-head title="Promotional materials"
                 :sub="$materials->count().' ready to send · your link is already in every one'" />

    <x-page-note>
        Nothing here needs editing. **Your name, number and registration link are already
        filled in** — copy it and send it. Anyone who registers through it is attributed to you.
    </x-page-note>

    @if ($materials->isEmpty())
        <x-empty title="Nothing published yet"
                 msg="Your admin has not put any materials up. Your registration link works on its own in the meantime." />

        <div style="text-align:center;margin-top:var(--s-5)">
            <button class="btn btn--primary" data-copy="{{ $realtor->registrationLink() }}">
                <x-icon name="plus" size="15" /><span>Copy my registration link</span>
            </button>
        </div>
    @else
        <div class="stack">
            @foreach ($materials as $row)
                @php $m = $row['model']; @endphp
                <x-card :title="$m->title"
                        :sub="$m->kindLabel().($m->property ? ' · '.$m->property->title : '')">
                    <x-slot:actions>
                        <button class="btn btn--sm" data-copy="{{ $row['personalised'] }}"
                                wire:click="markDownloaded({{ $m->id }})">
                            <x-icon name="plus" size="13" /><span>Copy</span>
                        </button>
                    </x-slot:actions>

                    {{-- Shown exactly as it will be sent. A template with the
                         placeholders still visible is one somebody pastes with
                         "call me on {phone}" left in it. --}}
                    <pre class="material-body">{{ $row['personalised'] }}</pre>
                </x-card>
            @endforeach
        </div>
    @endif
</div>

<div class="hold">
    <div class="hold__ico"><x-icon name="clock" size="26" /></div>

    <h1>Your application is with us.</h1>

    <p>
        Thanks, {{ $realtor->firstName() }}. Someone reviews every application by hand — usually
        within a working day. We will email <b>{{ $realtor->email }}</b> the moment it is done.
    </p>

    {{-- What happens next, spelled out. An application that ends on a blank
         page reads as one that went nowhere, and the person signs up twice. --}}
    <div class="hold__steps">
        <div class="row-2" style="align-items:flex-start;gap:var(--s-3);padding-bottom:var(--s-4)">
            <span class="agent-avatar" style="width:26px;height:26px;font-size:11px;flex:none">1</span>
            <div>
                <div class="semi">We check your details</div>
                <div class="t-sm t-3">Usually same day, always within two working days.</div>
            </div>
        </div>

        <div class="row-2" style="align-items:flex-start;gap:var(--s-3);padding:var(--s-4) 0;border-top:1px solid var(--border)">
            <span class="agent-avatar" style="width:26px;height:26px;font-size:11px;flex:none">2</span>
            <div>
                <div class="semi">You get your referral code</div>
                <div class="t-sm t-3">
                    A short code and a registration link that is yours. Anyone who signs up
                    through it is attributed to you automatically.
                </div>
            </div>
        </div>

        <div class="row-2" style="align-items:flex-start;gap:var(--s-3);padding-top:var(--s-4);border-top:1px solid var(--border)">
            <span class="agent-avatar" style="width:26px;height:26px;font-size:11px;flex:none">3</span>
            <div>
                <div class="semi">You start</div>
                <div class="t-sm t-3">
                    The full catalogue, promotional materials with your details already in them,
                    and a commission statement you can check line by line.
                </div>
            </div>
        </div>
    </div>

    <div class="row-2" style="justify-content:center;margin-top:var(--s-6)">
        <a class="btn btn--ghost" href="{{ route('properties') }}">
            <span>Look at the catalogue while you wait</span>
        </a>
    </div>
</div>

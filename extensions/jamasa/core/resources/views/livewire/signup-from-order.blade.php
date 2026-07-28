{{-- Inline "create an account from your order" prompt (guest success page).
     Single Livewire root; renders nothing unless there's an offer / done state. --}}
<div>
    @if($doneText)
        <div class="signup-card done">
            <span class="signup-card__ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </span>
            <div class="signup-card__body">
                <div class="signup-card__t">{{ $doneText }}</div>
                <a class="signup-card__link" href="{{ page_url('account.account') }}">Zu meinem Konto &rarr;</a>
            </div>
        </div>
    @elseif($offer)
        <div class="signup-card">
            <div class="signup-card__head">
                <span class="signup-card__ic">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/><path d="M12 8C11 5 9 4 7.6 4.8 6.2 5.6 6.7 8 12 8zM12 8c1-3 3-4 4.4-3.2C17.8 5.6 17.3 8 12 8z"/></svg>
                </span>
                <div class="signup-card__body">
                    <div class="signup-card__t">{{ $offer['teaser']['headline'] }}</div>
                    <div class="signup-card__s">{{ $offer['teaser']['subline'] }}</div>
                </div>
            </div>
            {{-- One tap, no password: accounts are passwordless (email-code
                 login). The email is server-side from the order. --}}
            {{-- Full-page POST (NOT wire:click): login rotates the CSRF token and a
                 Livewire morph left the order-preview poll on the stale token → 419.
                 A POST→redirect reloads with a fresh token. --}}
            <div class="signup-card__form">
                <div class="signup-card__for">Für <b>{{ $offer['email'] }}</b></div>
                <form method="POST" action="{{ url('jamasa/signup-from-order') }}"
                      onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Einen Moment…';">
                    @csrf
                    <input type="hidden" name="hash" value="{{ $hash }}">
                    <button type="submit" class="signup-card__btn">Konto anlegen – kein Passwort nötig</button>
                </form>
            </div>
        </div>
    @endif
</div>

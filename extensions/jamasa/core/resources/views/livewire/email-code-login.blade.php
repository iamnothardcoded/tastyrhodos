{{-- Passwordless email-code login (design: anmeldung-email-code-code.md mockup).
     Three steps: email → 6-digit code → success. The code boxes are a small JS
     island (auto-advance, backspace-nav, paste-distribute, OS one-time-code
     autofill) that calls onVerifyCode once 6 digits are in. --}}
<div class="auth" id="famedo-auth">

    <div class="auth__brand">
        <div class="auth__logo">{{ mb_strtoupper(mb_substr((string)setting('site_name'), 0, 1)) }}</div>
        <span class="auth__brandname">{{ setting('site_name') }}</span>
    </div>

    @if($step === 'email')
        <div class="auth__body">
            <div class="auth__ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/></svg>
            </div>
            <div class="auth__title">Anmelden oder Konto erstellen</div>
            <p class="auth__sub">Kurz die E-Mail eingeben – dauert 10 Sekunden, <b>kein Passwort</b> nötig.</p>

            <div class="auth__field">
                <label for="authEmail">E-Mail-Adresse</label>
                <input
                    id="authEmail"
                    type="email"
                    inputmode="email"
                    autocomplete="email"
                    placeholder="z. B. maria@web.de"
                    wire:model="email"
                    wire:keydown.enter="onRequestCode"
                >
            </div>
            <p class="auth__err">{{ $emailError }}</p>
            @if($perkTeaser)
                <p class="auth__perkline"><b>{{ $perkTeaser['headline'] }}</b> &middot; wird automatisch abgezogen</p>
            @endif
        </div>

        <button type="button" class="auth__btn" wire:click="onRequestCode" wire:loading.attr="disabled" wire:target="onRequestCode">
            <span wire:loading.remove wire:target="onRequestCode">Code zusenden</span>
            <span wire:loading wire:target="onRequestCode">Einen Moment&hellip;</span>
        </button>
        {{-- DSGVO: a visible link suffices (Kenntnisnahme) — no checkbox needed.
             AGB link joins once tenant AGB exist (see LEGAL todo). --}}
        <p class="auth__legal">Infos zur Verarbeitung deiner Daten: <a href="{{ url('/datenschutz') }}">Datenschutzerklärung</a></p>
        <a class="auth__skip" href="{{ page_url('local.menus') }}">Ohne Anmeldung als Gast bestellen</a>
    @elseif($step === 'code')
        <div class="auth__body">
            <div class="auth__ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>
            </div>
            <div class="auth__title">Code eingeben</div>
            <p class="auth__sub">Wir haben einen 6-stelligen Code an<br><b>{{ $email }}</b> geschickt.</p>
        </div>

        <div class="code-row" id="authCodeRow" wire:ignore>
            @for($i = 0; $i < 6; $i++)
                <input
                    class="code-box"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    @if($i === 0) autocomplete="one-time-code" @endif
                    aria-label="Ziffer {{ $i + 1 }}"
                >
            @endfor
        </div>
        <p class="auth__err" style="text-align:center">{{ $codeError }}</p>

        <div class="auth__resend" wire:ignore>
            Code nicht angekommen? <button type="button" id="authResendBtn" disabled>Erneut senden (30&hairsp;s)</button>
        </div>
        <div class="auth__back"><button type="button" wire:click="onBack">Andere E-Mail verwenden</button></div>
    @else
        <div class="auth__success">
            <div class="auth__check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
            <div class="auth__title">{{ $returning ? 'Willkommen zurück!' : 'Du bist angemeldet!' }}</div>
            <p class="auth__sub">{{ $returning ? 'Schön, dass du wieder da bist.' : 'Dein Konto ist erstellt – deine Bestellungen und Daten werden ab jetzt hier gespeichert.' }}</p>
            @if($successOffer)
                <div class="auth__benefit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                    {{ $successOffer }} &ndash; wird automatisch abgezogen
                </div>
            @endif
        </div>
        {{-- Plain link, NOT a Livewire action: Session::regenerate() rotated the
             CSRF token at verify — a wire:click here would 419 ("page expired"). --}}
        <a class="auth__btn" style="margin-top:34px" href="{{ $continueUrl ?: page_url('local.menus') }}">Weiter zur Bestellung</a>
    @endif

    @script
    <script>
    (() => {
        const root = document.getElementById('famedo-auth');
        if (!root) return;

        const boxes = () => [...root.querySelectorAll('.code-box')];

        const submitIfComplete = () => {
            const code = boxes().map(b => b.value).join('');
            if (code.length === 6 && /^\d{6}$/.test(code)) $wire.onVerifyCode(code);
        };

        const clearBoxes = (focus = true) => {
            boxes().forEach(b => { b.value = ''; });
            if (focus) boxes()[0]?.focus();
        };

        // Delegated handlers survive Livewire re-renders.
        root.addEventListener('input', (e) => {
            const box = e.target;
            if (!box.classList?.contains('code-box')) return;
            const digits = box.value.replace(/\D/g, '');
            if (digits.length > 1) { distribute(box, digits); return; } // OS autofill drops all 6 in one box
            box.value = digits.slice(0, 1);
            const all = boxes();
            const i = all.indexOf(box);
            if (box.value && i < all.length - 1) all[i + 1].focus();
            submitIfComplete();
        });

        root.addEventListener('keydown', (e) => {
            const box = e.target;
            if (!box.classList?.contains('code-box')) return;
            const all = boxes();
            const i = all.indexOf(box);
            if (e.key === 'Backspace' && !box.value && i > 0) all[i - 1].focus();
        });

        const distribute = (fromBox, digits) => {
            const all = boxes();
            let i = all.indexOf(fromBox);
            if (i < 0) i = 0;
            if (digits.length >= 6) i = 0; // full code pasted → fill from the start
            digits.slice(0, 6 - i).split('').forEach((d, k) => { all[i + k].value = d; });
            all[Math.min(i + digits.length, 5)].focus();
            submitIfComplete();
        };

        root.addEventListener('paste', (e) => {
            const box = e.target;
            if (!box.classList?.contains('code-box')) return;
            e.preventDefault();
            const digits = (e.clipboardData?.getData('text') || '').replace(/\D/g, '');
            if (digits) distribute(box, digits);
        });

        // Resend: 30s client timer (the server rate-limit is the real gate).
        let resendDeadline = 0;
        setInterval(() => {
            const btn = document.getElementById('authResendBtn');
            if (!btn) return;
            const left = Math.ceil((resendDeadline - Date.now()) / 1000);
            if (left > 0) { btn.disabled = true; btn.textContent = `Erneut senden (${left} s)`; }
            else { btn.disabled = false; btn.textContent = 'Erneut senden'; }
        }, 500);
        root.addEventListener('click', (e) => {
            if (e.target?.id === 'authResendBtn' && !e.target.disabled) $wire.onResend();
        });

        $wire.on('auth-code-step', () => {
            resendDeadline = Date.now() + 30000;
            setTimeout(() => clearBoxes(true), 50);
        });

        $wire.on('auth-code-invalid', () => {
            const row = document.getElementById('authCodeRow');
            if (!row) return;
            row.classList.add('err', 'shake');
            setTimeout(() => row.classList.remove('shake'), 400);
            setTimeout(() => { row.classList.remove('err'); clearBoxes(true); }, 700);
        });
    })();
    </script>
    @endscript
</div>

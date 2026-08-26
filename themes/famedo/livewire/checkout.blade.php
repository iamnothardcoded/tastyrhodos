{{-- jamasa/core override of igniter-orange::livewire.checkout (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo single-column checkout. checkout.js contract preserved verbatim:
     data-control="checkout" wrapper with all data-*-event attributes. Cart
     summary (cart-preview) moves below the form. --}}
<div>
    <div class="famedo-checkout__head">
        <h1 class="sheet__title">@lang('igniter.orange::default.text_title_checkout') {{ $locationCurrent->getName() }}</h1>

        <p class="sheet__desc">
            {!! $customer
                ? sprintf(lang('igniter.orange::default.text_logged_out'), e($customer->first_name), url('logout'))
                {{-- ?redirect= → ReturnUrl::capture on the login page: a mid-checkout
                     login must come BACK to checkout, not fall to the Speisekarte
                     (Global TODO #1 (d) — url.intended was never set from here). --}}
                : sprintf(lang('igniter.orange::default.text_logged_in'), page_url('account.login').'?redirect='.urlencode(url()->current()))
            !!}
        </p>
    </div>

    <div
        data-control="checkout"
        data-partial="checkoutForm"
        data-payment-input-name="fields.payment"
        data-validate-event="checkout::validate"
        data-confirm-event="checkout::confirm"
        data-choose-payment-event="checkout::choose-payment"
        data-delete-payment-profile-event="checkout::delete-payment-profile"
    >
        @includeWhen($isTwoPageCheckout, 'igniter-orange::includes.checkout.two-step-form')
        @includeUnless($isTwoPageCheckout, 'igniter-orange::includes.checkout.one-step-form')
    </div>

    <div class="famedo-checkout__summary famedo-card">
        <x-igniter-orange::cart-preview />
    </div>
</div>

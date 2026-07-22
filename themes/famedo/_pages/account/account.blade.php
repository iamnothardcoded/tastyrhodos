---
title: igniter.orange::default.account_title
permalink: /account
layout: default
security: customer
---
@php($firstName = \Igniter\User\Facades\Auth::isLogged() ? \Igniter\User\Facades\Auth::getFirstName() : null)
<div class="container my-4 my-md-5">
    <div class="account-hub">
        <h1 class="account-hub__greet">Hallo{{ $firstName ? ', '.e($firstName) : '' }}</h1>
        <p class="account-hub__lead">Dein Konto — verwalte deine Daten, Adressen und Bestellungen.</p>
        <div class="account-hub__grid">
            <a class="account-tile" href="{{ page_url('account.profile') }}">
                <span class="account-tile__ic"><i class="fa fa-user"></i></span>
                <span class="account-tile__t">Mein Konto</span>
                <span class="account-tile__s">Name &amp; Kontakt</span>
                <i class="fa fa-chevron-right account-tile__chev"></i>
            </a>
            <a class="account-tile" href="{{ page_url('account.address') }}">
                <span class="account-tile__ic"><i class="fa fa-book"></i></span>
                <span class="account-tile__t">Adressbuch</span>
                <span class="account-tile__s">Lieferadressen</span>
                <i class="fa fa-chevron-right account-tile__chev"></i>
            </a>
            <a class="account-tile" href="{{ page_url('account.orders') }}">
                <span class="account-tile__ic"><i class="fa fa-receipt"></i></span>
                <span class="account-tile__t">Bestellungen</span>
                <span class="account-tile__s">Deine Historie</span>
                <i class="fa fa-chevron-right account-tile__chev"></i>
            </a>
            @if(\Igniter\Local\Facades\Location::current()?->getSettings('booking.is_enabled', false))
            <a class="account-tile" href="{{ page_url('account.reservations') }}">
                <span class="account-tile__ic"><i class="fa fa-calendar"></i></span>
                <span class="account-tile__t">Reservierungen</span>
                <span class="account-tile__s">Deine Tische</span>
                <i class="fa fa-chevron-right account-tile__chev"></i>
            </a>
            @endif
        </div>
    </div>
</div>

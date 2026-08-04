---
description: Default layout
---
{{-- famedo theme layout (forked from ti-theme-orange v4.1.3 _layouts/default) --}}
{{-- Famedo app-column shell: `famedo` body class scopes famedo.css; .app caps content at --wrap. --}}
@php
    // Closed/paused/preorder overlay state — paused FIRST (a pause forces the
    // schedule closed via PauseWorkingSchedule, so the schedule check is true
    // then too; pause therefore always outranks preorder). isDisabled() covers
    // only the admin toggle; after-hours = schedule closed. Closed splits into
    // 'preorder' (future_orders on + same-day slots left → NO lockout, inviting
    // copy) vs plain 'closed' (lockout as before).
    try {
        $famedoOrderingState = \Jamasa\Core\Helpers\OrderingState::isPaused()
            ? 'paused'
            : ((!($famedoOrderType = \Igniter\Local\Facades\Location::getOrderType())
                || $famedoOrderType->isDisabled()
                || !$famedoOrderType->getSchedule()->isOpen())
                ? (\Jamasa\Core\Helpers\Preorder::isAvailable() ? 'preorder' : 'closed')
                : 'open');
    } catch (\Throwable) {
        $famedoOrderingState = 'open'; // fail open — server-side validation still blocks
    }
@endphp
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ App::getLocale() }}" class="h-100">
<head>
    @include('igniter-orange::includes.head')
    @livewireStyles
</head>
{{-- No h-100/d-flex on body: they clamp .app (flex child) to viewport height,
     cutting the white background off after one screen. .app sizes itself. --}}
<body class="famedo page-{{ str_slug(str_replace('/', '-', $this->page->getBaseFileName() ?? 'unknown')) }} {{ $this->page->bodyClass }}{{ $famedoOrderingState !== 'open' ? ' ordering-'.$famedoOrderingState : '' }}{{ \Igniter\User\Facades\Auth::isLogged() ? ' famedo-authed' : '' }}">

<div class="app">
    <header class="header">
        @include('igniter-orange::includes.header')
    </header>

    <main role="main">
        <div id="page-wrapper">
            @themePage
        </div>
    </main>

    @unless($this->page->hideFooter)
    <footer class="footer mt-auto">
        @include('igniter-orange::includes.footer')
    </footer>
    @endunless
</div>
<livewire:igniter-orange::utils.modal/>
<livewire:igniter-orange::utils.flash-message/>
@include('igniter-orange::includes.eucookiebanner')
@include('igniter-orange::includes.ordering-overlay', ['ovState' => $famedoOrderingState])
@livewireScripts
@include('igniter-orange::includes.scripts')
</body>
</html>

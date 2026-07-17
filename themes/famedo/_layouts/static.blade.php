---
description: Static layout for static pages
---
{{-- famedo theme layout (forked from ti-theme-orange v4.1.3 _layouts/static) --}}
{{-- Famedo shell for static pages (Impressum/Datenschutz/AGB): the `famedo` body
     class scopes famedo.css (orange's layout lacked it → these pages rendered
     stock orange). Orange's pages-menu sidebar dropped — the footer links the
     legal pages already. --}}
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ App::getLocale() }}" class="h-100">
<head>
    @include('igniter-orange::includes.head')
    @livewireStyles
</head>
<body class="famedo page-static {{ $this->page->bodyClass }}">

<div class="app">
    <header class="header">
        @include('igniter-orange::includes.header')
    </header>

    <main role="main">
        <div id="page-wrapper">
            <div class="static-page">
                <h1 class="static-page__title">{{ $this->page->title }}</h1>
                <div class="static-page__content">
                    @themePage
                </div>
            </div>
        </div>
    </main>

    <footer class="footer mt-auto">
        @include('igniter-orange::includes.footer')
    </footer>
</div>
<livewire:igniter-orange::utils.flash-message/>
@include('igniter-orange::includes.eucookiebanner')
@livewireScripts
@include('igniter-orange::includes.scripts')
</body>
</html>

{{-- Global empty state when the active filter combination matches nothing.
     Mount OUTSIDE the menu's Livewire root so morphs can't wipe it. --}}
<div class="fempty" data-diet-empty hidden>
    <div class="fempty__t">{{ lang('iamnothardcoded.foodlabels::default.filter_empty_title') }}</div>
    {{ lang('iamnothardcoded.foodlabels::default.filter_empty_body') }}
    <br><button type="button" data-diet-clear>{{ lang('iamnothardcoded.foodlabels::default.filter_empty_clear') }}</button>
</div>

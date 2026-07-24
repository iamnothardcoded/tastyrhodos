{{-- famedo override of igniter-orange::includes.account.address-book-form (forked from ti-theme-orange v4.1.3) --}}
{{-- Photon search-driven address form (same UX as the checkout fulfillment sheet):
     one search field → suggestions from /jamasa/address-suggestions → pick →
     Straße + REQUIRED Hausnummer + PLZ + Stadt (prefilled, editable) + optional
     Adresszusatz. Alpine (FamedoAddressBook, famedo.js) writes the STRUCTURED
     form.* wire props as deferred sets that ride the onSave request. `state` is
     hidden (unused in DE), `country_id` keeps its mount default (no select).
     CONTRACTS kept from vendor: modal id, wire:submit="onSave", hidden
     form.address_id, is_default block, button wiring, the @script modal
     lifecycle block (byte-identical), error displays. If upstream renames the
     form.* props this fork throws loudly on the deferred sets. --}}
<div
    id="addressBookFormModal"
    @class(['modal fade'])
    role="dialog"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <x-igniter-orange::forms.form
                    wire:submit="onSave"
                    role="form"
                >
                    <input
                        type="hidden"
                        wire:model="form.address_id"
                    />
                    <div x-data="FamedoAddressBook(
                        @js($selectAddress?->address_1),
                        @js((string)($selectAddress?->postcode ?? '')),
                        @js($selectAddress?->city),
                        @js($selectAddress?->country_id ?? \Igniter\System\Models\Country::getDefaultKey())
                    )">
                        {{-- SEARCH MODE --}}
                        <template x-if="!abPicked">
                            <div class="form-group">
                                <label for="ab-search-query">@lang('jamasa.core::default.address.search_label')</label>
                                <input
                                    id="ab-search-query"
                                    type="search"
                                    class="form-control"
                                    x-ref="abQuery"
                                    x-model="abQuery"
                                    x-on:input.debounce.400ms="abSearch()"
                                    placeholder="@lang('jamasa.core::default.address.search_placeholder')"
                                    autocomplete="off"
                                />
                                <div x-show="abLoading" class="famedo-addr-searching py-2">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>@lang('jamasa.core::default.address.searching')
                                </div>
                                <div x-show="!abLoading && abSuggestions.length" class="autocomplete-suggestions list-group mt-2 border-top rounded-bottom-0">
                                    <template x-for="s in abSuggestions" :key="(s.road || '') + '|' + (s.houseNumber || '') + '|' + (s.postcode || '')">
                                        <button
                                            type="button"
                                            class="list-group-item list-group-item-action famedo-suggestion"
                                            x-on:click="abPick(s)"
                                        >
                                            <div class="fw-bold" x-text="((s.road || '') + ' ' + (s.houseNumber || '')).trim()"></div>
                                            <div class="famedo-suggestion__sub" x-text="((s.postcode || '') + ' ' + (s.city || '')).trim()"></div>
                                        </button>
                                    </template>
                                </div>
                                <div x-show="!abLoading && abSearched && !abSuggestions.length" class="list-group-item text-center mt-2">
                                    @lang('jamasa.core::default.address.no_suggestions')
                                </div>
                                {{-- ODbL attribution — required wherever OSM data is shown --}}
                                <div x-show="!abLoading && abSearched" class="p-1 text-end border rounded rounded-top-0 famedo-osm-credit">
                                    <small>
                                        Powered by
                                        <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>
                                    </small>
                                </div>
                                {{-- manual fallback — address not found / not in OSM --}}
                                <button type="button" class="btn btn-link px-0 mt-1" x-on:click="abManual()">
                                    @lang('jamasa.core::default.address.manual_entry')
                                </button>
                            </div>
                        </template>

                        {{-- PICKED / EDIT MODE --}}
                        <template x-if="abPicked">
                            <div>
                                <div class="row">
                                    <div class="col-8">
                                        <div class="form-group">
                                            <label for="ab-road">@lang('jamasa.core::default.address.street')</label>
                                            <input id="ab-road" class="form-control" x-ref="abRoadEl" x-model="abRoad" x-on:input="abSync()" />
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-group">
                                            <label for="ab-nr">@lang('jamasa.core::default.address.number')</label>
                                            <input id="ab-nr" class="form-control" x-ref="abNr" x-model="abNr" x-on:input="abSync()" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="form-group">
                                            <label for="ab-plz">@lang('jamasa.core::default.address.postcode')</label>
                                            <input id="ab-plz" class="form-control" x-ref="abPlzEl" x-model="abPlz" x-on:input="abSync()" inputmode="numeric" pattern="\d{5}" />
                                        </div>
                                    </div>
                                    <div class="col-8">
                                        <div class="form-group">
                                            <label for="ab-city">@lang('jamasa.core::default.address.city')</label>
                                            <input id="ab-city" class="form-control" x-model="abCity" x-on:input="abSync()" />
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ab-address2">@lang('jamasa.core::default.address.address2_label')</label>
                                    <input
                                        id="ab-address2"
                                        wire:model="form.address_2"
                                        class="form-control"
                                        placeholder="@lang('jamasa.core::default.address.address2_placeholder')"
                                    />
                                    <x-igniter-orange::forms.error field="form.address_2" class="text-danger"/>
                                </div>
                                <div x-show="abPicked && !abNr.trim()" class="text-danger small mb-2">
                                    @lang('jamasa.core::default.address.number_missing')
                                </div>
                                {{-- delivery-zone gate: single-restaurant platform — undeliverable
                                     addresses can't be saved (same message as the fulfillment sheet) --}}
                                <div x-show="abZone === 'out'" class="text-danger small mb-2">
                                    @lang('igniter.local::default.alert_delivery_area_unavailable')
                                </div>
                                <div x-show="abZone === 'checking'" class="text-muted small mb-2">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>@lang('jamasa.core::default.address.zone_checking')
                                </div>
                                {{-- couldn't verify (geocoder blind) — inform, never block.
                                     Short line + tap-to-expand ⓘ details. --}}
                                <div x-show="abZone === 'unverified'" x-data="{ abInfoOpen: false }" class="text-muted small mb-2">
                                    <span>@lang('jamasa.core::default.address.unverified')</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" x-on:click="abInfoOpen = !abInfoOpen" aria-label="Info">
                                        <i class="fas fa-circle-info"></i>
                                    </button>
                                    <div x-show="abInfoOpen" class="mt-1">@lang('jamasa.core::default.address.unverified_more')</div>
                                </div>
                                <button type="button" class="btn btn-link px-0 mb-2" x-on:click="abReset()">
                                    @lang('jamasa.core::default.address.search_again')
                                </button>
                            </div>
                        </template>

                        {{-- every rule-carrying field gets a display — a rejected save
                             must NEVER be invisible (bit us: country_id, 2026-07-24) --}}
                        <x-igniter-orange::forms.error field="form.address_1" class="text-danger"/>
                        <x-igniter-orange::forms.error field="form.postcode" class="text-danger"/>
                        <x-igniter-orange::forms.error field="form.city" class="text-danger"/>
                        <x-igniter-orange::forms.error field="form.country_id" class="text-danger"/>
                        <x-igniter-orange::forms.error field="form.state" class="text-danger"/>

                        <div class="form-group">
                            <div class="form-check py-2">
                                <input
                                    wire:model="form.is_default"
                                    wire:loading.attr="disabled"
                                    type="checkbox"
                                    id="isDefaultAddress"
                                    class="form-check-input"
                                    value="1"
                                />
                                <label
                                    class="form-check-label w-100"
                                    for="isDefaultAddress"
                                >@lang('igniter.user::default.text_set_default')</label>
                            </div>
                            <x-igniter-orange::forms.error field="form.is_default" class="text-danger"/>
                        </div>

                        <div class="d-flex justify-content-between">
                            @if($selectAddress)
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    x-bind:disabled="abBlocked"
                                >@lang('igniter.user::default.account.button_update')</button>
                                <button
                                    type="button"
                                    class="btn btn-light text-danger"
                                    wire:click="onDelete({{ $selectAddress->address_id }})"
                                    wire:loading.class="disabled"
                                >@lang('igniter.user::default.account.text_delete')</button>
                                <a
                                    class="btn btn-light"
                                    data-bs-dismiss="modal"
                                >@lang('igniter::admin.button_close')</a>
                            @else
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    x-bind:disabled="abBlocked"
                                >@lang('igniter.user::default.account.button_add')</button>
                                <a
                                    class="btn btn-light"
                                    data-bs-dismiss="modal"
                                >@lang('igniter::admin.button_close')</a>
                            @endif
                        </div>
                    </div>
                </x-igniter-orange::forms.form>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $(document).render(function () {
        $('#addressBookFormModal').modal('show');
        $('#addressBookFormModal').on('hidden.bs.modal', function () {
            $wire.$set('addressId', null);
        });

        Livewire.hook('morph.removing', ({ el, component }) => {
            if ($(el).is('#addressBookFormModal')) {
                $('#addressBookFormModal').modal('hide');
            }
        })

        Livewire.hook('morph.updated', ({ el, component }) => {
            if ($(el).is('#addressBookFormModal')) {
                $('#addressBookFormModal').modal('dispose');
                $('#addressBookFormModal').modal('show');
            }
        })
    });
</script>
@endscript

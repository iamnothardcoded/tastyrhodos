{{-- famedo override of igniter-orange::livewire.account-settings (forked from
     ti-theme-orange v4.1.3). Changes: the „Kennwort ändern" section is REMOVED —
     accounts sign in via e-mail code, there is no customer-known password (the
     stored one is random). Backend-safe: SettingsForm rules are `sometimes` /
     `required_with:old_password`, so absent password fields validate clean.
     Also fixes the vendor copy-paste bug (last_name error slot pointed at
     form.first_name). Email changes still log out → re-login via code. --}}
<div class="card">
    <div class="card-body">
        <h5 class="font-weight-normal mb-3">@lang('igniter.user::default.text_edit_details')</h5>
        <x-igniter-orange::forms.form wire:submit="onUpdate">
            <div class="form-row">
                <div class="col col-sm-6">
                    <div class="form-group">
                        <div @class(['form-floating'])>
                            <input
                                wire:model="form.first_name"
                                class="form-control"
                                name="first_name"
                            />
                            <label for="firstName">@lang('igniter.user::default.settings.label_first_name')</label>
                        </div>
                        <x-igniter-orange::forms.error field="form.first_name" class="text-danger"/>
                    </div>
                </div>
                <div class="col col-sm-6">
                    <div class="form-group">
                        <div @class(['form-floating'])>
                            <input
                                wire:model="form.last_name"
                                class="form-control"
                                name="last_name"
                            />
                            <label for="last_name">@lang('igniter.user::default.settings.label_last_name')</label>
                        </div>
                        <x-igniter-orange::forms.error field="form.last_name" class="text-danger"/>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="col col-sm-6">
                    <div class="form-group">
                        <x-igniter-orange::forms.telephone
                            id="input-telephone"
                            :number="$form->telephone"
                            field="form.telephone"
                            :label="lang('igniter.user::default.settings.label_telephone')"
                        />
                    </div>
                </div>
                <div class="col col-sm-6">
                    <div class="form-group">
                        {{-- Email is IMMUTABLE: it is the passwordless login identity.
                             Server-enforced in Jamasa\Core\Livewire\AccountSettings. --}}
                        <div @class(['form-floating'])>
                            <input
                                class="form-control"
                                type="email"
                                value="{{ $form->email }}"
                                disabled
                            />
                            <label for="email">@lang('igniter.user::default.settings.label_email')</label>
                        </div>
                        <div class="profile-note">Deine Anmelde-Adresse – kann nicht geändert werden.</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input
                        type="checkbox"
                        wire:model="form.newsletter"
                        id="newsletter"
                        class="form-check-input"
                        value="1"
                    />
                    <label for="newsletter" class="form-check-label">
                        @lang('igniter.user::default.settings.label_newsletter')
                    </label>
                </div>
                <x-igniter-orange::forms.error field="form.newsletter" class="text-danger"/>
            </div>

            {{-- no „Kennwort ändern": passwordless accounts (e-mail code) --}}
            <p class="text-muted" style="font-size:12.5px;margin:14px 0 0">
                Die Anmeldung erfolgt per E-Mail-Code – ein Passwort brauchst du nicht.
            </p>

            <div class="buttons">
                <button
                    type="submit"
                    class="btn btn-primary"
                >@lang('igniter.user::default.settings.button_save')</button>
            </div>
        </x-igniter-orange::forms.form>
    </div>
</div>

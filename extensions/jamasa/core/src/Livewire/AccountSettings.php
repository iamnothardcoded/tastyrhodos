<?php

declare(strict_types=1);

namespace Jamasa\Core\Livewire;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Main\Helpers\MainHelper;
use Igniter\Orange\Livewire\Forms\SettingsForm;
use Igniter\User\Facades\Auth;
use Livewire\Component;

/**
 * Famedo profile settings. STANDALONE mirror of the vendor component (which is
 * `final` and thus not extendable), reusing the vendor SettingsForm and the
 * famedo view override. Famedo rules baked in:
 *
 * 1. EMAIL IS IMMUTABLE — it is the passwordless login identity (email-code);
 *    changing it would need a re-verify-the-new-inbox flow. The view renders
 *    it disabled; here it is server-enforced (form value forced back to the
 *    account email before validation). Genuine changes = support/admin action.
 *
 * 2. PASSWORD FIELDS NEVER TOUCH THE CUSTOMER — accounts are passwordless;
 *    the profile form has no password section. (Also avoids the vendor's
 *    empty-string-through-the-hash-cast write.) No logout-on-change branch.
 *
 * 3. RETURN-TO-CHECKOUT — when opened from the checkout identity card
 *    (?return=checkout → mount prop, carried in the Livewire snapshot, no
 *    lingering session flags), a successful save redirects back to checkout
 *    so the order flow is never broken. Validation failures throw first and
 *    keep the user on the form, as usual.
 */
class AccountSettings extends Component
{
    public SettingsForm $form;

    public string $returnTo = '';

    public function mount(string $returnTo = ''): void
    {
        $this->returnTo = $returnTo;
        $customer = Auth::customer();
        $this->form->fillFrom($customer);
        // fillFrom (vendor) doesn't load newsletter → without this it renders
        // unticked and every save silently writes newsletter=0 (consent revoked).
        $this->form->newsletter = (bool)$customer?->newsletter;
    }

    public function render()
    {
        return view('igniter-orange::livewire.account-settings');
    }

    public function onUpdate()
    {
        throw_unless($customer = Auth::customer(),
            new ApplicationException('You must be logged in to manage your account'),
        );

        // Immutable login identity — regardless of what the request carries.
        $this->form->email = (string)$customer->email;

        $this->form->validate();

        // only() (allowlist), not except(): a denylist over a `final` vendor form
        // with $guarded=[] on Customer means a vendor-added property would silently
        // mass-assign onto the customer. This also keeps password fields out.
        $customer->fill($this->form->only([
            'first_name', 'last_name', 'telephone', 'newsletter',
        ]));
        $customer->save();

        flash()->success(lang('igniter.user::default.settings.alert_updated_success'));

        if ($this->returnTo === 'checkout') {
            return $this->redirect(MainHelper::pageUrl('checkout.checkout'));
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Jamasa\Core\Console;

use Igniter\System\Models\Language;
use Igniter\User\Models\User;
use Igniter\User\Models\UserGroup;
use Igniter\User\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Provision a restaurant OWNER account for the famedo owner console.
 *
 * Per-tenant, run once at onboarding (it can't live in new-tenant.sh with fixed
 * values — every owner has their own name/e-mail). It writes the DB directly,
 * entirely inside this extension, so it never touches upstream core (the fork
 * stays mergeable).
 *
 * What it creates, idempotently:
 *   - A dedicated, PERMISSION-LESS admin role ("Restaurant-Inhaber"). The owner
 *     never uses the TastyIgniter admin UI — the console talks only to the
 *     owner API — but should they ever reach the admin, an empty permission set
 *     means TI's nav filtering hides everything.
 *   - A NON-superuser admin user (their e-mail is the login username), assigned
 *     that role, activated, language-pinned. This user's credentials are what
 *     the owner console's login exchanges for a short-lived `owner`-scoped token.
 *
 * Re-running with the same --email updates the name/role (no duplicate); the
 * password is only set on creation, or when --reset-password is passed.
 */
class CreateOwner extends Command
{
    protected $signature = 'famedo:create-owner
        {--email= : Owner login e-mail (becomes their username)}
        {--name= : Display name (restaurant or owner name)}
        {--password= : Login password; omit to auto-generate and print one}
        {--role=Restaurant-Inhaber : Name of the locked-down owner role}
        {--reset-password : Reset the password even if the user already exists}';

    protected $description = 'Create/update a locked-down owner account for the famedo owner console.';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Owner login e-mail'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --email is required.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Display name', 'Restaurant-Inhaber'));

        $role = $this->ensureOwnerRole((string) $this->option('role'));

        $user = User::query()->where('email', $email)->first();
        $isNew = $user === null;
        $user ??= new User;

        // Password: set on create, or when explicitly resetting. Otherwise keep.
        $password = null;
        if ($isNew || $this->option('reset-password')) {
            $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));
            $user->password = $password;   // model hashes on assignment
        }

        $user->name = $name;
        $user->email = $email;
        if ($isNew || empty($user->username)) {
            $user->username = $this->uniqueUsername($email);
        }
        $user->super_user = 0;
        $user->user_role_id = $role->getKey();
        $user->status = 1;
        $user->is_activated = 1;

        // Pin language so a stray TI-admin visit is English (owner-facing UI is
        // the console, which is German regardless). Mirrors admin-user pinning.
        if (Schema::hasColumn('admin_users', 'language_id') && !$user->language_id) {
            $user->language_id = optional(Language::query()->where('code', 'en')->first())->getKey();
        }

        $user->save();

        // Harmless group membership (TI uses groups for order-assignment routing).
        if ($group = UserGroup::query()->first()) {
            $user->groups()->syncWithoutDetaching([$group->getKey()]);
        }

        $this->report($user, $role, $isNew, $password);

        return self::SUCCESS;
    }

    protected function ensureOwnerRole(string $name): UserRole
    {
        $role = UserRole::query()->where('name', $name)->first() ?? new UserRole;
        $role->name = $name;
        if (Schema::hasColumn('admin_user_roles', 'code') && empty($role->code)) {
            $role->code = 'owner';
        }
        $role->permissions = [];   // no admin-panel permissions
        $role->save();

        return $role;
    }

    /** email local-part as a handle, suffixed if another user already has it. */
    protected function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'owner';
        $candidate = $base;
        $n = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.'-'.(++$n);
        }

        return $candidate;
    }

    protected function report(User $user, UserRole $role, bool $isNew, ?string $password): void
    {
        $url = rtrim((string) config('app.url'), '/').'/staff-pos/owner-console.html';

        $this->newLine();
        $this->info($isNew ? '✓ Owner account created.' : '✓ Owner account updated.');
        $this->line('  Console : '.$url);
        $this->line('  E-Mail  : '.$user->email);
        if ($password !== null) {
            $this->line('  Passwort: '.$password.'   ← hand this to the owner, then it is not shown again');
        } else {
            $this->line('  Passwort: (unchanged — pass --reset-password to set a new one)');
        }
        $this->line('  Rolle   : '.$role->name.' (keine Admin-Rechte, super_user=0)');
        $this->newLine();
    }
}

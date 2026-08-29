<?php

declare(strict_types=1);

namespace Jamasa\Core\Payments;

use Igniter\PayRegister\Payments\Mollie as BaseMollie;

/**
 * Deliberately EMPTY subclass — do not delete this class.
 *
 * It once carried the processReturnUrl fix for the two upstream bugs that sent
 * every successfully paying customer back to the checkout page. Upstream took
 * the fix verbatim (ti-ext-payregister#62, released in v4.2.0 — verified in
 * the installed vendor 2026-08-29), so the logic lives upstream now.
 *
 * The class itself must stay: live tenant DB rows bind it by name —
 * elgrecomarl's payments row has `payments.class_name = Jamasa\Core\Payments\Mollie`
 * (status=1). Deleting the class would kill that tenant's Mollie checkout at
 * its next image bump. Fresh installs also seed this name via the
 * registerPaymentGateways() override in Extension.php.
 */
class Mollie extends BaseMollie
{
}

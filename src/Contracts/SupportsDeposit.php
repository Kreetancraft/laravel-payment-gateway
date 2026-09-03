<?php

namespace Kreetancraft\PaymentGateway\Contracts;

/**
 * Something that can be paid for in two goes.
 *
 * A booking is commonly secured with a deposit and settled later, so the buyer
 * is choosing between two amounts the *server* computed — not typing one. That
 * distinction is the whole reason `Payable` exists, and it holds here: the
 * request may pick which of the two, never what either is worth.
 *
 * Separate from `Payable` on purpose. Most things sold are paid once, and adding
 * a method to `Payable` would break every host that already implements it for a
 * feature they do not want. Implement this as well when a payable takes a
 * deposit; implement nothing extra when it does not, and asking for a deposit is
 * simply refused.
 */
interface SupportsDeposit
{
    /**
     * The full deposit, in minor units — not what is left of it.
     *
     * How much of it has already been paid is this package's business, since it
     * is the one holding the payment records. Return the whole figure and let it
     * do the subtraction, or a payable that forgets will ask for the deposit
     * twice.
     */
    public function paymentDepositCents(): int;
}

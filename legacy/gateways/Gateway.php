<?php

namespace craft\commerce\paypalcheckout\gateways;

// Gateways saved with PayPal Checkout for Craft Commerce 3 store this class name as their type.
/** @deprecated in 4.0.0. Use {@see \CraftCms\CommercePayPalCheckout\Gateway} instead. */
class_alias(\CraftCms\CommercePayPalCheckout\Gateway::class, 'craft\commerce\paypalcheckout\gateways\Gateway');

/** @phpstan-ignore-next-line */
if (false) {
    class Gateway extends \CraftCms\CommercePayPalCheckout\Gateway
    {
    }
}

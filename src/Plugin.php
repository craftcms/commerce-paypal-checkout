<?php

declare(strict_types=1);

namespace CraftCms\CommercePayPalCheckout;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Commerce\Payment\Gateway\GatewayTypes;

/**
 * PayPal Checkout for Craft Commerce.
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0';

    public string $minVersionRequired = '3.1.0';

    public function boot(GatewayTypes $gatewayTypes): void
    {
        $gatewayTypes->register(Gateway::class);
    }
}

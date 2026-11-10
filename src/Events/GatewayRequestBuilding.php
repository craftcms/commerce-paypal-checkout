<?php

declare(strict_types=1);

namespace CraftCms\CommercePayPalCheckout\Events;

use CraftCms\Commerce\Payment\Data\Transaction;

/**
 * Dispatched before a request is sent to PayPal on behalf of a transaction. Listeners may modify `$request`, which is
 * sent as the request body.
 *
 * - When `$type` is `purchase`, `$request` is the body of the
 *   [Create order](https://developer.paypal.com/docs/api/orders/v2/#orders_create) request. It is also `purchase` when
 *   the gateway’s payment type is “Authorize Only”; `$request['intent']` is `AUTHORIZE` then.
 * - When `$type` is `refund`, `$request` is the body of the
 *   [Refund captured payment](https://developer.paypal.com/docs/api/payments/v2/#captures_refund) request.
 *
 * Changes to the transaction are ignored. Changing the amounts so the payment is less than the transaction amount
 * won’t complete the order.
 */
class GatewayRequestBuilding
{
    /**
     * @param array<string, mixed> $request
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $type,
        public array $request,
    ) {
    }
}

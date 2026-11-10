# Upgrading to PayPal Checkout for Craft Commerce 4.0 (Commerce 6)

PayPal Checkout for Craft Commerce 4.0 supports Craft Commerce 6. It talks to the PayPal REST API ([Orders v2](https://developer.paypal.com/docs/api/orders/v2/) and [Payments v2](https://developer.paypal.com/docs/api/payments/v2/)) directly, rather than through PayPal’s Checkout PHP SDK, which PayPal has deprecated and archived.

## Requirements

- Craft CMS 6.0 and Craft Commerce 6.0 or later
- PHP 8.5 or later
- `craftcms/yii2-adapter` is not needed.

## Upgrade steps

1. Update to PayPal Checkout for Craft Commerce 3.1.0 and Craft Commerce 5 first.
2. Upgrade your project to Craft 6 and Craft Commerce 6.
3. Update the plugin: `composer require craftcms/commerce-paypal-checkout:^4.0`.
4. Review the breaking changes below.

Existing PayPal Checkout gateways keep working: gateways saved with the old `craft\commerce\paypalcheckout\gateways\Gateway` type load as `CraftCms\CommercePayPalCheckout\Gateway`, and save with the new class name the next time they’re saved. Transactions made before the upgrade can still be captured and refunded.

## Breaking changes

### PayPal Checkout SDK

The plugin no longer depends on `paypal/paypal-checkout-sdk` or `paypal/paypalhttp`. If your project uses those classes directly, require the packages yourself.

`Gateway::createClient()` now returns a `CraftCms\CommercePayPalCheckout\Client` instead of a `PayPalCheckoutSdk\Core\PayPalHttpClient`. The client has a method for each request the gateway makes (`createOrder()`, `getOrder()`, `authorizeOrder()`, `captureOrder()`, `captureAuthorization()`, `refundCapture()`), and caches PayPal access tokens with Laravel’s cache until they expire.

Responses are stored on transactions with the same `statusCode`, `result` and `headers` keys as before, so code that reads `transaction.response` keeps working. `Gateway::getResponseModel()` and `Gateway::getRefundResponseModel()` now take that array rather than a `PayPalHttp\HttpResponse`.

### Events

`Gateway::EVENT_BUILD_GATEWAY_REQUEST` and `BuildGatewayRequestEvent` are replaced by the `CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding` Laravel event. It has the same `transaction`, `type` (`purchase` or `refund`) and `request` properties.

| Before | After |
| --- | --- |
| `craft\commerce\paypalcheckout\gateways\Gateway::EVENT_BUILD_GATEWAY_REQUEST` | `CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding` |
| `craft\commerce\paypalcheckout\events\BuildGatewayRequestEvent` | `CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding` |

Before:

```php
use craft\commerce\paypalcheckout\events\BuildGatewayRequestEvent;
use craft\commerce\paypalcheckout\gateways\Gateway;
use yii\base\Event;

Event::on(Gateway::class, Gateway::EVENT_BUILD_GATEWAY_REQUEST, function(BuildGatewayRequestEvent $event) {
    if ($event->type === 'purchase') {
        $event->request['purchase_units'][0]['description'] = 'My store';
    }
});
```

After:

```php
use CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding;
use Illuminate\Support\Facades\Event;

Event::listen(function(GatewayRequestBuilding $event): void {
    if ($event->type === 'purchase') {
        $event->request['purchase_units'][0]['description'] = 'My store';
    }
});
```

### Classes and namespaces

| Before | After |
| --- | --- |
| `craft\commerce\paypalcheckout\Plugin` | `CraftCms\CommercePayPalCheckout\Plugin` |
| `craft\commerce\paypalcheckout\gateways\Gateway` | `CraftCms\CommercePayPalCheckout\Gateway` (the old name is kept as an alias) |
| `craft\commerce\paypalcheckout\responses\CheckoutResponse` | `CraftCms\CommercePayPalCheckout\Responses\CheckoutResponse` |
| `craft\commerce\paypalcheckout\responses\RefundResponse` | `CraftCms\CommercePayPalCheckout\Responses\RefundResponse` |
| `craft\commerce\paypalcheckout\events\BuildGatewayRequestEvent` | `CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding` |
| `craft\commerce\paypalcheckout\injectors\PayPalAuthorizationInjector` | Removed. `CraftCms\CommercePayPalCheckout\Client` fetches and caches access tokens. |
| `craft\commerce\paypalcheckout\PayPalCheckoutBundle` | Removed. The payment form registers its script itself. |

### Settings

The settings are unchanged, and can still be set to environment variables. They are now public properties on the gateway (`clientId`, `secret`, `brandName`, `landingPage`, `sendCartInfo`, `sendShippingInfo`, `testMode`), and the `getX(bool $parse = true)` getters still return the parsed or raw values.

The gateway now requires a **Client ID** and **Secret** to be saved.

### Templates and Twig

`cart.gateway.getPaymentFormHtml({...})` still renders the PayPal Buttons, and still accepts the `currency`, `disable-card`, `disable-funding`, `enable-funding`, `locale` and `components` params.

The payment form is now built by `Gateway::paymentFormUi()` instead of the `commerce-paypal-checkout/paymentForm` template, so overriding that template no longer has any effect. To change the markup, extend the gateway and override `paymentFormUi()`. The unused `data-prepare` attribute was removed from the `.paypal-rest-form` element.

The `commerce-paypal-checkout/settings` template was removed. The gateway settings are built by `Gateway::settingsUi()`.

The plugin no longer loads a `fetch`/`Promise` polyfill for Internet Explorer.

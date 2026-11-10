# Release Notes for PayPal Checkout for Craft Commerce

## Unreleased

> [!WARNING]
> This release requires Craft Commerce 6. See [UPGRADE.md](UPGRADE.md) for the breaking changes.

- PayPal Checkout for Craft Commerce now requires Craft CMS 6, Craft Commerce 6 and PHP 8.5.
- The plugin now uses the PayPal REST API directly, and no longer requires `paypal/paypal-checkout-sdk`, which PayPal has deprecated.
- Moved all classes to the `CraftCms\CommercePayPalCheckout` namespace. `craft\commerce\paypalcheckout\gateways\Gateway` is kept as an alias of `CraftCms\CommercePayPalCheckout\Gateway`.
- Added `CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding`, which replaces `Gateway::EVENT_BUILD_GATEWAY_REQUEST` and `BuildGatewayRequestEvent`.
- The payment form is now built by `Gateway::paymentFormUi()`, and the `commerce-paypal-checkout/paymentForm` template was removed.
- Amounts sent to PayPal now always have the currency’s number of decimal places (for example, `12.30` rather than `12.3`).
- The gateway now requires a client ID and secret.
- Fixed a bug where the payment form showed PayPal’s raw error response instead of its error description.
- Removed the Internet Explorer `fetch`/`Promise` polyfill from the payment form.

## 3.1.0 - 2026-03-17

- It is now possible to provide the `components` parameter for the PayPal JS SDK. ([#95](https://github.com/craftcms/commerce-paypal-checkout/issues/95))
- Fixed a PHP error that occurred when parsing environment variables for boolean gateway settings.

## 3.0.4 - 2025-10-14

- Fixed a bug where declined payments weren’t being marked as failed. ([#94](https://github.com/craftcms/commerce-paypal-checkout/pull/94))

## 3.0.3 - 2025-02-06

- Fixed a bug where refunds could be incorrectly marked as failed. ([#82](https://github.com/craftcms/commerce-paypal-checkout/issues/82))

## 3.0.2 - 2023-02-03

- Added `craft\commerce\paypalcheckout\gateways\Gateway::EVENT_BUILD_GATEWAY_REQUEST`. ([#89](https://github.com/craftcms/commerce-paypal-checkout/issues/89))
- Added `craft\commerce\paypalcheckout\events\BuildGatewayRequestEvent`. ([#89](https://github.com/craftcms/commerce-paypal-checkout/issues/89))

## 3.0.1 - 2023-06-27 [CRITICAL]

- Added `craft\commerce\paypalcheckout\gateways\Gateway::showPaymentFormSubmitButton()`.
- Fixed a supply chain security vulnerability.

## 3.0.0 - 2024-03-20

- Added Craft CMS 5 and Craft Commerce 5 compatibility.

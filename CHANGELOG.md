# Release Notes for PayPal Checkout for Craft Commerce

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

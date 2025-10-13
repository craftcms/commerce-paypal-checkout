# Release Notes for PayPal Checkout for Craft Commerce

## Unreleased

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

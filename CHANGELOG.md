# Release Notes for PayPal Checkout for Craft Commerce

## 2.1.0.1 - 2022-07-21

### Fixed
- Fixed a PHP error that occurred when receiving and error from PayPal. ([#62](https://github.com/craftcms/commerce-paypal-checkout/issues/62))
- Fixed a bug where names were being passed in correctly to the API.

## 2.1.0 - 2022-07-13

### Added
- Added the `sendShippingInfo` setting to allow control over sending shipping information to PayPal. ([#27](https://github.com/craftcms/commerce-paypal-checkout/issues/27))
- Added `craft\commerce\paypalcheckout\gateways\Gateway::getSendShippingInfo()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::setSendShippingInfo()`.

## 2.0.0 - 2022-05-04

### Added
- Added Craft CMS 4 and Craft Commerce 4 compatibility.
- All gateway settings now support environment variables.

## 1.3.2.1 - 2022-02-24

### Fixed
- Fixed an error that could occur when trying to refund an order. ([#49](https://github.com/craftcms/commerce-paypal-checkout/issues/49))

## 1.3.2 - 2022-02-24

### Fixed
- Fixed an error that could occur when no billing address was provided. ([#48](https://github.com/craftcms/commerce-paypal-checkout/issues/48))
- Fixed a bug where errors weren’t being returned from the API. ([#51](https://github.com/craftcms/commerce-paypal-checkout/issues/51))

## 1.3.1 - 2021-12-07

### Added
- Added `craft\commerce\paypalcheckout\gateways\Gateway::getClientId()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::setClientId()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::getSecret()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::setSecret()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::getSendCartInfo()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::setSendCartInfo()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::getTestMode()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::setTestMode()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::getLandingLage()`.
- Added `craft\commerce\paypalcheckout\gateways\Gateway::setLandingLage()`.

### Fixed
- Fixed a bug where “Send Gateway Info”, “Test mode?” and “Landing Page” gateway settings were not being parsed as environment variables.

## 1.3.0 - 2021-12-01

### Changed
- The plugin now requires Craft 3.7.22 or later.
- The “Test mode?” and “Landing Page” gateway settings now support environment variables.

## 1.2.2.2 - 2021-07-15

### Fixed
- Fixed a bug that could occur with previous versions of Craft. ([#40](https://github.com/craftcms/commerce-paypal-checkout/issues/40))

## 1.2.2.1 - 2021-07-15

### Fixed
- Fixed a PHP error that could occur if the `siteName` param in the general config was `null`. ([#40](https://github.com/craftcms/commerce-paypal-checkout/issues/40))

## 1.2.2 - 2021-07-06

### Fixed
- Fixed a bug where data submitted to the PayPal API was invalid. ([#39](https://github.com/craftcms/commerce-paypal-checkout/issues/39))

## 1.2.1 - 2021-06-10

### Added
- Added the `locale` parameter for when customizing the PayPal SDK script. ([#123](https://github.com/craftcms/commerce-paypal-checkout/pull/35))

### Fixed
- Fixed a bug where Commerce payment errors weren’t being returned
- Fixed a bug where the PayPal Checkout gateway was incorrectly showing in the available gateways list for CP payments. ([#30](https://github.com/craftcms/commerce-paypal-checkout/issues/30))
- Fixed a bug where the order number wasn’t being sent to PayPal.

## 1.2.0 - 2020-12-04

### Removed
- Removed `sendTotalsBreakdown` in favour of using `sendCartInfo` setting. ([#25](https://github.com/craftcms/commerce-paypal-checkout/issues/25))

## 1.1.3 - 2020-12-03

### Added
- Added `sendTotalsBreakdown` setting to allow the option not to send a breakdown. ([#25](https://github.com/craftcms/commerce-paypal-checkout/issues/25))

## 1.1.2 - 2020-10-13

### Changed
- Improved redirection after payment has been approved.

### Fixed
- Fixed a bug that occurred when using the PayPal Checkout gateway with the latest example templates.

## 1.1.1 - 2020-07-07

### Fixed
- Fixed a bug that caused state value not to be populated when passing payer details to PayPal. ([#20](https://github.com/craftcms/commerce-paypal-checkout/issues/20))

## 1.1.0 - 2020-04-14

### Added
- Added the ability to pay in a currency other than the primary payment currency.
- Added the ability to set `disable-funding` and `disable-card` PayPal SDK query parameters. ([#14](https://github.com/craftcms/commerce-paypal-checkout/issues/14))
- Billing address details are now passed to PayPal via the `payer` object. ([#11](https://github.com/craftcms/commerce-paypal-checkout/issues/11))

### Fixed
- Fixed a JavaScript error that occurred when setting authorize payment type. ([#13](https://github.com/craftcms/commerce-paypal-checkout/issues/13))

## 1.0.5 - 2020-03-23

### Added
- Added support for payment of outstanding balance on an order. ([#9](https://github.com/craftcms/commerce-paypal-checkout/issues/9))

## 1.0.4 - 2020-02-27

### Fixed
- Fixed an issue where JavaScript would cause float values to be malformed.

## 1.0.3 - 2020-02-26

### Added
- Added the ability to checkout without a shipping address.

### Fixed
- Fixed PHP 7.0 compatibility
- Fixed a JavaScript error that could occur if both PayPal gateways were installed.

## 1.0.2 - 2020-02-16

### Fixed
- Fixed a bug that could occur if the sale price of a line item was not rounded.
- Fixed a bug that could occur if there was no shipping method.

## 1.0.1 - 2019-12-17

### Fixed
- Fixed support for environment variables in gateway settings. ([#7](https://github.com/craftcms/commerce-paypal-checkout/issues/7))

## 1.0.0 - 2019-12-13

- Initial release.

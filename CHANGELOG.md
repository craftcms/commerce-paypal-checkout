# Release Notes for PayPal Checkout for Craft Commerce

## Unreleased

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
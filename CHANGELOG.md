# Release Notes for PayPal Checkout for Craft Commerce

## Unreleased

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
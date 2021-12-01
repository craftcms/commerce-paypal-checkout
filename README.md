<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="PayPal Checkout for Craft Commerce icon"></p>
<h1 align="center">PayPal Checkout for Craft Commerce</h1>

This plugin provides a [PayPal Checkout](https://www.paypal.com/uk/webapps/mpp/checkout) gateway integration for [Craft Commerce](https://craftcms.com/commerce).

## Requirements

This plugin requires Craft 3.7.22 and Craft Commerce 2.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “PayPal Checkout for Craft Commerce”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/commerce-paypal-checkout

# tell Craft to install the plugin
./craft plugin/install commerce-paypal-checkout
```

## Setup

### Creating PayPal REST API credentials

The following steps are from the [PayPal guide](https://www.paypal.com/us/smarthelp/article/how-do-i-create-rest-api-credentials-ts1949) on how to create REST API credentials. 

REST API credentials include a client ID and secret. Here's how you generate the credentials:

1. Log in to the [PayPal Developer Portal](https://developer.paypal.com/) using the same credentials you use for [PayPal](https://paypal.com/).
1. On the **My Apps & Credentials** page, click **Live** or **Sandbox** depending on whether you need an app for testing (Sandbox) or going live (Live).
1. Click **Create App** under **REST API** apps. Any previously created REST API apps will appear in the table above the **Create App** button.
1. Enter the name of your REST API app in the **App Name** field, and select a Sandbox business account to associate with your app.
**Note:** Remember that you can't use a Live credit card in Sandbox, and you can't use a test credit card in your Live account.
1. Click **Create App**.
1. Your credentials, the client ID and secret, are displayed on the app details page that displays after you click **Create App**.
1. Request permissions for REST API features that apply to your integration:
    - PayPal payments

You will now be able to see the **client ID** and **secret** for your newly created app.

When you are ready to take your code live, make sure you create a Live app to get live credentials.

Use the Live and Sandbox toggle at the top of My Apps & Credentials to switch between app types and view your credentials for each.

### Creating PayPal Checkout gateway in Commerce

To add the PayPal Checkout gateway, go to Commerce → Settings → Gateways, create a new gateway, and set the gateway type to “PayPal Checkout”.

In the gateway settings enter the **client ID** and **secret** for your rest app in their respective fields.

### Cart Info / Cost breakdown

Turning on the "Send Cart Information" setting for this gateway, the gateway will send cart items and cost breakdown through to PayPal.

**Note:** If you are using deprecated custom adjuster types this setting will need be turned off.

### Paying with non-primary currency

When using the `getPaymentFormHtml()` method (e.g. `cart.gateway.getPaymentFormHtml({})) and allowing payment in a currency other than the primary currency.

You must pass the currency ISO in the method params. For example if you have already set the alternative payment currency on the cart you could do the following:

```twig
{{ cart.gateway.getPaymentFormHtml({
  currency: cart.paymentCurrency
}) }}
```

This is required when paying with an alternative payment currency due to the integration of the PayPal JavaScript SDK.

### SDK query string parameters

This gateway allows the passing of certain query string parameters, at runtime, as specified in the [PayPal documentation](https://developer.paypal.com/docs/checkout/reference/customize-sdk/#query-parameters).

The allowed parameters are `currency` (as detailed above), `disable-card`, `disable-funding` and `locale`.

As an example to disable PayPal credit funding you could output the payment form as follows:

```twig
{{ cart.gateway.getPaymentFormHtml({
  ...
  'disable-funding': 'credit'
  ...
}) }}
```

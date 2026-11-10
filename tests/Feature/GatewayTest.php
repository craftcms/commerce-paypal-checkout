<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Exceptions\PaymentException;
use CraftCms\Commerce\Payment\Forms\OffsitePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\GatewayTypes;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionModel;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding;
use CraftCms\CommercePayPalCheckout\Gateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const SANDBOX = 'https://api-m.sandbox.paypal.com';

function paypalGateway(array $settings = [], string $paymentType = 'purchase'): Gateway
{
    /** @var Gateway $gateway */
    $gateway = app(Gateways::class)->createGateway([
        'type' => Gateway::class,
        'name' => 'PayPal',
        'handle' => 'paypal',
        'paymentType' => $paymentType,
        'settings' => array_merge([
            'clientId' => 'client-id',
            'secret' => 'client-secret',
            'testMode' => true,
            'sendCartInfo' => false,
            'sendShippingInfo' => true,
        ], $settings),
    ]);

    return $gateway;
}

/**
 * Saves a gateway and an order, and returns an unsaved transaction for it.
 */
function paypalTransaction(float $amount = 12.3, array $settings = [], string $type = TransactionModel::TYPE_PURCHASE): Transaction
{
    $gateway = paypalGateway($settings, $type);
    app(Gateways::class)->saveGateway($gateway);

    $order = new Order();
    $order->number = '9e5c1b8a7f2d4c6b8a0e1f3d5c7b9a2e';
    $order->email = 'customer@example.com';
    $order->gatewayId = $gateway->id;
    $order->returnUrl = 'shop/thanks';
    $order->cancelUrl = 'shop/checkout';
    Elements::saveElement($order);

    $transaction = app(Transactions::class)->createTransaction($order, null, $type);
    $transaction->paymentAmount = $amount;
    $transaction->amount = $amount;

    return $transaction;
}

function paypalToken(): array
{
    return ['access_token' => 'A21AAtoken', 'token_type' => 'Bearer', 'expires_in' => 32400];
}

/**
 * An Orders v2 order, as returned with `Prefer: return=representation`.
 */
function paypalOrder(string $status = 'CREATED', array $payments = []): array
{
    $order = [
        'id' => '5O190127TN364715T',
        'status' => $status,
        'purchase_units' => [['reference_id' => 'default', 'amount' => ['currency_code' => 'USD', 'value' => '12.30']]],
    ];

    if ($payments) {
        $order['purchase_units'][0]['payments'] = $payments;
    }

    return $order;
}

function paypalError(string $name, string $message, string $issue, string $description): array
{
    return [
        'name' => $name,
        'message' => $message,
        'debug_id' => 'f2d8a7c1b2e3',
        'details' => [['issue' => $issue, 'description' => $description]],
    ];
}

it('registers the gateway type', function() {
    expect(app(GatewayTypes::class)->types()->all())->toContain(Gateway::class);
});

it('loads gateways saved with the Craft Commerce 5 class name', function() {
    $gateway = app(Gateways::class)->createGateway([
        'type' => 'craft\\commerce\\paypalcheckout\\gateways\\Gateway',
        'settings' => [
            'clientId' => '$PAYPAL_CLIENT_ID',
            'secret' => '$PAYPAL_SECRET',
            'brandName' => 'Acme',
            'landingPage' => 'LOGIN',
            'sendCartInfo' => '1',
            'sendShippingInfo' => '$PAYPAL_SEND_SHIPPING',
            'testMode' => '$PAYPAL_TEST_MODE',
        ],
    ]);

    expect($gateway)->toBeInstanceOf(Gateway::class)
        ->and($gateway->getConfig()['type'])->toBe(Gateway::class)
        ->and($gateway->getClientId(false))->toBe('$PAYPAL_CLIENT_ID')
        ->and($gateway->getSecret(false))->toBe('$PAYPAL_SECRET')
        ->and($gateway->getLandingPage())->toBe('LOGIN')
        ->and($gateway->getSendShippingInfo(false))->toBe('$PAYPAL_SEND_SHIPPING')
        ->and(array_keys($gateway->getSettings()))->toEqualCanonicalizing(['clientId', 'secret', 'brandName', 'landingPage', 'sendCartInfo', 'sendShippingInfo', 'testMode']);
});

it('parses environment variables in settings', function() {
    putenv('PAYPAL_TEST_CLIENT_ID=env-client');
    putenv('PAYPAL_TEST_SANDBOX=false');
    $_SERVER['PAYPAL_TEST_CLIENT_ID'] = 'env-client';
    $_SERVER['PAYPAL_TEST_SANDBOX'] = 'false';

    $gateway = paypalGateway(['clientId' => '$PAYPAL_TEST_CLIENT_ID', 'testMode' => '$PAYPAL_TEST_SANDBOX']);

    expect($gateway->getClientId())->toBe('env-client')
        ->and($gateway->getTestMode())->toBeFalse()
        ->and($gateway->createClient()->baseUrl())->toBe('https://api-m.paypal.com');
});

it('requires a client ID, a secret and a supported payment type', function() {
    $gateway = paypalGateway(['clientId' => '', 'secret' => null]);
    $gateway->paymentType = 'subscribe';

    expect($gateway->validate())->toBeFalse()
        ->and($gateway->errors()->keys())->toContain('clientId', 'secret', 'paymentType');

    $gateway = paypalGateway(paymentType: 'authorize');
    expect($gateway->validate())->toBeTrue();
});

it('renders the settings', function() {
    $html = paypalGateway(['clientId' => '$PAYPAL_CLIENT_ID', 'brandName' => 'Acme Store'])->getSettingsHtml();

    expect($html)
        ->toContain('clientId')
        ->toContain('$PAYPAL_CLIENT_ID')
        ->toContain('secret')
        ->toContain('landingPage')
        ->toContain('Acme Store')
        ->toContain('sendCartInfo')
        ->toContain('sendShippingInfo')
        ->toContain('testMode');
});

it('creates a PayPal order with the purchase details', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction(12.3, ['brandName' => 'Acme', 'landingPage' => 'BILLING']);

    $response = $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    expect($response->isRedirect())->toBeTrue()
        ->and($response->isSuccessful())->toBeFalse()
        ->and($response->getRedirectMethod())->toBe('GET')
        ->and($response->getTransactionReference())->toBe('5O190127TN364715T')
        ->and($response->getRedirectUrl())->toBe('5O190127TN364715T')
        ->and($response->getCode())->toBe('201');

    Http::assertSent(function(Request $request) {
        return $request->url() === SANDBOX . '/v1/oauth2/token'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('client-id:client-secret'))
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request['grant_type'] === 'client_credentials';
    });

    Http::assertSent(function(Request $request) use ($transaction) {
        if ($request->url() !== SANDBOX . '/v2/checkout/orders') {
            return false;
        }

        $unit = $request['purchase_units'][0];

        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer A21AAtoken')
            && $request->hasHeader('Prefer', 'return=representation')
            && !$request->hasHeader('PayPal-Request-Id')
            && $request['intent'] === 'CAPTURE'
            && $request['payer'] === ['email_address' => 'customer@example.com']
            && $unit['amount'] === ['currency_code' => 'USD', 'value' => '12.30']
            && $unit['items'] === []
            && $unit['invoice_id'] === '9e5c1b8a7f2d4c6b8a0e1f3d5c7b9a2e'
            && $unit['custom_id'] === $transaction->hash
            && !isset($unit['shipping'])
            && $request['application_context']['brand_name'] === 'Acme'
            && $request['application_context']['landing_page'] === 'BILLING'
            && $request['application_context']['locale'] === I18N::getLocale()->id
            && $request['application_context']['shipping_preference'] === 'NO_SHIPPING'
            && $request['application_context']['user_action'] === 'PAY_NOW'
            && str_ends_with($request['application_context']['return_url'], 'shop/thanks')
            && str_ends_with($request['application_context']['cancel_url'], 'shop/checkout');
    });
});

it('uses the AUTHORIZE intent for authorize-only gateways', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction(type: TransactionModel::TYPE_AUTHORIZE);

    $response = $transaction->getGateway()->authorize($transaction, new OffsitePaymentForm());

    expect($response->isRedirect())->toBeTrue();
    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders') && $request['intent'] === 'AUTHORIZE');
});

it('formats amounts in the currency’s minor units', function(float $amount, string $currency, string $expected) {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction($amount);
    $transaction->paymentCurrency = $currency;

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && $request['purchase_units'][0]['amount'] === ['currency_code' => $currency, 'value' => $expected]);
})->with([
    'cents' => [12.3, 'USD', '12.30'],
    'whole amount' => [10.0, 'USD', '10.00'],
    'zero-decimal currency' => [1500.0, 'JPY', '1500'],
]);

it('caches the access token until it expires', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction();

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());
    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    expect(Http::recorded(fn(Request $request) => str_ends_with($request->url(), '/v1/oauth2/token')))->toHaveCount(1)
        ->and(Http::recorded(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders')))->toHaveCount(2);
});

it('uses the live API outside test mode', function() {
    Http::fake([
        'https://api-m.paypal.com/v1/oauth2/token' => Http::response(paypalToken()),
        'https://api-m.paypal.com/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction(settings: ['testMode' => false]);

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    Http::assertSentCount(2);
    Http::assertNotSent(fn(Request $request) => str_contains($request->url(), 'sandbox'));
});

it('sends the cart breakdown and items when “Send cart information” is on', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction(0.0, ['sendCartInfo' => '1']);

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && $request['purchase_units'][0]['amount'] === [
            'currency_code' => 'USD',
            'value' => '0.00',
            'breakdown' => [
                'item_total' => ['currency_code' => 'USD', 'value' => '0.00'],
                'shipping' => ['currency_code' => 'USD', 'value' => '0.00'],
                'tax_total' => ['currency_code' => 'USD', 'value' => '0.00'],
            ],
        ]);
});

it('doesn’t send the cart breakdown when paying in another currency', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction(10, ['sendCartInfo' => true]);
    $transaction->paymentCurrency = 'EUR';

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && !isset($request['purchase_units'][0]['amount']['breakdown'])
        && $request['purchase_units'][0]['items'] === []);
});

it('sends the payer and shipping addresses', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction();
    $address = [
        'firstName' => 'Jenny',
        'lastName' => 'Andrews',
        'addressLine1' => '1 Infinite Loop',
        'addressLine2' => 'Suite 2',
        'locality' => 'Cupertino',
        'administrativeArea' => 'CA',
        'postalCode' => '95014',
        'countryCode' => 'US',
    ];
    $transaction->getOrder()->setBillingAddress($address);
    $transaction->getOrder()->setShippingAddress($address);

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    $paypalAddress = [
        'address_line_1' => '1 Infinite Loop',
        'address_line_2' => 'Suite 2',
        'admin_area_2' => 'Cupertino',
        'admin_area_1' => 'CA',
        'postal_code' => '95014',
        'country_code' => 'US',
    ];

    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && $request['payer'] === [
            'email_address' => 'customer@example.com',
            'name' => ['given_name' => 'Jenny', 'surname' => 'Andrews'],
            'address' => $paypalAddress,
        ]
        && $request['purchase_units'][0]['shipping'] === [
            'address' => $paypalAddress,
            'name' => ['full_name' => 'Jenny Andrews'],
        ]
        && $request['application_context']['shipping_preference'] === 'SET_PROVIDED_ADDRESS');
});

it('doesn’t send the shipping address when “Send shipping information” is off', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    $transaction = paypalTransaction(settings: ['sendShippingInfo' => false]);
    $transaction->getOrder()->setShippingAddress(['addressLine1' => '1 Infinite Loop', 'countryCode' => 'US']);

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && !isset($request['purchase_units'][0]['shipping'])
        && $request['application_context']['shipping_preference'] === 'NO_SHIPPING');
});

it('lets listeners modify the create order request', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response(paypalOrder(), 201),
    ]);
    Event::listen(function(GatewayRequestBuilding $event) {
        if ($event->type === 'purchase') {
            $event->request['purchase_units'][0]['description'] = 'Custom';
        }
    });
    $transaction = paypalTransaction();

    $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm());

    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && $request['purchase_units'][0]['description'] === 'Custom');
});

it('throws PayPal’s error when an order can’t be created', function() {
    $error = paypalError('UNPROCESSABLE_ENTITY', 'The requested action could not be performed.', 'DECIMAL_PRECISION', 'If the currency supports decimals, only two decimal place precision is supported.');
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders' => Http::response($error, 422),
    ]);
    $transaction = paypalTransaction();

    expect(fn() => $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm()))
        ->toThrow(function(PaymentException $e) use ($error) {
            // The payment form JS reads the error details from this JSON.
            expect(json_decode($e->getMessage(), true))->toBe($error);
        });
});

it('throws when PayPal rejects the credentials', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'Client Authentication failed'], 401),
    ]);
    $transaction = paypalTransaction();

    expect(fn() => $transaction->getGateway()->purchase($transaction, new OffsitePaymentForm()))
        ->toThrow(PaymentException::class, 'Client Authentication failed');
    Http::assertNotSent(fn(Request $request) => str_contains($request->url(), '/v2/'));
});

it('captures the order when the customer returns', function(array $order, bool $successful, bool $processing, bool $redirect) {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders/5O190127TN364715T/capture' => Http::response($order, 201),
    ]);
    $transaction = paypalTransaction();
    $transaction->reference = '5O190127TN364715T';

    $response = $transaction->getGateway()->completePurchase($transaction);

    expect($response->isSuccessful())->toBe($successful)
        ->and($response->isProcessing())->toBe($processing)
        ->and($response->isRedirect())->toBe($redirect)
        ->and($response->getTransactionReference())->toBe('5O190127TN364715T');

    Http::assertSent(fn(Request $request) => $request->method() === 'POST'
        && $request->url() === SANDBOX . '/v2/checkout/orders/5O190127TN364715T/capture'
        && $request->body() === '');
})->with([
    'completed' => [paypalOrder('COMPLETED', ['captures' => [['id' => '3C679366HH908993F', 'status' => 'COMPLETED']]]), true, false, false],
    'pending capture' => [paypalOrder('COMPLETED', ['captures' => [['id' => '3C679366HH908993F', 'status' => 'PENDING']]]), false, true, false],
    'declined capture' => [paypalOrder('COMPLETED', ['captures' => [['id' => '3C679366HH908993F', 'status' => 'DECLINED']]]), false, false, false],
    'failed capture' => [paypalOrder('COMPLETED', ['captures' => [['id' => '3C679366HH908993F', 'status' => 'FAILED']]]), false, false, false],
    'completed without payments' => [paypalOrder('COMPLETED'), true, false, false],
    'not approved yet' => [paypalOrder('PAYER_ACTION_REQUIRED'), false, false, true],
]);

it('reports PayPal’s error message when a capture fails', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders/*/capture' => Http::response(paypalError('UNPROCESSABLE_ENTITY', 'The requested action could not be performed.', 'INSTRUMENT_DECLINED', 'The instrument presented was declined.'), 422),
    ]);
    $transaction = paypalTransaction();
    $transaction->reference = '5O190127TN364715T';

    $response = $transaction->getGateway()->completePurchase($transaction);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isProcessing())->toBeFalse()
        ->and($response->isRedirect())->toBeFalse()
        ->and($response->getMessage())->toBe('The requested action could not be performed.')
        ->and($response->getCode())->toBe('422')
        ->and($response->getTransactionReference())->toBe('5O190127TN364715T');
});

it('authorizes the order when the customer returns', function(string $status, bool $successful, bool $processing) {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/checkout/orders/5O190127TN364715T/authorize' => Http::response(paypalOrder('COMPLETED', ['authorizations' => [['id' => '0VF52814937998046', 'status' => $status]]]), 201),
    ]);
    $transaction = paypalTransaction(type: TransactionModel::TYPE_AUTHORIZE);
    $transaction->reference = '5O190127TN364715T';

    $response = $transaction->getGateway()->completeAuthorize($transaction);

    expect($response->isSuccessful())->toBe($successful)
        ->and($response->isProcessing())->toBe($processing);
    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/authorize') && $request->body() === '{}');
})->with([
    'created' => ['CREATED', true, false],
    'pending' => ['PENDING', false, true],
    'denied' => ['DENIED', true, false],
]);

it('captures the authorization stored on the parent transaction', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/payments/authorizations/0VF52814937998046/capture' => Http::response(['id' => '2GG279541U471931P', 'status' => 'COMPLETED'], 201),
    ]);
    $authorize = paypalTransaction(type: TransactionModel::TYPE_AUTHORIZE);
    $authorize->reference = '5O190127TN364715T';
    $authorize->status = TransactionModel::STATUS_SUCCESS;
    // The shape PayPal Checkout for Craft Commerce 3 stored, so authorizations made before upgrading can be captured.
    $authorize->response = json_encode([
        'statusCode' => 201,
        'result' => paypalOrder('COMPLETED', ['authorizations' => [['id' => '0VF52814937998046', 'status' => 'CREATED']]]),
        'headers' => [],
    ]);
    app(Transactions::class)->saveTransaction($authorize);

    $capture = app(Transactions::class)->createTransaction(null, $authorize, TransactionModel::TYPE_CAPTURE);
    $response = $authorize->getGateway()->capture($capture, '5O190127TN364715T');

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getTransactionReference())->toBe('2GG279541U471931P');
    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/payments/authorizations/0VF52814937998046/capture') && $request->body() === '{}');
});

it('refuses to capture without an authorization ID', function() {
    Http::fake();
    $authorize = paypalTransaction(type: TransactionModel::TYPE_AUTHORIZE);
    $authorize->status = TransactionModel::STATUS_SUCCESS;
    $authorize->response = ['statusCode' => 201, 'result' => paypalOrder('COMPLETED'), 'headers' => []];
    app(Transactions::class)->saveTransaction($authorize);

    $capture = app(Transactions::class)->createTransaction(null, $authorize, TransactionModel::TYPE_CAPTURE);

    expect(fn() => $authorize->getGateway()->capture($capture, (string)$authorize->reference))->toThrow(PaymentException::class);
    Http::assertNothingSent();
});

it('refunds the capture of a purchase', function(array $refund, bool $successful, bool $processing, string $message) {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/payments/captures/3C679366HH908993F/refund' => Http::response($refund, 201),
    ]);
    $purchase = paypalTransaction(20);
    $purchase->reference = '5O190127TN364715T';
    $purchase->status = TransactionModel::STATUS_SUCCESS;
    $purchase->response = ['statusCode' => 201, 'result' => paypalOrder('COMPLETED', ['captures' => [['id' => '3C679366HH908993F', 'status' => 'COMPLETED']]]), 'headers' => []];
    app(Transactions::class)->saveTransaction($purchase);

    // Load it again, so the response is the JSON stored in the database.
    $purchase = app(Transactions::class)->getTransactionById($purchase->id);
    $refundTransaction = app(Transactions::class)->createTransaction(null, $purchase, TransactionModel::TYPE_REFUND);
    $refundTransaction->paymentAmount = 5.5;
    $response = $purchase->getGateway()->refund($refundTransaction);

    expect($response->isSuccessful())->toBe($successful)
        ->and($response->isProcessing())->toBe($processing)
        ->and($response->getMessage())->toBe($message);
    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/payments/captures/3C679366HH908993F/refund')
        && $request['amount'] === ['currency_code' => 'USD', 'value' => '5.50']);
})->with([
    'completed' => [['id' => '1JU08902781691411', 'status' => 'COMPLETED'], true, false, ''],
    'pending' => [['id' => '1JU08902781691411', 'status' => 'PENDING', 'status_details' => ['reason' => 'ECHECK']], false, true, 'ECHECK'],
    'failed' => [['id' => '1JU08902781691411', 'status' => 'FAILED'], false, false, ''],
]);

it('refunds a capture transaction by its own ID', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/payments/captures/2GG279541U471931P/refund' => Http::response(['id' => '1JU08902781691411', 'status' => 'COMPLETED'], 201),
    ]);
    $authorize = paypalTransaction(1500, type: TransactionModel::TYPE_AUTHORIZE);
    $authorize->status = TransactionModel::STATUS_SUCCESS;
    app(Transactions::class)->saveTransaction($authorize);

    $capture = app(Transactions::class)->createTransaction(null, $authorize, TransactionModel::TYPE_CAPTURE);
    $capture->status = TransactionModel::STATUS_SUCCESS;
    $capture->response = ['statusCode' => 201, 'result' => ['id' => '2GG279541U471931P', 'status' => 'COMPLETED'], 'headers' => []];
    app(Transactions::class)->saveTransaction($capture);

    $refund = app(Transactions::class)->createTransaction(null, $capture, TransactionModel::TYPE_REFUND);
    $refund->paymentAmount = 1500;
    $refund->paymentCurrency = 'JPY';

    expect($capture->getGateway()->refund($refund)->isSuccessful())->toBeTrue();
    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/v2/payments/captures/2GG279541U471931P/refund')
        && $request['amount'] === ['currency_code' => 'JPY', 'value' => '1500']);
});

it('reports PayPal’s error message when a refund fails', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/payments/captures/*/refund' => Http::response(paypalError('UNPROCESSABLE_ENTITY', 'The requested action could not be performed.', 'REFUND_AMOUNT_EXCEEDED', 'The refund amount must be less than or equal to the capture amount.'), 422),
    ]);
    $purchase = paypalTransaction(20);
    $purchase->status = TransactionModel::STATUS_SUCCESS;
    $purchase->response = ['statusCode' => 201, 'result' => paypalOrder('COMPLETED', ['captures' => [['id' => '3C679366HH908993F', 'status' => 'COMPLETED']]]), 'headers' => []];
    app(Transactions::class)->saveTransaction($purchase);

    $refund = app(Transactions::class)->createTransaction(null, $purchase, TransactionModel::TYPE_REFUND);
    $refund->paymentAmount = 50;
    $response = $purchase->getGateway()->refund($refund);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isProcessing())->toBeFalse()
        ->and($response->getMessage())->toBe('The requested action could not be performed.');
});

it('lets listeners modify the refund request', function() {
    Http::fake([
        SANDBOX . '/v1/oauth2/token' => Http::response(paypalToken()),
        SANDBOX . '/v2/payments/captures/*/refund' => Http::response(['id' => '1JU08902781691411', 'status' => 'COMPLETED'], 201),
    ]);
    Event::listen(function(GatewayRequestBuilding $event) {
        if ($event->type === 'refund') {
            $event->request['note_to_payer'] = 'Sorry';
        }
    });
    $purchase = paypalTransaction(20);
    $purchase->status = TransactionModel::STATUS_SUCCESS;
    $purchase->response = ['statusCode' => 201, 'result' => paypalOrder('COMPLETED', ['captures' => [['id' => '3C679366HH908993F', 'status' => 'COMPLETED']]]), 'headers' => []];
    app(Transactions::class)->saveTransaction($purchase);

    $refund = app(Transactions::class)->createTransaction(null, $purchase, TransactionModel::TYPE_REFUND);
    $refund->paymentAmount = 5;
    $purchase->getGateway()->refund($refund);

    Http::assertSent(fn(Request $request) => str_contains($request->url(), '/refund') && $request['note_to_payer'] === 'Sorry');
});

it('renders the PayPal Buttons container and registers the SDK', function() {
    HtmlStack::clear();

    $html = paypalGateway(['clientId' => 'abc 123'], 'authorize')->getPaymentFormHtml([
        'currency' => 'EUR',
        'disable-funding' => 'credit,card',
        'not-an-sdk-param' => 'x',
    ]);
    $bodyEnd = HtmlStack::bodyEndHtml();

    expect($html)
        ->toContain('class="paypal-rest-form"')
        ->toContain('data-env="sandbox"')
        ->toContain('data-complete="')
        ->toContain('commerce/payments/complete-payment')
        ->toContain('<div id="paypal-button-container"></div>')
        ->not->toContain('data-prepare')
        ->and($bodyEnd)
        ->toContain('https://www.paypal.com/sdk/js?client-id=abc+123&amp;intent=authorize&amp;currency=EUR&amp;disable-funding=credit%2Ccard"')
        ->toContain('data-namespace="paypal_checkout_sdk"')
        ->toContain('function initPaypalCheckout()')
        ->not->toContain('not-an-sdk-param');
});

it('hides the payment form submit button and control panel payments', function() {
    $gateway = paypalGateway();

    expect($gateway->showPaymentFormSubmitButton())->toBeFalse()
        ->and($gateway->cpPaymentsEnabled())->toBeFalse()
        ->and($gateway->supportsWebhooks())->toBeFalse()
        ->and($gateway->processWebHook()->getContent())->toBe('ok');
});

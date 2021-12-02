<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\paypalcheckout\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\ShippingMethod;
use craft\commerce\elements\Order;
use craft\commerce\errors\PaymentException;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\paypalcheckout\PayPalCheckoutBundle;
use craft\commerce\paypalcheckout\responses\CheckoutResponse;
use craft\commerce\paypalcheckout\responses\RefundResponse;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersAuthorizeRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\AuthorizationsCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

/**
 * This class represents the PayPal Checkout gateway
 *
 * @property bool $sendCartInfo Whether cart information should be sent to the payment gateway
 * @property bool $testMode Whether Test Mode should be used
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class Gateway extends BaseGateway
{
    const PAYMENT_TYPES = [
        'authorize' => 'AUTHORIZE',
        'purchase' => 'CAPTURE'
    ];

    /**
     * @since 1.1.0
     */
    const SDK_URL = 'https://www.paypal.com/sdk/js';

    /**
     * @var string
     */
    public $clientId;

    /**
     * @var string
     */
    public $secret;

    /**
     * @var string
     */
    public $brandName;

    /**
     * @var string
     */
    public $landingPage;

    /**
     * @var string|bool
     * @see getSendCartInfo()
     * @see setSendCartInfo()
     */
    private $_sendCartInfo = false;

    /**
     * @var string|bool
     * @see getTestMode()
     * @see setTestMode()
     */
    private $_testMode = false;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $attributes = parent::attributes();
        $attributes[] = 'sendCartInfo';
        $attributes[] = 'testMode';
        return $attributes;
    }

    /**
     * Returns whether Test Mode should be used.
     *
     * @param bool $parse Whether to parse the value as an environment variable
     * @return string|null
     * @since 1.3.1
     */
    public function getTestMode(bool $parse = true)
    {
        return $parse ? Craft::parseBooleanEnv($this->_testMode) : $this->_testMode;
    }

    /**
     * Sets whether Test Mode should be used.
     *
     * @param string|bool $testMode
     * @since 1.3.1
     */
    public function setTestMode($testMode): void
    {
        $this->_testMode = $testMode;
    }

    /**
     * Returns whether cart information should be sent to the payment gateway.
     *
     * @param bool $parse Whether to parse the value as an environment variable
     * @return string|null
     * @since 1.3.1
     */
    public function getSendCartInfo(bool $parse = true)
    {
        return $parse ? Craft::parseBooleanEnv($this->_sendCartInfo) : $this->_sendCartInfo;
    }

    /**
     * Sets whether cart information should be sent to the payment gateway.
     *
     * @param string|bool $sendCartInfo
     * @since 1.3.1
     */
    public function setSendCartInfo($sendCartInfo): void
    {
        $this->_sendCartInfo = $sendCartInfo;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'PayPal Checkout');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce-paypal-checkout/settings', ['gateway' => $this]);
    }

    /**
     * Returns payment Form HTML
     *
     * @param array $params
     * @return string|null
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
            'gateway' => $this,
            'currency' => Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso(),
        ];

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerJsFile(self::SDK_URL . '?' . $this->_sdkQueryParameters($params), ['data-namespace' => 'paypal_checkout_sdk']);

        // IE polyfill
        $view->registerJsFile('https://polyfill.io/v3/polyfill.min.js?features=fetch%2CPromise%2CPromise.prototype.finally');
        $view->registerAssetBundle(PayPalCheckoutBundle::class);

        $html = Craft::$app->getView()->renderTemplate('commerce-paypal-checkout/paymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getResponseModel($data): RequestResponseInterface
    {
        return new CheckoutResponse($data);
    }

    /**
     * @param $data
     * @return RefundResponse
     */
    public function getRefundResponseModel($data): RefundResponse
    {
        return new RefundResponse($data);
    }

    /**
     * Makes an authorize request.
     *
     * @param Transaction $transaction The authorize transaction
     * @param BasePaymentForm $form A form filled with payment info
     * @return RequestResponseInterface
     * @throws Exception
     * @throws PaymentException
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // Authorize is the same request as purchase only that the intent is different
        // which is set from the gateway settings
        return $this->purchase($transaction, $form);
    }

    /**
     * Makes a capture request.
     *
     * @param Transaction $transaction The capture transaction
     * @param string $reference Reference for the transaction being captured.
     * @return RequestResponseInterface
     * @throws PaymentException
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        $parentTransaction = $transaction->getParent();
        if (!$parentTransaction) {
            Craft::error('Cannot retrieve parent transaction', __METHOD__);
        }

        $response = json_decode($parentTransaction->response, false);
        $authorizationId = $response->result->purchase_units[0]->payments->authorizations[0]->id ?? null;

        if (!$authorizationId) {
            Craft::error('An Authorization ID is required to capture', __METHOD__);
        }

        $request = new AuthorizationsCaptureRequest($authorizationId);
        $request->body = '{}';
        $request->prefer('return=representation');
        $client = $this->createClient();

        try {
            $apiResponse = $client->execute($request);
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }

        return $this->getResponseModel($apiResponse);
    }

    /**
     * Complete the authorization for offsite payments.
     *
     * @param Transaction $transaction The transaction
     * @return RequestResponseInterface
     * @throws PaymentException
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        $request = new OrdersAuthorizeRequest($transaction->reference);
        $request->body = '{}';
        $request->prefer('return=representation');
        $client = $this->createClient();

        try {
            $apiResponse = $client->execute($request);
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }

        return $this->getResponseModel($apiResponse);
    }

    /**
     * Complete the purchase for offsite payments.
     *
     * @param Transaction $transaction The transaction
     * @return RequestResponseInterface
     * @throws PaymentException
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        $request = new OrdersCaptureRequest($transaction->reference);
        $client = $this->createClient();

        try {
            $apiResponse = $client->execute($request);
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }

        return $this->getResponseModel($apiResponse);
    }

    /**
     * Creates a payment source from source data and user id.
     *
     * @param BasePaymentForm $sourceData
     * @param int $userId
     * @return PaymentSource
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        // TODO: Implement createPaymentSource() method.
    }

    /**
     * Deletes a payment source on the gateway by its token.
     *
     * @param string $token
     * @return bool
     */
    public function deletePaymentSource($token): bool
    {
        // TODO: Implement deletePaymentSource() method.
    }

    /**
     * Returns payment form model to use in payment forms.
     *
     * @return BasePaymentForm
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    /**
     * Makes a purchase request.
     *
     * @param Transaction $transaction The purchase transaction
     * @param BasePaymentForm $form A form filled with payment info
     * @return RequestResponseInterface
     * @throws PaymentException
     * @throws Exception
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        $requestData = $this->buildCreateOrderRequestData($transaction);

        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = $requestData;

        $client = $this->createClient();

        try {
            $apiResponse = $client->execute($request);
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }

        return $this->getResponseModel($apiResponse);
    }

    /**
     * @return PayPalHttpClient
     */
    public function createClient(): PayPalHttpClient
    {
        if (!Craft::parseBooleanEnv($this->testMode)) {
            $environment = new ProductionEnvironment(Craft::parseEnv($this->clientId), Craft::parseEnv($this->secret));
        } else {
            $environment = new SandboxEnvironment(Craft::parseEnv($this->clientId), Craft::parseEnv($this->secret));
        }

        return new PayPalHttpClient($environment);
    }

    /**
     * Makes an refund request.
     *
     * @param Transaction $transaction The refund transaction
     * @return RequestResponseInterface
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $parentTransaction = $transaction->getParent();
        if (!$parentTransaction) {
            Craft::error('Cannot retrieve parent transaction', __METHOD__);
        }

        $body = [
            'amount' => [
                'value' => $transaction->paymentAmount,
                'currency_code' => $transaction->paymentCurrency
            ]
        ];

        // Get the data from different locations based on which type of transaction
        // the parent was
        $response = json_decode($parentTransaction->response, true);
        if ($parentTransaction->type == 'capture') {
            $captureId = ArrayHelper::getValue($response, 'result.id', null);
        } else {
            $captureId = ArrayHelper::getValue($response, 'result.purchase_units.0.payments.captures.0.id', null);
        }

        $request = new CapturesRefundRequest($captureId);
        $request->body = $body;
        $request->prefer('return=representation');
        $client = $this->createClient();

        try {
            $apiResponse = $client->execute($request);
            return $this->getRefundResponseModel($apiResponse);
        } catch (\Exception $e) {

            return $this->getRefundResponseModel([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Processes a webhook and return a response
     *
     * @return WebResponse
     * @throws Throwable if something goes wrong
     */
    public function processWebHook(): WebResponse
    {

    }

    /**
     * Returns true if gateway supports authorize requests.
     *
     * @return bool
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports capture requests.
     *
     * @return bool
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports completing authorize requests
     *
     * @return bool
     */
    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports completing purchase requests
     *
     * @return bool
     */
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports payment sources
     *
     * @return bool
     */
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    /**
     * Returns true if gateway supports purchase requests.
     *
     * @return bool
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports refund requests.
     *
     * @return bool
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports partial refund requests.
     *
     * @return bool
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports webhooks.
     *
     * @return bool
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function cpPaymentsEnabled(): bool
    {
        return false;
    }

    /**
     * @param Transaction $transaction
     * @return array
     * @throws Exception
     */
    protected function buildCreateOrderRequestData(Transaction $transaction): array
    {
        $order = $transaction->order;
        $requestData = [];
        $requestData['intent'] = self::PAYMENT_TYPES[$this->paymentType] ?? 'CAPTURE';

        if ($payer = $this->_buildPayer($order)) {
            $requestData['payer'] = $payer;
        }

        $requestData['purchase_units'] = $this->_buildPurchaseUnits($order, $transaction);

        $shippingPreference = isset($requestData['purchase_units'][0]['shipping']) && !empty($requestData['purchase_units'][0]['shipping']) && isset($requestData['purchase_units'][0]['shipping']['address']) ? 'SET_PROVIDED_ADDRESS' : 'NO_SHIPPING';

        $requestData['application_context'] = [
            'brand_name' => $this->brandName,
            'locale' => Craft::$app->getLocale()->id,
            'landing_page' => Craft::parseEnv($this->landingPage),
            'shipping_preference' => $shippingPreference,
            'user_action' => 'PAY_NOW',
            'return_url' => UrlHelper::siteUrl($order->returnUrl),
            'cancel_url' => UrlHelper::siteUrl($order->cancelUrl)
        ];

        return $requestData;
    }

    /**
     * Build purchase units adhering to the criteria set out in the docs
     * https://developer.paypal.com/docs/api/orders/v2/#definition-purchase_unit
     *
     * @param Order $order
     * @param Transaction $transaction
     * @return array
     */
    private function _buildPurchaseUnits(Order $order, Transaction $transaction): array
    {
        // TODO update to `getName()` method when updating the cms requirement.
        $siteName = Craft::$app->getSites()->getCurrentSite()->name;
        $purchaseUnits = [
            'description' => StringHelper::truncate($siteName, 127, ''),
            'invoice_id' => StringHelper::truncate($order->number, 127, ''),
            'custom_id' => StringHelper::truncate($transaction->hash, 127, ''),
            'soft_descriptor' => StringHelper::truncate(StringHelper::regexReplace($siteName, "[^a-zA-Z0-9\*\.\-\s]", ''), 22, ''),
            'amount' => $this->_buildAmount($order, $transaction),
            'items' => $this->_buildItems($order, $transaction),
        ];

        $shipping = $this->_buildShipping($order);
        if (!empty($shipping)) {
            $purchaseUnits['shipping'] = $shipping;
        }

        return [
            $purchaseUnits
        ];
    }

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @return array
     */
    private function _buildAmount(Order $order, Transaction $transaction): array
    {
        $return = [
            'currency_code' => $transaction->paymentCurrency,
            'value' => (string)$transaction->paymentAmount,
        ];

        if ($this->getSendCartInfo() && !$this->_isPartialPayment($order) && $this->_isPaymentInBaseCurrency($order, $transaction)) {
            $return['breakdown'] = [
                'item_total' =>
                    [
                        'currency_code' => $order->paymentCurrency,
                        'value' => (string)Currency::round($order->getItemSubtotal()),
                    ],
                'shipping' =>
                    [
                        'currency_code' => $order->paymentCurrency,
                        'value' => (string)Currency::round($order->getTotalShippingCost()),
                    ],
                'tax_total' =>
                    [
                        'currency_code' => $order->paymentCurrency,
                        'value' => (string)Currency::round($order->getTotalTax()),
                    ],
            ];

            // $discount = $order->getAdjustmentsTotalByType('discount') * -1;
            $discount = $order->getTotalDiscount();
            if ($discount !== 0) {
                $return['breakdown']['discount'] = [
                    'currency_code' => $order->paymentCurrency,
                    'value' => (string)Currency::round($discount * -1), // Needs to be a positive number
                ];
            }
        }

        return $return;
    }

    /**
     * Build items array adhering to the PayPal API spec
     * https://developer.paypal.com/docs/api/orders/v2/#definition-item
     *
     * @param Order $order
     * @param Transaction $transaction
     * @return array
     */
    private function _buildItems(Order $order, Transaction $transaction): array
    {
        if (!$this->getSendCartInfo() || $this->_isPartialPayment($order) || !$this->_isPaymentInBaseCurrency($order, $transaction)) {
            return [];
        }

        $lineItems = [];
        foreach ($order->getLineItems() as $lineItem) {
            $lineItems[] = [
                'name' => StringHelper::truncate($lineItem->description, 127, ''), // required
                'sku' => StringHelper::truncate($lineItem->sku, 127, ''),
                'unit_amount' => [
                    'currency_code' => $order->paymentCurrency,
                    'value' => (string)Currency::round($lineItem->onSale ? $lineItem->salePrice : $lineItem->price),
                ], // required
                'quantity' => $lineItem->qty, // required
            ];
        }

        return $lineItems;
    }

    /**
     * @param Order $order
     * @return array
     */
    private function _buildShipping(Order $order): array
    {
        /** @var ShippingMethod $shippingMethod */
        $shippingMethod = $order->getShippingMethod();
        /** @var Address $shippingAddress */
        $shippingAddress = $order->shippingAddress;

        $return = [];

        if ($shippingAddress && $shippingAddress->country) {
            $return['address'] = $this->_buildAddressArray($shippingAddress);

            $name = $shippingAddress->fullName ?: $shippingAddress->firstName . ' ' . $shippingAddress->lastName;
            if ($name) {
                $return['name'] = ['full_name' => StringHelper::truncate($name, 300, '')];
            }
        }

        if ($shippingAddress && $shippingMethod) {
            $return['method'] = $shippingMethod->name;
        }

        return $return;
    }

    /**
     * Build payer data based on APi spec
     * https://developer.paypal.com/docs/api/orders/v2/#definition-payer
     *
     * @param Order $order
     * @return array
     * @since 1.1.0
     */
    private function _buildPayer(Order $order): array
    {
        /** @var Address $shippingAddress */
        $billingAddress = $order->billingAddress;

        $return = [
            'email_address' => StringHelper::truncate($order->email, 254, ''),
        ];

        $name = [];
        if ($billingAddress->fullName || $billingAddress->firstName) {
            $name['given_name'] = StringHelper::truncate($billingAddress->fullName ?: $billingAddress->firstName, 140, '');
        }

        if (!$billingAddress->fullName && $billingAddress->lastName) {
            $name['surname'] = StringHelper::truncate($billingAddress->lastName, 140, '');
        }

        if (!empty($name)) {
            $return['name'] = $name;
        }

        if ($billingAddress && $billingAddress->country) {
            $return['address'] = $this->_buildAddressArray($billingAddress);
        }

        return $return;
    }

    /**
     * Build address data based on API spec
     * https://developer.paypal.com/docs/api/orders/v2/#definition-address_portable
     *
     * @param Address $address
     * @return array
     * @since 1.1.1
     */
    private function _buildAddressArray(Address $address): array
    {
        $stateText = $address->stateText;
        if ($address->stateId && $state = $address->getState()) {
            $stateText = $state->abbreviation;
        }

        return [
            'address_line_1' => StringHelper::truncate($address->address1, 300, ''),
            'address_line_2' => StringHelper::truncate($address->address2, 300, ''),
            'admin_area_2' => StringHelper::truncate($address->city, 120, ''),
            'admin_area_1' => StringHelper::truncate($stateText, 300, ''),
            'postal_code' => StringHelper::truncate($address->zipCode, 60, ''),
            'country_code' => $address->country->iso,
        ];
    }

    /**
     * @param Order $order
     * @return bool
     * @since 1.0.5
     */
    private function _isPartialPayment(Order $order): bool
    {
        return $order->hasOutstandingBalance() && $order->getOutstandingBalance() < $order->getTotal();
    }

    /**
     * @param Order $order
     * @param Transaction $transaction
     * @return bool
     * @since 1.1.0
     */
    private function _isPaymentInBaseCurrency(Order $order, Transaction $transaction): bool
    {
        return $order->currency == $transaction->paymentCurrency;
    }

    /**
     * @param array $passedParams
     * @return string
     * @since 1.1.0
     */
    private function _sdkQueryParameters(array $passedParams): string
    {
        $passedParamsMergeKeys = [
            'currency',
            'disable-card',
            'disable-funding',
            'locale',
        ];
        $intent = strtolower(self::PAYMENT_TYPES[$this->paymentType]);
        $params = [
            'client-id' => Craft::parseEnv($this->clientId),
            'intent' => $intent,
        ];

        foreach ($passedParamsMergeKeys as $passedParamsMergeKey) {
            if (isset($passedParams[$passedParamsMergeKey])) {
                $params[$passedParamsMergeKey] = $passedParams[$passedParamsMergeKey];
            }
        }

        foreach ($params as $key => &$param) {
            $param = $key . '=' . urlencode($param);
        }

        return implode('&', $params);
    }
}

<?php

declare(strict_types=1);

namespace CraftCms\CommercePayPalCheckout;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\BooleanMenu;
use CraftCms\Cms\Ui\Controls\Combobox;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\TemplateContent;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Exceptions\NotImplementedException;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Exceptions\PaymentException;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Forms\OffsitePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Gateway\Gateway as BaseGateway;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionModel;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\CommercePayPalCheckout\Events\GatewayRequestBuilding;
use CraftCms\CommercePayPalCheckout\Responses\CheckoutResponse;
use CraftCms\CommercePayPalCheckout\Responses\RefundResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Override;

use function CraftCms\Cms\t;

/**
 * PayPal Checkout gateway, using the PayPal Orders v2 and Payments v2 REST APIs.
 *
 * Customers approve the payment with the PayPal Buttons from the PayPal JavaScript SDK. The gateway creates the PayPal
 * order when the buttons ask for one, and captures or authorizes it when the customer returns.
 */
class Gateway extends BaseGateway
{
    /**
     * Maps Commerce payment types to PayPal order intents.
     */
    public const array PAYMENT_TYPES = [
        'authorize' => 'AUTHORIZE',
        'purchase' => 'CAPTURE',
    ];

    public const string SDK_URL = 'https://www.paypal.com/sdk/js';

    public const array LANDING_PAGES = ['BILLING', 'LOGIN', 'NO_PREFERENCE'];

    /**
     * @var string|null PayPal REST app client ID. Can be set to an environment variable.
     */
    public ?string $clientId = null;

    /**
     * @var string|null PayPal REST app secret. Can be set to an environment variable.
     */
    public ?string $secret = null;

    /**
     * @var string|null The label that overrides the business name on PayPal’s pages. Can be set to an environment variable.
     */
    public ?string $brandName = null;

    /**
     * @var string|null The landing page to show on PayPal’s site: `BILLING`, `LOGIN` or `NO_PREFERENCE`. Can be set to
     * an environment variable.
     */
    public ?string $landingPage = null;

    /**
     * @var bool|string Whether to send the line items and cost breakdown to PayPal. Can be set to an environment variable.
     */
    public bool|string $sendCartInfo = false;

    /**
     * @var bool|string Whether to send the shipping address to PayPal. Can be set to an environment variable.
     */
    public bool|string $sendShippingInfo = true;

    /**
     * @var bool|string Whether to use PayPal’s sandbox. Can be set to an environment variable.
     */
    public bool|string $testMode = false;

    #[Override]
    public static function displayName(): string
    {
        return t('PayPal Checkout', category: 'commerce-paypal-checkout');
    }

    public function getClientId(bool $parse = true): ?string
    {
        return $parse ? Env::parse($this->clientId) : $this->clientId;
    }

    public function setClientId(?string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getSecret(bool $parse = true): ?string
    {
        return $parse ? Env::parse($this->secret) : $this->secret;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    public function getBrandName(bool $parse = true): ?string
    {
        return $parse ? Env::parse($this->brandName) : $this->brandName;
    }

    public function setBrandName(?string $brandName): void
    {
        $this->brandName = $brandName;
    }

    public function getLandingPage(bool $parse = true): ?string
    {
        return $parse ? Env::parse($this->landingPage) : $this->landingPage;
    }

    public function setLandingPage(?string $landingPage): void
    {
        $this->landingPage = $landingPage;
    }

    public function getSendCartInfo(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->sendCartInfo) ?? false) : $this->sendCartInfo;
    }

    public function setSendCartInfo(bool|string $sendCartInfo): void
    {
        $this->sendCartInfo = $sendCartInfo;
    }

    public function getSendShippingInfo(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->sendShippingInfo) ?? true) : $this->sendShippingInfo;
    }

    public function setSendShippingInfo(bool|string $sendShippingInfo): void
    {
        $this->sendShippingInfo = $sendShippingInfo;
    }

    public function getTestMode(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->testMode) ?? false) : $this->testMode;
    }

    public function setTestMode(bool|string $testMode): void
    {
        $this->testMode = $testMode;
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'paymentType' => ['required', Rule::in(array_keys(self::PAYMENT_TYPES))],
            'clientId' => ['required'],
            'secret' => ['required'],
        ]);
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        $landingPageOptions = array_map(fn(string $value, string $label) => ['label' => $label, 'value' => $value], self::LANDING_PAGES, [
            t('Billing', category: 'commerce-paypal-checkout'),
            t('Login', category: 'commerce-paypal-checkout'),
            t('No Preference', category: 'commerce-paypal-checkout'),
        ]);

        return Ui::make([
            Field::make(t('Send cart information to PayPal when making transactions?', category: 'commerce-paypal-checkout'))
                ->control(BooleanMenu::make('sendCartInfo')
                    ->value(BooleanMenu::optionValue($this->sendCartInfo))
                    ->includeEnvVars()),
            Field::make(t('Send shipping information to PayPal when making transactions?', category: 'commerce-paypal-checkout'))
                ->control(BooleanMenu::make('sendShippingInfo')
                    ->value(BooleanMenu::optionValue($this->sendShippingInfo))
                    ->includeEnvVars()),
            Field::make(t('Client ID', category: 'commerce-paypal-checkout'))
                ->required()
                ->control(Text::make('clientId')
                    ->value($this->clientId)
                    ->dir('ltr')
                    ->textExpanderTriggers(SelectOptions::getEnvTextExpanderTriggers())),
            Field::make(t('Secret', category: 'commerce-paypal-checkout'))
                ->required()
                ->control(Text::make('secret')
                    ->value($this->secret)
                    ->dir('ltr')
                    ->textExpanderTriggers(SelectOptions::getEnvTextExpanderTriggers())),
            Field::make(t('Landing Page', category: 'commerce-paypal-checkout'))
                ->control(Combobox::make('landingPage')
                    ->value($this->landingPage)
                    ->clearable()
                    ->options([...$landingPageOptions, ...SelectOptions::getEnvOptions(self::LANDING_PAGES)])),
            Field::make(t('Brand Name', category: 'commerce-paypal-checkout'))
                ->control(Text::make('brandName')
                    ->value($this->brandName)
                    ->textExpanderTriggers(SelectOptions::getEnvTextExpanderTriggers())),
            Field::make(t('Test Mode?', category: 'commerce-paypal-checkout'))
                ->control(BooleanMenu::make('testMode')
                    ->value(BooleanMenu::optionValue($this->testMode))
                    ->includeEnvVars()),
        ]);
    }

    #[Override]
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    /**
     * Returns the PayPal Buttons container, and registers the PayPal JavaScript SDK and the script that renders the
     * buttons.
     *
     * The buttons submit the surrounding form to `commerce/payments/pay` to create the PayPal order, and send the
     * customer to `commerce/payments/complete-payment` once they approve it.
     *
     * @param array<string, mixed> $params `currency`, `disable-card`, `disable-funding`, `enable-funding`, `locale` and
     * `components` are passed on to the PayPal JavaScript SDK.
     */
    #[Override]
    public function paymentFormUi(BasePaymentForm $form, array $params = []): ?Ui
    {
        $params = array_merge([
            'currency' => app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso(),
        ], $params);

        HtmlStack::jsFile(self::SDK_URL . '?' . $this->sdkQueryParameters($params), [
            'data-namespace' => 'paypal_checkout_sdk',
        ]);
        HtmlStack::js((string)file_get_contents(dirname(__DIR__) . '/resources/js/paymentForm.js'), key: 'commerce-paypal-checkout');

        $html = Html::tag('div', Html::tag('div', '', ['id' => 'paypal-button-container']), [
            'class' => 'paypal-rest-form',
            'data-env' => $this->getTestMode() ? 'sandbox' : 'production',
            'data-complete' => Url::actionUrl('commerce/payments/complete-payment'),
        ]);

        return Ui::make([
            TemplateContent::make('paypal-checkout', $html)->trusted(),
        ]);
    }

    /**
     * @param array{statusCode?: int, result?: mixed, headers?: array<string, string>} $data
     */
    public function getResponseModel(array $data): CheckoutResponse
    {
        return new CheckoutResponse($data);
    }

    /**
     * @param array{statusCode?: int, result?: mixed, headers?: array<string, string>} $data
     */
    public function getRefundResponseModel(array $data): RefundResponse
    {
        return new RefundResponse($data);
    }

    public function createClient(): Client
    {
        return new Client((string)$this->getClientId(), (string)$this->getSecret(), (bool)$this->getTestMode());
    }

    /**
     * Creates a PayPal order with the `AUTHORIZE` intent. The order is authorized in {@see completeAuthorize()}.
     */
    #[Override]
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // The intent comes from the gateway’s payment type, so this is the same request as purchase().
        return $this->purchase($transaction, $form);
    }

    /**
     * Creates a PayPal order. The PayPal Buttons use its ID to show the approval popup.
     */
    #[Override]
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        $body = $this->buildCreateOrderRequestData($transaction);

        try {
            $data = $this->createClient()->createOrder($body);
        } catch (RequestException|ConnectionException $e) {
            throw new PaymentException($this->errorBody($e), previous: $e);
        }

        return $this->getResponseModel($data);
    }

    #[Override]
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        try {
            $data = $this->createClient()->captureOrder((string)$transaction->reference);
        } catch (RequestException|ConnectionException $e) {
            $data = $this->errorResponseData($e, $transaction);
        }

        return $this->getResponseModel($data);
    }

    #[Override]
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        try {
            $data = $this->createClient()->authorizeOrder((string)$transaction->reference);
        } catch (RequestException|ConnectionException $e) {
            $data = $this->errorResponseData($e, $transaction);
        }

        return $this->getResponseModel($data);
    }

    /**
     * Captures the authorization stored on the parent (authorize) transaction.
     */
    #[Override]
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        $parent = $transaction->getParent();
        if (!$parent) {
            Log::error('Cannot retrieve parent transaction');
        }

        $authorizationId = Arr::get($this->decodeResponse($parent?->response), 'result.purchase_units.0.payments.authorizations.0.id');

        if (!$authorizationId) {
            Log::error('An Authorization ID is required to capture');
            throw new PaymentException(t('An Authorization ID is required to capture', category: 'commerce-paypal-checkout'));
        }

        try {
            $data = $this->createClient()->captureAuthorization((string)$authorizationId);
        } catch (RequestException|ConnectionException $e) {
            throw new PaymentException($this->errorBody($e), previous: $e);
        }

        return $this->getResponseModel($data);
    }

    /**
     * Refunds the capture stored on the parent (purchase or capture) transaction.
     */
    #[Override]
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $parent = $transaction->getParent();
        if (!$parent) {
            Log::error('Cannot retrieve parent transaction');
        }

        $event = new GatewayRequestBuilding($transaction, 'refund', [
            'amount' => $this->amount($transaction->paymentAmount, (string)$transaction->paymentCurrency),
        ]);
        event($event);

        // A capture transaction’s response is the capture itself; a purchase transaction’s response is the order.
        $response = $this->decodeResponse($parent?->response);
        $captureId = $parent?->type === TransactionModel::TYPE_CAPTURE
            ? Arr::get($response, 'result.id')
            : Arr::get($response, 'result.purchase_units.0.payments.captures.0.id');

        try {
            return $this->getRefundResponseModel($this->createClient()->refundCapture((string)$captureId, $event->request));
        } catch (RequestException|ConnectionException $e) {
            return $this->getRefundResponseModel([
                'statusCode' => 0,
                'result' => Json::decodeIfJson($this->errorBody($e)),
                'headers' => [],
            ]);
        }
    }

    #[Override]
    public function processWebHook(): Response
    {
        return response('ok');
    }

    #[Override]
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException(t('Payment sources are not supported by this gateway', category: 'commerce'));
    }

    #[Override]
    public function deletePaymentSource(string $token): bool
    {
        throw new NotImplementedException(t('Payment sources are not supported by this gateway', category: 'commerce'));
    }

    #[Override]
    public function supportsAuthorize(): bool
    {
        return true;
    }

    #[Override]
    public function supportsCapture(): bool
    {
        return true;
    }

    #[Override]
    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    #[Override]
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    #[Override]
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPurchase(): bool
    {
        return true;
    }

    #[Override]
    public function supportsRefund(): bool
    {
        return true;
    }

    #[Override]
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    #[Override]
    public function supportsWebhooks(): bool
    {
        return false;
    }

    #[Override]
    public function cpPaymentsEnabled(): bool
    {
        return false;
    }

    #[Override]
    public function showPaymentFormSubmitButton(): bool
    {
        return false;
    }

    /**
     * Builds the body of the PayPal [Create order](https://developer.paypal.com/docs/api/orders/v2/#orders_create)
     * request, and dispatches {@see GatewayRequestBuilding} so listeners can change it.
     *
     * @return array<string, mixed>
     */
    protected function buildCreateOrderRequestData(Transaction $transaction): array
    {
        $order = $transaction->getOrder() ?? throw new PaymentException('The transaction has no order.');

        $requestData = [];
        $requestData['intent'] = self::PAYMENT_TYPES[$this->paymentType] ?? 'CAPTURE';

        if ($payer = $this->buildPayer($order)) {
            $requestData['payer'] = $payer;
        }

        $requestData['purchase_units'] = $this->buildPurchaseUnits($order, $transaction);

        $shippingPreference = !empty($requestData['purchase_units'][0]['shipping']['address']) ? 'SET_PROVIDED_ADDRESS' : 'NO_SHIPPING';

        $requestData['application_context'] = [
            'brand_name' => $this->getBrandName(),
            'locale' => I18N::getLocale()->id,
            'landing_page' => $this->getLandingPage(),
            'shipping_preference' => $shippingPreference,
            'user_action' => 'PAY_NOW',
            'return_url' => Url::siteUrl((string)$order->returnUrl),
            'cancel_url' => Url::siteUrl((string)$order->cancelUrl),
        ];

        $event = new GatewayRequestBuilding($transaction, 'purchase', $requestData);
        event($event);

        return $event->request;
    }

    /**
     * Returns the query string for the PayPal JavaScript SDK URL.
     *
     * @param array<string, mixed> $params The params passed to `getPaymentFormHtml()`
     * @see https://developer.paypal.com/sdk/js/configuration/#link-queryparameters
     */
    protected function sdkQueryParameters(array $params): string
    {
        $query = [
            'client-id' => $this->getClientId(),
            'intent' => strtolower(self::PAYMENT_TYPES[$this->paymentType] ?? 'CAPTURE'),
        ];

        foreach (['currency', 'disable-card', 'disable-funding', 'enable-funding', 'locale', 'components'] as $key) {
            if (isset($params[$key])) {
                $query[$key] = $params[$key];
            }
        }

        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = $key . '=' . urlencode((string)$value);
        }

        return implode('&', $parts);
    }

    /**
     * Builds the purchase units.
     *
     * @return list<array<string, mixed>>
     * @see https://developer.paypal.com/docs/api/orders/v2/#definition-purchase_unit_request
     */
    private function buildPurchaseUnits(Order $order, Transaction $transaction): array
    {
        $siteName = Sites::getCurrentSite()->getName();

        $purchaseUnit = [
            'description' => $this->truncate($siteName, 127),
            'invoice_id' => $this->truncate($order->number, 127),
            'custom_id' => $this->truncate($transaction->hash, 127),
            'soft_descriptor' => $this->truncate(preg_replace('/[^a-zA-Z0-9\*\.\-\s]/', '', $siteName), 22),
            'amount' => $this->buildAmount($order, $transaction),
            'items' => $this->buildItems($order, $transaction),
        ];

        $shipping = $this->buildShipping($order);
        if (!empty($shipping)) {
            $purchaseUnit['shipping'] = $shipping;
        }

        return [$purchaseUnit];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAmount(Order $order, Transaction $transaction): array
    {
        $return = $this->amount($transaction->paymentAmount, (string)$transaction->paymentCurrency);

        if ($this->sendsCart($order, $transaction)) {
            $currency = $order->getPaymentCurrency();

            $return['breakdown'] = [
                'item_total' => $this->amount($order->getItemSubtotal(), $currency),
                'shipping' => $this->amount($order->getTotalShippingCost(), $currency),
                'tax_total' => $this->amount($order->getTotalTax(), $currency),
            ];

            $discount = $order->getTotalDiscount();
            if ($discount != 0) {
                // PayPal needs the discount as a positive amount.
                $return['breakdown']['discount'] = $this->amount($discount * -1, $currency);
            }
        }

        return $return;
    }

    /**
     * @return list<array<string, mixed>>
     * @see https://developer.paypal.com/docs/api/orders/v2/#definition-item
     */
    private function buildItems(Order $order, Transaction $transaction): array
    {
        if (!$this->sendsCart($order, $transaction)) {
            return [];
        }

        $currency = $order->getPaymentCurrency();
        $items = [];

        foreach ($order->getLineItems() as $lineItem) {
            $items[] = [
                'name' => $this->truncate($lineItem->getDescription(), 127),
                'sku' => $this->truncate($lineItem->getSku(), 127),
                'unit_amount' => $this->amount($lineItem->getOnPromotion() ? $lineItem->getSalePrice() : $lineItem->getPrice(), $currency),
                'quantity' => $lineItem->qty,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShipping(Order $order): array
    {
        $return = [];

        if (!$this->getSendShippingInfo()) {
            return $return;
        }

        $shippingMethod = $order->getShippingMethod();
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress && $shippingAddress->getCountryCode()) {
            $return['address'] = $this->buildAddress($shippingAddress);

            $name = $shippingAddress->fullName ?: $shippingAddress->firstName . ' ' . $shippingAddress->lastName;
            if (trim($name)) {
                $return['name'] = ['full_name' => $this->truncate($name, 300)];
            }
        }

        if ($shippingAddress && $shippingMethod) {
            $return['method'] = $shippingMethod->getName();
        }

        return $return;
    }

    /**
     * @return array<string, mixed>|null
     * @see https://developer.paypal.com/docs/api/orders/v2/#definition-payer
     */
    private function buildPayer(Order $order): ?array
    {
        $billingAddress = $order->getBillingAddress();

        if (!$billingAddress && !$order->getEmail()) {
            return null;
        }

        $return = [
            'email_address' => $this->truncate($order->getEmail(), 254),
        ];

        if (!$billingAddress) {
            return $return;
        }

        $name = [];
        if ($billingAddress->firstName || $billingAddress->fullName) {
            $name['given_name'] = $this->truncate($billingAddress->firstName ?: $billingAddress->fullName, 140);
        }

        if ($billingAddress->lastName || $billingAddress->fullName) {
            $name['surname'] = $this->truncate($billingAddress->lastName ?: $billingAddress->fullName, 140);
        }

        if (!empty($name)) {
            $return['name'] = $name;
        }

        // PayPal requires a country on the address.
        if ($billingAddress->getCountryCode()) {
            $return['address'] = $this->buildAddress($billingAddress);
        }

        return $return;
    }

    /**
     * @return array<string, string>
     * @see https://developer.paypal.com/docs/api/orders/v2/#definition-address_portable
     */
    private function buildAddress(Address $address): array
    {
        return [
            'address_line_1' => $this->truncate($address->getAddressLine1(), 300),
            'address_line_2' => $this->truncate($address->getAddressLine2(), 300),
            'admin_area_2' => $this->truncate($address->getLocality(), 120),
            'admin_area_1' => $this->truncate($address->getAdministrativeArea(), 300),
            'postal_code' => $this->truncate($address->getPostalCode(), 60),
            'country_code' => $address->getCountryCode(),
        ];
    }

    /**
     * Whether to send the line items and cost breakdown, which only add up for full payments in the order’s currency.
     */
    private function sendsCart(Order $order, Transaction $transaction): bool
    {
        return $this->getSendCartInfo() && !$this->isPartialPayment($order) && $order->currency === $transaction->paymentCurrency;
    }

    private function isPartialPayment(Order $order): bool
    {
        return $order->hasOutstandingBalance() && $order->getOutstandingBalance() < $order->getTotal();
    }

    /**
     * @return array{currency_code: string, value: string}
     */
    private function amount(float $amount, string $currency): array
    {
        return [
            'currency_code' => $currency,
            'value' => app(Currencies::class)->getTeller($currency)->convertToString($amount),
        ];
    }

    private function truncate(?string $value, int $length): string
    {
        return mb_substr((string)$value, 0, $length);
    }

    /**
     * Returns a transaction’s stored response as an array.
     *
     * @return array<string, mixed>
     */
    private function decodeResponse(mixed $response): array
    {
        if (is_string($response)) {
            $response = Json::decodeIfJson($response);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * Returns the body PayPal sent with an error, or the connection error message.
     */
    private function errorBody(RequestException|ConnectionException $e): string
    {
        return $e instanceof RequestException ? $e->response->body() : $e->getMessage();
    }

    /**
     * @return array{statusCode: int, result: array<string, mixed>, headers: array<string, string>}
     */
    private function errorResponseData(RequestException|ConnectionException $e, Transaction $transaction): array
    {
        $message = Json::decodeIfJson($this->errorBody($e));

        return [
            'statusCode' => $e instanceof RequestException ? $e->response->status() : 400,
            'result' => [
                'id' => $transaction->reference,
                'message' => is_array($message) && isset($message['message']) ? $message['message'] : $message,
                'status' => CheckoutResponse::STATUS_ERROR,
            ],
            'headers' => [],
        ];
    }
}

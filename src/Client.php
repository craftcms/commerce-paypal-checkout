<?php

declare(strict_types=1);

namespace CraftCms\CommercePayPalCheckout;

use Composer\InstalledVersions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A small client for the PayPal REST API: OAuth 2.0 access tokens, Orders v2 and Payments v2.
 *
 * Each request method returns the response as an array with `statusCode`, `result` (the decoded JSON body) and
 * `headers` keys, which is how PayPal responses have always been stored on Commerce transactions. Requests that PayPal
 * answers with a non-2xx status throw a {@see RequestException}, and requests that can’t reach PayPal throw a
 * {@see ConnectionException}.
 *
 * @see https://developer.paypal.com/api/rest/
 */
class Client
{
    public const string LIVE_URL = 'https://api-m.paypal.com';

    public const string SANDBOX_URL = 'https://api-m.sandbox.paypal.com';

    /**
     * Seconds to take off a token’s lifetime when caching it, so a token isn’t used just as it expires.
     */
    private const int TOKEN_EXPIRY_MARGIN = 60;

    public function __construct(
        private readonly string $clientId,
        private readonly string $secret,
        private readonly bool $sandbox = false,
    ) {
    }

    public function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{statusCode: int, result: mixed, headers: array<string, string>}
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders_create
     */
    public function createOrder(array $body): array
    {
        return $this->send($this->request()->post('v2/checkout/orders', $body));
    }

    /**
     * @return array{statusCode: int, result: mixed, headers: array<string, string>}
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders_get
     */
    public function getOrder(string $orderId): array
    {
        return $this->send($this->request()->get('v2/checkout/orders/' . rawurlencode($orderId)));
    }

    /**
     * @return array{statusCode: int, result: mixed, headers: array<string, string>}
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders_authorize
     */
    public function authorizeOrder(string $orderId): array
    {
        return $this->send($this->request()->withBody('{}')->post('v2/checkout/orders/' . rawurlencode($orderId) . '/authorize'));
    }

    /**
     * @return array{statusCode: int, result: mixed, headers: array<string, string>}
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders_capture
     */
    public function captureOrder(string $orderId): array
    {
        // Sent without a body, as the PayPal Checkout SDK did.
        return $this->send($this->request()->withBody('')->post('v2/checkout/orders/' . rawurlencode($orderId) . '/capture'));
    }

    /**
     * @return array{statusCode: int, result: mixed, headers: array<string, string>}
     * @see https://developer.paypal.com/docs/api/payments/v2/#authorizations_capture
     */
    public function captureAuthorization(string $authorizationId): array
    {
        return $this->send($this->request()->withBody('{}')->post('v2/payments/authorizations/' . rawurlencode($authorizationId) . '/capture'));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{statusCode: int, result: mixed, headers: array<string, string>}
     * @see https://developer.paypal.com/docs/api/payments/v2/#captures_refund
     */
    public function refundCapture(string $captureId, array $body): array
    {
        return $this->send($this->request()->post('v2/payments/captures/' . rawurlencode($captureId) . '/refund', $body));
    }

    /**
     * Returns an access token, from the cache if a valid one was fetched before.
     *
     * @throws RequestException
     * @throws ConnectionException
     * @see https://developer.paypal.com/api/rest/authentication/
     */
    public function accessToken(): string
    {
        $cacheKey = 'commerce-paypal-checkout:access-token:' . hash('sha256', $this->baseUrl() . "\0" . $this->clientId . "\0" . $this->secret);

        $token = Cache::get($cacheKey);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $data = Http::baseUrl($this->baseUrl())
            ->withBasicAuth($this->clientId, $this->secret)
            ->acceptJson()
            ->asForm()
            ->withUserAgent($this->userAgent())
            ->post('v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()
            ->json();

        $token = (string)($data['access_token'] ?? '');
        $ttl = (int)($data['expires_in'] ?? 0) - self::TOKEN_EXPIRY_MARGIN;

        if ($token !== '' && $ttl > 0) {
            Cache::put($cacheKey, $token, $ttl);
        }

        return $token;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Prefer' => 'return=representation'])
            ->withUserAgent($this->userAgent());
    }

    /**
     * @return array{statusCode: int, result: mixed, headers: array<string, string>}
     * @throws RequestException
     */
    private function send(Response $response): array
    {
        $response->throw();

        return [
            'statusCode' => $response->status(),
            'result' => $response->body() === '' ? null : $response->json(),
            'headers' => array_map(fn(array $values) => implode(', ', $values), $response->headers()),
        ];
    }

    private function userAgent(): string
    {
        return implode(' ', [
            'CraftCommercePayPalCheckout/' . (InstalledVersions::getPrettyVersion('craftcms/commerce-paypal-checkout') ?? 'dev'),
            'CraftCommerce/' . (InstalledVersions::getPrettyVersion('craftcms/commerce') ?? 'dev'),
        ]);
    }
}

<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\paypalcheckout\injectors;

use Craft;
use PayPalCheckoutSdk\Core\AccessToken;
use PayPalCheckoutSdk\Core\AccessTokenRequest;
use PayPalCheckoutSdk\Core\PayPalEnvironment;
use PayPalCheckoutSdk\Core\RefreshTokenRequest;
use PayPalHttp\HttpClient;
use PayPalHttp\HttpException;
use PayPalHttp\HttpRequest;
use PayPalHttp\Injector;
use PayPalHttp\IOException;

/**
 * AuthorizationInjector class override
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.3.3
 */
class PayPalAuthorizationInjector implements Injector
{
    public $client;
    public $environment;
    public $refreshToken = null;
    public $accessToken = null;

    public function __construct(HttpClient $client, PayPalEnvironment $environment, $refreshToken = null)
    {
        $this->client = $client;
        $this->environment = $environment;
        $this->refreshToken = $refreshToken;
    }

    public function inject($httpRequest): void
    {
        if (!$this->hasAuthHeader($httpRequest) && !$this->isAuthRequest($httpRequest))
        {
            if (is_null($this->accessToken) || $this->accessToken->isExpired())
            {
                $this->accessToken = $this->getAccessToken();
            }
            $httpRequest->headers['Authorization'] = 'Bearer ' . $this->accessToken->token;
        }
    }

    /**
     * @return AccessToken|null
     * @throws HttpException
     * @throws IOException
     */
    public function getAccessToken(): ?AccessToken
    {
        // Try to retrieve the access token from cache to avoid extra calls.
        /** @var AccessToken|null $accessToken */
        $accessToken = Craft::$app->getCache()->get($this->getCacheKey());
        if (!$accessToken) {
            $accessTokenResponse = $this->client->execute(new AccessTokenRequest($this->environment, $this->refreshToken));
            $accessTokenResult = $accessTokenResponse->result;

            $accessToken = new AccessToken($accessTokenResult->access_token, $accessTokenResult->token_type, $accessTokenResult->expires_in);
            Craft::$app->getCache()->set($this->getCacheKey(), $accessToken, $accessToken->expiresIn);
        }

        return $accessToken;
    }

    /**
     * @return array
     */
    protected function getCacheKey(): array
    {
        return [
            'PayPalAuth',
            get_class($this->environment),
            $this->environment->authorizationString(),
        ];
    }

    /**
     * @param $request
     * @return bool
     */
    private function isAuthRequest($request): bool
    {
        return $request instanceof AccessTokenRequest || $request instanceof RefreshTokenRequest;
    }

    /**
     * @param HttpRequest $request
     * @return bool
     */
    private function hasAuthHeader(HttpRequest $request): bool
    {
        return array_key_exists("Authorization", $request->headers);
    }
}
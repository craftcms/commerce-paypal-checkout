<?php

namespace craft\commerce\paypalcheckout\responses;

use craft\commerce\base\RequestResponseInterface;
use PayPalHttp\HttpResponse;

/**
 * PayPal Checkout CheckoutResponse
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @package craft\commerce\paypalcheckout\responses
 * @since 1.0
 */
class CheckoutResponse implements RequestResponseInterface
{
    public const STATUS_ERROR = 'error';
    public const STATUS_REDIRECT = 'redirect';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESSFUL = 'successful';

    /**
     * @var string|null
     */
    protected ?string $status = null;

    /**
     * @var HttpResponse|null
     */
    protected ?HttpResponse $data = null;

    /**
     * @var string
     */
    private string $_message = '';

    /**
     * Construct the response
     *
     * @param HttpResponse|null $data
     */
    public function __construct(?HttpResponse $data)
    {
        $this->data = $data;

        if (isset($this->data->result->status, $this->data->result->message) && $this->data->result->status == self::STATUS_ERROR) {
            $this->setMessage($this->data->result->message);
        }
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        $this->status = self::STATUS_REDIRECT;

        if ($this->data && isset($this->data->result, $this->data->result->status) && $this->data->result->status == 'COMPLETED') {
            $this->status = self::STATUS_SUCCESSFUL;

            if (isset($this->data->result->purchase_units) && isset($this->data->result->purchase_units->payments)) {
                $captureStatus = null;
                $authorizeStatus = null;

                if (!empty($this->data->result->purchase_units->payments->captures)) {
                    $captureStatus = $this->data->result->purchase_units->payments->captures[0]->status;
                }

                if (!empty($this->data->result->purchase_units->payments->authorizations)) {
                    $authorizeStatus = $this->data->result->purchase_units->payments->authorizations[0]->status;
                }

                if ($captureStatus == 'PENDING' || $authorizeStatus == 'PENDING') {
                    $this->status = self::STATUS_PROCESSING;
                }
            }
        } elseif ($this->data && isset($this->data->result->status) && $this->data->result->status == self::STATUS_ERROR) {
            $this->status = self::STATUS_ERROR;
        }

        return $this->status;
    }

    /**
     * Returns whether the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_SUCCESSFUL;
    }

    /**
     * Returns whether the payment is being processed by gateway.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->getStatus() === self::STATUS_PROCESSING;
    }

    /**
     * Returns whether the user needs to be redirected.
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        // Only redirect when we are creating the transaction
        return $this->getStatus() === self::STATUS_REDIRECT;
    }

    /**
     * Returns the redirect method to use, if any.
     *
     * @return string
     */
    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    /**
     * Returns the redirect data provided.
     *
     * @return array
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * Returns the redirect URL to use, if any.
     *
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return (string)$this->data->result->id;
    }

    /**
     * Returns the transaction reference.
     *
     * @return string
     */
    public function getTransactionReference(): string
    {
        return $this->data->result->id;
    }

    /**
     * Returns the response code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->data->statusCode;
    }

    /**
     * Returns the data.
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @param string $message
     * @return void
     */
    public function setMessage(string $message): void
    {
        $this->_message = $message;
    }

    /**
     * Returns the gateway message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->_message;
    }

    /**
     * Perform the redirect.
     */
    public function redirect(): void
    {
        return;
    }
}

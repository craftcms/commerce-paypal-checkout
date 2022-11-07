<?php


namespace craft\commerce\paypalcheckout\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use PayPalHttp\HttpResponse;

/**
 * PayPal Checkout RefundResponse
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @package craft\commerce\paypalcheckout\responses
 * @since 1.0
 */
class RefundResponse implements RequestResponseInterface
{
    protected $data;

    /**
     * Construct the response
     *
     * @param HttpResponse $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Returns whether or not the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->data && isset($this->data->result->status) && $this->data->result->status == 'COMPLETED';
    }

    /**
     * Returns whether or not the payment is being processed by gateway.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return false;
    }

    /**
     * Returns whether or not the user needs to be redirected.
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * Returns the redirect method to use, if any.
     *
     * @return string
     */
    public function getRedirectMethod(): string
    {
        return '';
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
        return '';
    }

    /**
     * Returns the transaction reference.
     *
     * @return string
     */
    public function getTransactionReference(): string
    {
        return '';
    }

    /**
     * Returns the response code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return '';
    }

    /**
     * Returns the data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the gateway message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        if (!isset($this->data) || !$this->data || !isset($this->data->result)) {
            return '';
        }

        if (is_array($this->data->result) && isset($this->data->result['message'])) {
            return $this->data->result['message'];
        }

        if (isset($this->data->result->message)) {
            return $this->data->result->message ?? '';
        }

        return '';
    }

    /**
     * Perform the redirect.
     *
     * @return mixed
     */
    public function redirect()
    {
        return null;
    }
}

<?php

namespace craft\commerce\paypalcheckout\responses;

use craft\commerce\base\RequestResponseInterface;

/**
 * PayPal Checkout CheckoutResponse
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @package craft\commerce\paypalcheckout\responses
 * @since 1.0
 */
class CheckoutResponse implements RequestResponseInterface
{
    public CONST STATUS_REDIRECT = 'redirect';
    public CONST STATUS_PROCESSING = 'processing';
    public CONST STATUS_SUCCESSFUL = 'successful';

    protected $status;

    protected $data;

    // Private Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_processing = false;


    // Public Methods
    // =========================================================================

    /**
     * Construct the response
     *
     * @param $data
     */
    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        $this->status = self::STATUS_REDIRECT;

        if ($this->data && isset($this->data->result->status) && $this->data->result->status == 'COMPLETED') {
            $this->status = self::STATUS_SUCCESSFUL;

            $captureStatus = $this->data->result->purchase_units->payments->captures[0]->status ?? null;
            $authorizeStatus = $this->data->result->purchase_units->payments->authorizations[0]->status ?? null;
            if ($captureStatus == 'PENDING' || $authorizeStatus == 'PENDING') {
                $this->status = self::STATUS_PROCESSING;
            }
        }

        return $this->status;
    }
    /**
     * @param bool $status
     */
    public function setProcessing(bool $status)
    {
        $this->_processing = $status;
    }
    /**
     * Returns whether or not the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->getStatus() == self::STATUS_SUCCESSFUL;
    }

    /**
     * Returns whether or not the payment is being processed by gateway.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->getStatus() == self::STATUS_PROCESSING;
    }

    /**
     * Returns whether or not the user needs to be redirected.
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        // Only redirect when we are creating the transaction
        return !$this->isSuccessful();
    }

    /**
     * Returns the redirect method to use, if any.
     *
     * @return string
     */
    public function getRedirectMethod(): string
    {
        return $this->getStatus() == self::STATUS_REDIRECT;
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
        return ''.$this->data->result->id;
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
        return '';
    }

    /**
     * Perform the redirect.
     *
     * @return mixed
     */
    public function redirect()
    {
        // TODO: Implement redirect() method.
    }
}